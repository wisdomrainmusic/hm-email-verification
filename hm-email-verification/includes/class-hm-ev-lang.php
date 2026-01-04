<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('HM_EV_Lang')) {

class HM_EV_Lang {

    /**
     * Allowed languages (exact list per plan)
     */
    public static function allowed_langs() {
        return [
            'tr','en','de','fr','es','it','pt','nl','pl','cs','sk','hu','ro','bg','el','sv','no','da','fi','is',
            'et','lv','lt','sl','hr','sr','bs','mk','sq','uk','ru','be','ga','cy','eu','ca','gl','mt','lb','ka',
            'hy','az','ar','fa','ku','zh',
        ];
    }

    public static function normalize($lang) {
        $lang = strtolower(trim((string)$lang));
        if ($lang === '') return 'en';

        // Handle locales like de_DE, tr_TR, de-DE
        // Keep only the first segment before '_' or '-'
        if (strpos($lang, '_') !== false) {
            $lang = explode('_', $lang)[0];
        }
        if (strpos($lang, '-') !== false) {
            $lang = explode('-', $lang)[0];
        }

        // Now keep only letters
        $lang = preg_replace('~[^a-z]~', '', $lang);
        if ($lang === '') return 'en';

        // If it's longer than 2, try first 2 chars (safety)
        if (strlen($lang) > 2) {
            $maybe = substr($lang, 0, 2);
            if (in_array($maybe, self::allowed_langs(), true)) return $maybe;
        }

        if (!in_array($lang, self::allowed_langs(), true)) return 'en';
        return $lang;
    }

    /**
     * Get current language.
     * Priority:
     * 0) Explicit query override (?lang=)
     * 1) HMPC (if present) via filters/actions detection
     * 2) Cookie (common HMPC cookie keys)
     * 3) URL prefix first segment (/de/..., /fr/...)
     * 4) locale fallback (de_DE -> de)
     * 5) fallback en
     */
    public static function get_lang() {
        // 0) explicit query override (rare but useful)
        if (!empty($_GET['lang'])) {
            return self::normalize(wp_unslash($_GET['lang']));
        }

        // 1) HMPC-first (best effort, non-fatal)
        $hmpc = self::get_lang_from_hmpc();
        if ($hmpc) return self::normalize($hmpc);

        // 2) Cookie-based language (HMPC often uses cookies)
        $cookie = self::get_lang_from_cookie();
        if ($cookie) return self::normalize($cookie);

        // 3) URL prefix
        $prefix = self::get_lang_from_url_prefix();
        if ($prefix) return self::normalize($prefix);

        // 4) Locale fallback (de_DE -> de, tr_TR -> tr, etc.)
        $loc = self::get_lang_from_locale();
        if ($loc) return self::normalize($loc);

        return 'en';
    }

    /**
     * Build a language-aware home URL for a path.
     * Example: home_url_lang('/email-verification/', 'de') => https://site.com/de/email-verification/
     */
    public static function home_url_lang($path, $lang = null) {
        $lang = $lang ? self::normalize($lang) : self::get_lang();
        $path = '/' . ltrim((string)$path, '/');

        // Ensure path starts and ends correctly (keep trailing slash if provided)
        // If $path already contains /{lang}/ prefix, don't double-prefix.
        $trimmed = ltrim($path, '/');
        $first = strtok($trimmed, '/');
        if ($first && self::is_lang($first)) {
            return home_url($path);
        }

        // Prefix language
        $prefixed = '/' . $lang . $path;
        return home_url($prefixed);
    }

    public static function is_lang($code) {
        $code = strtolower((string)$code);
        return in_array($code, self::allowed_langs(), true);
    }

    /* ======================
     * Internals
     * ====================== */

    protected static function get_lang_from_url_prefix() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return null;

        $parts = explode('/', trim($path, '/'));
        if (empty($parts[0])) return null;

        $first = strtolower($parts[0]);
        if (self::is_lang($first)) return $first;

        return null;
    }

    /**
     * HMPC detection:
     * We'll try a few safe filters if present. If none exist, return null.
     *
     * NOTE: We don't hard-depend on HMPC. This is best-effort.
     */
    protected static function get_lang_from_hmpc() {
        // Common patterns (best effort). If HMPC defines one of these filters, it will return a language code.
        $candidates = [
            'hmpc_current_lang',
            'hmpc_get_current_lang',
            'hmpcv2_current_lang',
            'hm_pro_ceviri_current_lang',
        ];

        foreach ($candidates as $tag) {
            if (has_filter($tag)) {
                $val = apply_filters($tag, '');
                if (is_string($val) && $val !== '') return $val;
            }
        }

        return null;
    }

    protected static function get_lang_from_cookie() {
        if (empty($_COOKIE) || !is_array($_COOKIE)) return null;

        // Most common candidates for language cookie keys
        $key_candidates = [
            'hmpc_lang',
            'hmpcv2_lang',
            'hm_lang',
            'hm_language',
            'site_lang',
            'current_lang',
            'lang',
        ];

        // 1) direct key hits
        foreach ($key_candidates as $k) {
            if (!empty($_COOKIE[$k])) {
                $val = wp_unslash($_COOKIE[$k]);
                if (is_string($val) && $val !== '') return $val;
            }
        }

        // 2) heuristic scan: any cookie name containing "lang" with allowed value
        foreach ($_COOKIE as $k => $v) {
            $k = strtolower((string)$k);
            if (strpos($k, 'lang') === false) continue;

            $val = is_array($v) ? '' : (string)wp_unslash($v);
            $val = strtolower(trim($val));

            // Sometimes cookie stores locale like de_DE
            if (strpos($val, '_') !== false) {
                $val = explode('_', $val)[0];
            }

            if (self::is_lang($val)) return $val;
        }

        return null;
    }

    protected static function get_lang_from_locale() {
        $locale = function_exists('get_locale') ? get_locale() : '';
        if (!is_string($locale) || $locale === '') return null;

        $locale = strtolower($locale);

        // de_de -> de, tr_tr -> tr
        if (strpos($locale, '_') !== false) {
            $parts = explode('_', $locale);
            if (!empty($parts[0])) return $parts[0];
        }

        // fallback
        if (strlen($locale) >= 2) return substr($locale, 0, 2);

        return null;
    }
}

}
