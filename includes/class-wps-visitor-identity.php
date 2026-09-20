<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cookieless visitor identity (plan §3). visitor_token rotates daily and
 * folds in the UA, so it can't be used to track a visitor across days —
 * that's the intended privacy property, not an oversight. ip_hash is a
 * separate, non-rotating hash used only as a rate-limiting key.
 */
class WPS_Visitor_Identity {

    /**
     * Same visitor + same UA + same calendar day => same token (free
     * per-day view dedup). Known tradeoff: visitors sharing both IP and an
     * identical UA string (NAT'd network, generic mobile UA) collapse into
     * one token — acceptable for MVP's reporting goals.
     */
    public static function get_token( $ip, $user_agent ) {
        $salt = self::get_salt();
        $raw  = $ip . '|' . $user_agent . '|' . gmdate( 'Y-m-d' ) . '|' . $salt;

        return substr( hash_hmac( 'sha256', $raw, $salt ), 0, 32 );
    }

    public static function get_ip_hash( $ip ) {
        return hash_hmac( 'sha256', (string) $ip, self::get_salt() );
    }

    public static function get_device_type( $user_agent ) {
        $ua = (string) $user_agent;

        if ( preg_match( '/tablet|ipad/i', $ua ) ) {
            return 'tablet';
        }

        if ( preg_match( '/mobile|iphone|android/i', $ua ) ) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * REMOTE_ADDR only — X-Forwarded-For is trivially spoofable and would
     * let a caller fake distinct visitors and dodge the rate limiter.
     * Filterable for a future reverse-proxy setup.
     */
    public static function get_client_ip() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';

        return apply_filters( 'wps_visitor_ip', $ip );
    }

    private static function get_salt() {
        $salt = get_option( WPS_Activator::IP_SALT_OPTION );

        if ( ! $salt ) {
            $salt = wp_generate_password( 32, false );
            update_option( WPS_Activator::IP_SALT_OPTION, $salt );
        }

        return $salt;
    }
}
