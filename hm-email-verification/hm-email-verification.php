<?php
/**
 * Plugin Name: HM Email Verification
 * Description: Email verification engine with language-aware redirects (HMPC-compatible).
 * Version: 0.1.0
 * Author: HM
 * Text Domain: hm-email-verification
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Constants (guarded)
 */
if ( ! defined( 'HM_EV_VERSION' ) ) {
    define( 'HM_EV_VERSION', '0.1.0' );
}

if ( ! defined( 'HM_EV_PATH' ) ) {
    define( 'HM_EV_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'HM_EV_URL' ) ) {
    define( 'HM_EV_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Activation hook
 * - Flush rewrite rules after registering them
 */
register_activation_hook( __FILE__, function () {
    $bootstrap = HM_EV_PATH . 'includes/bootstrap.php';

    if ( file_exists( $bootstrap ) ) {
        require_once $bootstrap;
    }

    if ( class_exists( 'HM_EV_Router' ) ) {
        HM_EV_Router::register_rewrites();
    }

    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Load order
 * - Use plugins_loaded with low-ish priority to avoid "too early" issues
 * - Keep everything inside includes/bootstrap.php
 */
add_action( 'plugins_loaded', function () {
    $bootstrap = HM_EV_PATH . 'includes/bootstrap.php';
    if ( file_exists( $bootstrap ) ) {
        require_once $bootstrap;
    }
}, 5 );
