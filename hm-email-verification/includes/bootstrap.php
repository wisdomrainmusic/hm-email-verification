<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('hm_ev_bootstrap')) {
    function hm_ev_bootstrap() {

        // Lang (Commit 3)
        $lang = HM_EV_PATH . 'includes/class-hm-ev-lang.php';
        if (file_exists($lang)) require_once $lang;

        // I18n (Commit 4)
        $i18n = HM_EV_PATH . 'includes/class-hm-ev-i18n.php';
        if (file_exists($i18n)) require_once $i18n;

        // Core (Commit 2)
        $core = HM_EV_PATH . 'includes/class-hm-ev-core.php';
        if (file_exists($core)) require_once $core;

        if (class_exists('HM_EV_Core')) {
            HM_EV_Core::init();
        }
    }
}

hm_ev_bootstrap();
