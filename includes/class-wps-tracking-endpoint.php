<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * POST /wp-json/wps/v1/track — the only write path into wp_wps_hits.
 * Must be public (permission_callback __return_true: anonymous visitors have
 * no auth), so abuse resistance comes from layered cheap checks instead
 * (plan §3): bot UA filtering, per-IP rate limiting, same-site origin check,
 * and an object-existence sanity check.
 */
class WPS_Tracking_Endpoint {

    const RATE_LIMIT_MAX    = 30; // requests per IP per window
    const RATE_LIMIT_WINDOW = 60; // seconds

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            'wps/v1',
            '/track',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function handle( WP_REST_Request $request ) {
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

        // Silently no-op (204) rather than error for bot/rate-limit/exclusion
        // cases — nothing legitimate is retrying on failure here, and a 4xx
        // would just be noise in server logs for expected, routine skips.
        if ( WPS_Bot_Filter::is_bot( $user_agent ) ) {
            return new WP_REST_Response( null, 204 );
        }

        if ( ! self::is_same_site_request() ) {
            return new WP_REST_Response( null, 403 );
        }

        $ip      = WPS_Visitor_Identity::get_client_ip();
        $ip_hash = WPS_Visitor_Identity::get_ip_hash( $ip );

        if ( self::is_rate_limited( $ip_hash ) ) {
            return new WP_REST_Response( null, 429 );
        }

        $settings = get_option( WPS_Activator::SETTINGS_OPTION, array() );
        if ( ! empty( $settings['exclude_admins'] ) && current_user_can( 'manage_options' ) ) {
            return new WP_REST_Response( null, 204 );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = $request->get_params();
        }

        $type        = isset( $params['type'] ) && 'heartbeat' === $params['type'] ? 'heartbeat' : 'view';
        $object_type = isset( $params['object_type'] ) ? sanitize_key( $params['object_type'] ) : 'other';
        $object_id   = isset( $params['object_id'] ) ? absint( $params['object_id'] ) : 0;
        $referrer    = isset( $params['referrer'] ) ? esc_url_raw( $params['referrer'] ) : '';

        if ( ! in_array( $object_type, array( 'post', 'page', 'product', '404', 'other' ), true ) ) {
            $object_type = 'other';
        }

        // A forged object_id pointing at a nonexistent/unpublished post is
        // the one input worth rejecting outright rather than just coercing.
        // Must check for 'publish' specifically, not just post existence —
        // otherwise a crafted request could record views against draft,
        // private, or trashed post IDs, leaking their existence and
        // polluting stats for content that was never publicly viewable.
        if ( $object_id && 'publish' !== get_post_status( $object_id ) ) {
            return new WP_REST_Response( null, 400 );
        }

        $utm_source   = isset( $params['utm_source'] ) ? sanitize_text_field( $params['utm_source'] ) : '';
        $utm_medium   = isset( $params['utm_medium'] ) ? sanitize_text_field( $params['utm_medium'] ) : '';
        $utm_campaign = isset( $params['utm_campaign'] ) ? sanitize_text_field( $params['utm_campaign'] ) : '';

        // Only meaningful (and only trusted) for '404' hits — object_id is
        // always 0 for those, so there's no risk of it overriding real
        // post-keyed data for any other object_type.
        $request_path = ( '404' === $object_type && isset( $params['request_path'] ) )
            ? substr( sanitize_text_field( $params['request_path'] ), 0, 500 )
            : '';

        $channel       = WPS_Source_Classifier::classify( array( 'utm_source' => $utm_source ), $referrer );
        $referrer_host = $referrer ? (string) wp_parse_url( $referrer, PHP_URL_HOST ) : '';

        $visitor_token = WPS_Visitor_Identity::get_token( $ip, $user_agent );

        WPS_Hits_Repository::insert_hit( array(
            'object_type'   => $object_type,
            'object_id'     => $object_id,
            'visitor_token' => $visitor_token,
            'channel'       => $channel,
            'utm_source'    => $utm_source,
            'utm_medium'    => $utm_medium,
            'utm_campaign'  => $utm_campaign,
            'referrer_host' => $referrer_host,
            'request_path'  => $request_path,
            'device_type'   => WPS_Visitor_Identity::get_device_type( $user_agent ),
            'ip_hash'       => $ip_hash,
            'hit_type'      => $type,
        ) );

        WPS_Hits_Repository::upsert_presence( $visitor_token, $object_id, $channel );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    private static function is_rate_limited( $ip_hash ) {
        $key   = 'wps_rl_' . $ip_hash;
        $count = (int) get_transient( $key );

        if ( $count >= self::RATE_LIMIT_MAX ) {
            return true;
        }

        set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

        return false;
    }

    /**
     * Blocks trivial cross-site POST spam at the endpoint itself. This is
     * distinct from the 'referrer' field in the payload (document.referrer,
     * i.e. how the visitor arrived) — this checks the Origin/Referer of the
     * tracking request itself, which browsers set to the current page.
     */
    private static function is_same_site_request() {
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );

        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';
        if ( $origin ) {
            return wp_parse_url( $origin, PHP_URL_HOST ) === $site_host;
        }

        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        if ( $referer ) {
            return wp_parse_url( $referer, PHP_URL_HOST ) === $site_host;
        }

        // Neither header present — uncommon for a browser-fired POST; allow
        // rather than risk false-rejecting a legitimate first-party beacon.
        return true;
    }
}
