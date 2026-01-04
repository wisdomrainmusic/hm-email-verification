<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('hm_ev_bootstrap')) {
    function hm_ev_bootstrap() {

        // Core (Commit 2)
        $core = HM_EV_PATH . 'includes/class-hm-ev-core.php';
        if (file_exists($core)) {
            require_once $core;
        }

        if (class_exists('HM_EV_Core')) {
            HM_EV_Core::init();
        }
    }
}

hm_ev_bootstrap();
