<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues the tracking beacon. Only a small, cache-safe data blob
 * (object type/id, REST URL) is embedded in the page — the actual write
 * happens via a JS-fired request to WPS_Tracking_Endpoint, never during
 * template rendering, so this stays safe under a page cache (plan §3).
 */
class WPS_Frontend {

    const HEARTBEAT_INTERVAL_MS = 60000;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ) );
    }

    public static function enqueue_tracker() {
        $settings = get_option( WPS_Activator::SETTINGS_OPTION, array() );

        if ( ! empty( $settings['exclude_admins'] ) && current_user_can( 'manage_options' ) ) {
            return;
        }

        list( $object_type, $object_id ) = self::get_current_object();

        wp_enqueue_script(
            'wps-tracker',
            WPS_PLUGIN_URL . 'assets/js/wps-tracker.js',
            array(),
            WPS_VERSION,
            true
        );

        $data = array(
            'restUrl'           => rest_url( 'wps/v1/track' ),
            // Sent as a ?_wpnonce= query param (not a header) so it survives
            // navigator.sendBeacon(), which can't set custom headers. See
            // rest_cookie_check_errors() — it reads _wpnonce from $_REQUEST.
            'nonce'             => wp_create_nonce( 'wp_rest' ),
            'objectType'        => $object_type,
            'objectId'          => $object_id,
            'heartbeatInterval' => self::HEARTBEAT_INTERVAL_MS,
        );

        // The requested path is inherent to the URL, not the visitor, so
        // baking it into this localized data stays cache-safe (a full-page
        // cache already varies by URL) — unlike document.referrer, which
        // genuinely differs per visitor and must stay client-side (§3).
        if ( '404' === $object_type ) {
            $data['requestPath'] = self::get_request_path();
        }

        wp_localize_script( 'wps-tracker', 'wpsTrackerData', $data );
    }

    private static function get_current_object() {
        if ( is_404() ) {
            return array( '404', 0 );
        }

        if ( is_singular( 'product' ) ) {
            return array( 'product', get_queried_object_id() );
        }

        if ( is_singular( 'post' ) ) {
            return array( 'post', get_queried_object_id() );
        }

        if ( is_page() ) {
            return array( 'page', get_queried_object_id() );
        }

        return array( 'other', 0 );
    }

    private static function get_request_path() {
        $path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = strtok( $path, '?' ); // drop the query string — the path is what identifies a broken link

        return substr( sanitize_text_field( $path ), 0, 500 );
    }
}
