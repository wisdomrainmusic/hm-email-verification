<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('HM_EV_Shortcode')) {

class HM_EV_Shortcode {

    public static function init() {
        add_shortcode('hm_email_verification_notice', [__CLASS__, 'render']);
    }

    public static function render($atts = [], $content = '') {
        $lang = class_exists('HM_EV_Lang') ? HM_EV_Lang::get_lang() : 'en';

        // Status
        $v = isset($_GET['v']) ? sanitize_text_field(wp_unslash($_GET['v'])) : '';

        $msg_key = 'notice_verify_required';
        if ($v === 'sent')     $msg_key = 'notice_sent';
        if ($v === 'cooldown') $msg_key = 'notice_cooldown';
        if ($v === 'invalid')  $msg_key = 'notice_invalid';
        if ($v === 'notfound') $msg_key = 'notice_notfound';
        if ($v === 'check')    $msg_key = 'notice_verify_required';
        if ($v === 'already')  $msg_key = 'notice_verify_required'; // avoid new key

        $msg = class_exists('HM_EV_I18n') ? HM_EV_I18n::t($msg_key, $lang) : $msg_key;

        // Determine email (query param OR current user)
        $email = '';
        if (!empty($_GET['email'])) {
            $email = sanitize_email(wp_unslash($_GET['email']));
        } elseif (is_user_logged_in()) {
            $u = wp_get_current_user();
            if ($u && !empty($u->user_email)) $email = $u->user_email;
        }

        // Build resend URL (language-aware)
        if (class_exists('HM_EV_Lang')) {
            $base = HM_EV_Lang::home_url_lang(HM_EV_Core::VERIFY_PAGE, $lang);
        } else {
            $base = home_url(HM_EV_Core::VERIFY_PAGE);
        }

        $args = ['hm_resend_verify' => 1];
        if ($email && is_email($email)) {
            $args['email'] = $email;
        }

        $resend_url = add_query_arg($args, $base);

        $btn_label = class_exists('HM_EV_I18n') ? HM_EV_I18n::t('button_resend', $lang) : 'Resend verification email';

        // Woo notices wrapper if available
        $html = '';

        if (function_exists('wc_print_notice')) {
            // Use Woo notice styling
            ob_start();
            wc_print_notice($msg, 'notice');
            $notice_html = ob_get_clean();

            $html .= $notice_html;
            $html .= '<p style="margin-top:12px;">';
            $html .= '<a class="button" href="' . esc_url($resend_url) . '">' . esc_html($btn_label) . '</a>';
            $html .= '</p>';
            return $html;
        }

        // Fallback simple markup
        $html .= '<div class="hm-ev-notice" style="padding:12px 16px;border:1px solid #e2e2e2;border-radius:8px;margin:12px 0;">';
        $html .= '<p style="margin:0 0 12px 0;">' . esc_html($msg) . '</p>';
        $html .= '<a class="button" href="' . esc_url($resend_url) . '">' . esc_html($btn_label) . '</a>';
        $html .= '</div>';

        return $html;
    }
}

}
