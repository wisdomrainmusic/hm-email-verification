<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('HM_EV_I18n')) {

class HM_EV_I18n {

    public static function t($key, $lang = null, $vars = []) {
        $key = (string)$key;

        if ($lang === null) {
            if (class_exists('HM_EV_Lang')) {
                $lang = HM_EV_Lang::get_lang();
            } else {
                $lang = 'en';
            }
        }

        if (class_exists('HM_EV_Lang')) {
            $lang = HM_EV_Lang::normalize($lang);
        } else {
            $lang = strtolower((string)$lang);
            if ($lang === '') $lang = 'en';
        }

        $dict = self::load_lang($lang);
        $en   = ($lang === 'en') ? $dict : self::load_lang('en');

        $text = '';
        if (is_array($dict) && array_key_exists($key, $dict)) {
            $text = (string)$dict[$key];
        } elseif (is_array($en) && array_key_exists($key, $en)) {
            $text = (string)$en[$key];
        } else {
            $text = $key;
        }

        if (!empty($vars) && is_array($vars)) {
            foreach ($vars as $vk => $vv) {
                $text = str_replace('{' . $vk . '}', (string)$vv, $text);
            }
        }

        return $text;
    }

    protected static function load_lang($lang) {
        $lang = strtolower((string)$lang);
        $file = HM_EV_PATH . 'languages/' . $lang . '.php';
        if (!file_exists($file)) return [];
        $data = include $file;
        return is_array($data) ? $data : [];
    }
}

}
