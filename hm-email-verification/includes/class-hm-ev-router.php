<?php
/**
 * Locale-aware routing for HM Email Verification.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'HM_EV_Router' ) ) {
    /**
     * Handles locale-aware rewrites and canonical redirects.
     */
    class HM_EV_Router {
        const QUERY_FLAG   = 'hm_ev_route';
        const QUERY_LOCALE = 'hm_ev_locale';
        const PAGE_SLUG    = 'email-verification';

        /**
         * Boot router hooks.
         */
        public static function init() {
            add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
            add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
            add_action( 'template_redirect', array( __CLASS__, 'redirect_to_canonical_locale' ) );
        }

        /**
         * Register locale-aware rewrite rules.
         */
        public static function register_rewrites() {
            $page_slug    = self::get_primary_page_slug();
            $locale_slugs = self::get_locale_slugs();

            if ( empty( $page_slug ) || empty( $locale_slugs ) ) {
                return;
            }

            foreach ( $locale_slugs as $locale => $slug ) {
                if ( empty( $slug ) ) {
                    continue;
                }

                $rule_pattern = '^' . preg_quote( trim( $slug, '/' ), '#' ) . '/?$';
                $query        = sprintf(
                    'index.php?pagename=%1$s&%2$s=1&%3$s=%4$s',
                    rawurlencode( $page_slug ),
                    rawurlencode( self::QUERY_FLAG ),
                    rawurlencode( self::QUERY_LOCALE ),
                    rawurlencode( $locale )
                );

                add_rewrite_rule( $rule_pattern, $query, 'top' );
            }
        }

        /**
         * Allow router-specific query variables.
         *
         * @param array $vars Query vars list.
         * @return array
         */
        public static function register_query_vars( $vars ) {
            $vars[] = self::QUERY_FLAG;
            $vars[] = self::QUERY_LOCALE;

            return $vars;
        }

        /**
         * Redirect mismatched slug requests to the canonical locale slug.
         */
        public static function redirect_to_canonical_locale() {
            if ( ! get_query_var( self::QUERY_FLAG ) ) {
                return;
            }

            $locale_slugs   = self::get_locale_slugs();
            $current_locale = self::get_current_locale();
            $canonical_slug = isset( $locale_slugs[ $current_locale ] ) ? $locale_slugs[ $current_locale ] : self::get_default_slug( $locale_slugs );

            if ( empty( $canonical_slug ) ) {
                return;
            }

            $current_slug = self::extract_slug_from_request();

            if ( $current_slug && $current_slug !== $canonical_slug ) {
                $destination = trailingslashit( home_url( '/' . ltrim( $canonical_slug, '/' ) ) );
                wp_safe_redirect( $destination, 302 );
                exit;
            }
        }

        /**
         * Get localized slugs map.
         *
         * @return array<string, string> Locale => slug mapping.
         */
        public static function get_locale_slugs() {
            $defaults = array(
                'en_US' => 'email-verification',
                'es_ES' => 'verificacion-email',
                'pt_BR' => 'verificacao-de-email',
            );

            $slugs = apply_filters( 'hm_ev_locale_slugs', $defaults );

            if ( empty( $slugs ) || ! is_array( $slugs ) ) {
                return $defaults;
            }

            return $slugs;
        }

        /**
         * Ensure a reasonable default slug exists for redirects.
         *
         * @param array $locale_slugs Locale => slug map.
         * @return string
         */
        protected static function get_default_slug( $locale_slugs ) {
            if ( isset( $locale_slugs['en_US'] ) ) {
                return $locale_slugs['en_US'];
            }

            $values = array_values( array_filter( $locale_slugs ) );

            return reset( $values );
        }

        /**
         * Primary page slug that hosts the verification content.
         *
         * @return string
         */
        protected static function get_primary_page_slug() {
            return apply_filters( 'hm_ev_page_slug', self::PAGE_SLUG );
        }

        /**
         * Extract slug from current request path.
         *
         * @return string|null
         */
        protected static function extract_slug_from_request() {
            $request_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
            $segments     = array_filter( explode( '/', $request_path ) );

            if ( empty( $segments ) ) {
                return null;
            }

            return end( $segments );
        }

        /**
         * Resolve the active locale with a safe fallback.
         *
         * @return string
         */
        protected static function get_current_locale() {
            if ( function_exists( 'determine_locale' ) ) {
                return determine_locale();
            }

            return get_locale();
        }
    }
}
