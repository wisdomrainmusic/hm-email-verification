<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('HM_EV_Core')) {

class HM_EV_Core {
    const META_VERIFIED = '_hm_email_verified';
    const META_TOKEN    = '_hm_email_verify_token';
    const META_TGEN     = '_hm_email_verify_token_generated';

    const VERIFY_PAGE   = '/email-verification/';
    const AFTER_VERIFY  = '/my-account/';
    const COOLDOWN_SEC  = 60;

    public static function init() {
        // Register hooks (double-hook safe)
        add_action('user_register', [__CLASS__, 'on_register'], 20, 1);
        add_action('woocommerce_created_customer', [__CLASS__, 'on_register'], 20, 1);

        // Link handlers
        add_action('init', [__CLASS__, 'handle_verify_link']);
        add_action('init', [__CLASS__, 'handle_resend_request']);

        // Hard gate My Account (run as early as possible)
        add_action('template_redirect', [__CLASS__, 'guard_my_account_hard'], 0);
    }

    /**
     * Create token once (avoid overwrite on double-hook).
     * Optionally send email when called (default yes).
     */
    public static function on_register($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) return;

        // If already verified, no-op
        if (self::is_verified($user_id)) return;

        // Ensure token exists (do NOT overwrite)
        $token = get_user_meta($user_id, self::META_TOKEN, true);
        if (!is_string($token) || $token === '') {
            $token = self::generate_token();
            update_user_meta($user_id, self::META_TOKEN, $token);
        }

        // Ensure generated timestamp exists (for cooldown baseline)
        $tgen = intval(get_user_meta($user_id, self::META_TGEN, true));
        if ($tgen <= 0) {
            update_user_meta($user_id, self::META_TGEN, time());
        }

        // Send verification email (safe; idempotent-ish)
        self::send_verify_email($user_id, $token);
    }

    public static function handle_verify_link() {
        if (empty($_GET['hm_verify'])) return;

        $uid   = isset($_GET['uid']) ? absint($_GET['uid']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if (!$uid || $token === '') {
            self::redirect_verify(['v' => 'invalid']);
        }

        // User exists?
        $user = get_user_by('id', $uid);
        if (!$user) {
            self::redirect_verify(['v' => 'notfound']);
        }

        // Already verified?
        if (self::is_verified($uid)) {
            self::redirect_after_verify(['v' => 'success']);
        }

        $saved = get_user_meta($uid, self::META_TOKEN, true);
        if (!is_string($saved) || $saved === '' || !hash_equals($saved, $token)) {
            self::redirect_verify(['v' => 'invalid', 'email' => $user->user_email]);
        }

        // Mark verified + cleanup
        update_user_meta($uid, self::META_VERIFIED, 1);
        delete_user_meta($uid, self::META_TOKEN);
        // Keep META_TGEN as harmless history, or delete if you want:
        // delete_user_meta($uid, self::META_TGEN);

        self::redirect_after_verify(['v' => 'success']);
    }

    public static function handle_resend_request() {
        if (empty($_GET['hm_resend_verify'])) return;

        $email = '';
        if (!empty($_GET['email'])) {
            $email = sanitize_email(wp_unslash($_GET['email']));
        } elseif (is_user_logged_in()) {
            $u = wp_get_current_user();
            if ($u && !empty($u->user_email)) $email = $u->user_email;
        }

        if ($email === '' || !is_email($email)) {
            self::redirect_verify(['v' => 'invalid']);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            self::redirect_verify(['v' => 'notfound', 'email' => $email]);
        }

        $uid = intval($user->ID);

        if (self::is_verified($uid)) {
            self::redirect_verify(['v' => 'already', 'email' => $email]);
        }

        // Cooldown check
        $tgen = intval(get_user_meta($uid, self::META_TGEN, true));
        $now  = time();
        if ($tgen > 0 && ($now - $tgen) < self::COOLDOWN_SEC) {
            self::redirect_verify(['v' => 'cooldown', 'email' => $email]);
        }

        // Ensure token exists (do not overwrite unless missing)
        $token = get_user_meta($uid, self::META_TOKEN, true);
        if (!is_string($token) || $token === '') {
            $token = self::generate_token();
            update_user_meta($uid, self::META_TOKEN, $token);
        }

        // Update generation time to enforce cooldown
        update_user_meta($uid, self::META_TGEN, $now);

        // Send email
        self::send_verify_email($uid, $token);

        self::redirect_verify(['v' => 'sent', 'email' => $email]);
    }

    /**
     * Hard gate for My Account: force logout + redirect to verify page if unverified.
     */
    public static function guard_my_account_hard() {
        // Only applies to logged-in users
        if (!is_user_logged_in()) return;

        // Don't gate on verify page itself (avoid loops)
        if (self::is_verify_page_request()) return;

        // If this request is performing verify/resend, skip
        if (!empty($_GET['hm_verify']) || !empty($_GET['hm_resend_verify'])) return;

        if (!self::is_my_account_request()) return;

        $user = wp_get_current_user();
        if (!$user || empty($user->ID)) return;

        if (self::is_verified(intval($user->ID))) return;

        $email = !empty($user->user_email) ? $user->user_email : '';

        // Logout hard
        wp_logout();

        self::redirect_verify([
            'v' => 'check',
            'email' => $email,
        ]);
    }

    /* ==============================
     * Helpers
     * ============================== */

    protected static function is_verified($user_id) {
        $v = get_user_meta($user_id, self::META_VERIFIED, true);
        return !empty($v) && intval($v) === 1;
    }

    protected static function generate_token() {
        // 32 chars, URL-safe
        return wp_generate_password(32, false, false);
    }

    protected static function send_verify_email($user_id, $token) {
        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) return;

        $to = $user->user_email;

        $verify_url = add_query_arg(
            [
                'hm_verify' => 1,
                'uid'       => $user_id,
                'token'     => $token,
            ],
            home_url('/')
        );

        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $subject = 'Verify your email address';
        $body  = "Hello,\n\n";
        $body .= "To activate your account on {$site}, please verify your email address by clicking the link below:\n\n";
        $body .= $verify_url . "\n\n";
        $body .= "If you did not create an account, you can ignore this email.\n\n";
        $body .= "Regards,\n{$site}\n";

        $headers = [];
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        // wp_mail is always available, but still guard
        if (function_exists('wp_mail')) {
            wp_mail($to, $subject, $body, $headers);
        }
    }

    protected static function is_my_account_request() {
        $req_path = self::current_path();

        // 1) URI contains /my-account
        if (strpos($req_path, '/my-account') !== false) return true;

        // 2) Woo permalink compare if available
        if (function_exists('wc_get_page_permalink')) {
            $perma = wc_get_page_permalink('myaccount');
            if ($perma) {
                $p = wp_parse_url($perma, PHP_URL_PATH);
                if ($p && strpos($req_path, rtrim($p, '/')) === 0) return true;
            }
        }

        // 3) WP conditional fallback
        if (function_exists('is_account_page') && is_account_page()) return true;

        return false;
    }

    protected static function is_verify_page_request() {
        $req_path = self::current_path();
        $verify_path = rtrim(self::VERIFY_PAGE, '/');
        if ($verify_path === '') $verify_path = '/email-verification';

        return (strpos($req_path, $verify_path) !== false);
    }

    protected static function current_path() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = '/';
        return $path;
    }

    protected static function redirect_verify($args = []) {
        $url = self::build_url(self::VERIFY_PAGE, $args);
        wp_safe_redirect($url);
        exit;
    }

    protected static function redirect_after_verify($args = []) {
        $url = self::build_url(self::AFTER_VERIFY, $args);
        wp_safe_redirect($url);
        exit;
    }

    protected static function build_url($path, $args = []) {
        $base = home_url($path);
        if (!empty($args) && is_array($args)) {
            // Remove empty args
            foreach ($args as $k => $v) {
                if ($v === null || $v === '') unset($args[$k]);
            }
            $base = add_query_arg($args, $base);
        }
        return $base;
    }
}

}
