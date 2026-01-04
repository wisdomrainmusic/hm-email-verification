<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('hm_ev_bootstrap')) {
    function hm_ev_bootstrap() {

        // I18n
        $i18n = HM_EV_PATH . 'includes/class-hm-ev-i18n.php';
        if (file_exists($i18n)) require_once $i18n;

        // Core
        $core = HM_EV_PATH . 'includes/class-hm-ev-core.php';
        if (file_exists($core)) require_once $core;

        // Shortcode (Commit 5)
        $sc = HM_EV_PATH . 'includes/class-hm-ev-shortcode.php';
        if (file_exists($sc)) require_once $sc;

        // Compat
        $compat = HM_EV_PATH . 'includes/class-hm-ev-compat.php';
        if (file_exists($compat)) require_once $compat;

        if (class_exists('HM_EV_Compat')) {
            HM_EV_Compat::init();
        }

        if (class_exists('HM_EV_Core')) {
            HM_EV_Core::init();
        }

        if (class_exists('HM_EV_Shortcode')) {
            HM_EV_Shortcode::init();
        }
    }
}

hm_ev_bootstrap();
