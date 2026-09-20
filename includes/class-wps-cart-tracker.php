<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Records an 'add_to_cart' hit on WooCommerce's own add-to-cart action —
 * this is a server-side PHP hook, unlike view/heartbeat which are JS-beacon
 * driven, since add-to-cart already fires a real page load or AJAX request
 * we can hook directly (no separate beacon needed).
 *
 * Completes the funnel this enables: view (product page) -> add_to_cart
 * (this) -> checkout reached (already captured — the checkout page is a
 * normal tracked page view) -> purchase (already captured in
 * wp_wps_sales_daily). Only this one stage needed new tracking.
 */
class WPS_Cart_Tracker {

    public static function init() {
        add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'track' ), 10, 2 );
    }

    public static function track( $cart_item_key, $product_id ) {
        $settings = get_option( WPS_Activator::SETTINGS_OPTION, array() );
        if ( ! empty( $settings['exclude_admins'] ) && current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ( WPS_Bot_Filter::is_bot( $user_agent ) ) {
            return;
        }

        $ip            = WPS_Visitor_Identity::get_client_ip();
        $visitor_token = WPS_Visitor_Identity::get_token( $ip, $user_agent );

        WPS_Hits_Repository::insert_hit( array(
            'object_type'   => 'product',
            'object_id'     => $product_id,
            'visitor_token' => $visitor_token,
            'channel'       => self::get_recent_channel( $visitor_token ),
            'utm_source'    => '',
            'utm_medium'    => '',
            'utm_campaign'  => '',
            'referrer_host' => '',
            'request_path'  => '',
            'device_type'   => WPS_Visitor_Identity::get_device_type( $user_agent ),
            'ip_hash'       => WPS_Visitor_Identity::get_ip_hash( $ip ),
            'hit_type'      => 'add_to_cart',
        ) );
    }

    /**
     * There's no "current channel" at add-to-cart time the way there is for
     * a page view (no referrer to classify — this is a form submit/AJAX
     * call, not a fresh navigation). Attribute it to whatever channel this
     * visitor's most recent tracked page view carried instead, falling back
     * to 'direct' if none is found (e.g. tracking was just enabled).
     */
    private static function get_recent_channel( $visitor_token ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        $channel = $wpdb->get_var( $wpdb->prepare(
            "SELECT channel FROM $table
             WHERE visitor_token = %s AND hit_type = 'view'
             ORDER BY created_at DESC
             LIMIT 1",
            $visitor_token
        ) );

        return $channel ? $channel : 'direct';
    }
}
