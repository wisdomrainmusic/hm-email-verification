<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('HM_EV_Compat')) {

class HM_EV_Compat {

    public static function init() {
        // Disable Woo "Customer New Account" email (welcome / account created)
        add_filter('woocommerce_email_enabled_customer_new_account', [__CLASS__, 'disable_customer_new_account'], 10, 2);

        // Extra safety: ensure recipient is empty in edge cases
        add_filter('woocommerce_email_recipient_customer_new_account', [__CLASS__, 'empty_recipient'], 10, 2);
    }

    public static function disable_customer_new_account($enabled, $email_obj) {
        return false;
    }

    public static function empty_recipient($recipient, $user) {
        return '';
    }
}

}
