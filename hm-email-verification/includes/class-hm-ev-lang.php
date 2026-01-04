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
        $lang = preg_replace('~[^a-z]~', '', $lang);
        if ($lang === '') return 'en';
        if (!in_array($lang, self::allowed_langs(), true)) return 'en';
        return $lang;
    }

    /**
     * Get current language.
     * Priority:
     * 1) HMPC (if present) via filters/actions detection
     * 2) URL prefix first segment (/de/..., /fr/...)
     * 3) fallback en
     */
    public static function get_lang() {
        // 1) HMPC-first (best effort, non-fatal)
        $hmpc = self::get_lang_from_hmpc();
        if ($hmpc) return self::normalize($hmpc);

        // 2) URL prefix
        $prefix = self::get_lang_from_url_prefix();
        if ($prefix) return self::normalize($prefix);

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
}

}
