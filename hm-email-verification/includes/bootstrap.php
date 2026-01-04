<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bootstrap loader
 * - Keep all requires and initialization in one place
 */

require_once HM_EV_PATH . 'includes/class-hm-ev-router.php';

/**
 * Initialize plugin pieces.
 */
function hm_ev_bootstrap() {
    HM_EV_Router::init();
}

hm_ev_bootstrap();
