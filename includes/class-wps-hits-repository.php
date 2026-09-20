<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * $wpdb wrapper for wp_wps_hits (writes only — reads live in
 * WPS_Rollup_Repository since milestone 7, except get_visitors_total()
 * below, which genuinely needs raw visitor-level data) and wp_wps_presence
 * (writes + the real-time "online now" reads, which by nature can't be
 * precomputed).
 */
class WPS_Hits_Repository {

    public static function insert_hit( array $data ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'wps_hits',
            array(
                'object_type'   => $data['object_type'],
                'object_id'     => $data['object_id'] ? $data['object_id'] : null,
                'visitor_token' => $data['visitor_token'],
                'channel'       => $data['channel'],
                'utm_source'    => '' !== $data['utm_source'] ? $data['utm_source'] : null,
                'utm_medium'    => '' !== $data['utm_medium'] ? $data['utm_medium'] : null,
                'utm_campaign'  => '' !== $data['utm_campaign'] ? $data['utm_campaign'] : null,
                'referrer_host' => '' !== $data['referrer_host'] ? $data['referrer_host'] : null,
                'request_path'  => ! empty( $data['request_path'] ) ? $data['request_path'] : null,
                'device_type'   => $data['device_type'],
                'ip_hash'       => $data['ip_hash'],
                'hit_type'      => $data['hit_type'],
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public static function upsert_presence( $visitor_token, $object_id, $channel ) {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'wps_presence',
            array(
                'visitor_token' => $visitor_token,
                'last_seen'     => current_time( 'mysql' ),
                'object_id'     => $object_id ? $object_id : null,
                'channel'       => $channel,
            ),
            array( '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * "Online now" = presence rows seen in the last 5 minutes (plan §1).
     * Prunes rows older than 30 minutes first — self-cleaning table, no
     * dedicated prune cron.
     */
    public static function get_online_count() {
        global $wpdb;

        self::prune_stale_presence();

        $table = $wpdb->prefix . 'wps_presence';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE last_seen >= %s",
            self::minutes_ago( 5 )
        ) );
    }

    /** @return array<object{object_id:?int,channel:string,last_seen:string}> */
    public static function get_active_presence( $limit = 20 ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_presence';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT object_id, channel, last_seen FROM $table WHERE last_seen >= %s ORDER BY last_seen DESC LIMIT %d",
            self::minutes_ago( 5 ),
            $limit
        ) );
    }

    /**
     * Unique visitors across the whole range (not per-day/per-page — a
     * distinct metric from "views"). Deliberately NOT served from the
     * rollup: wp_wps_daily_stats only keeps per-day/object/channel counts,
     * not visitor identities, so true cross-day uniqueness requires the raw
     * table. Cheap regardless — a single COUNT(DISTINCT visitor_token) using
     * the visitor_lookup index, not the heavier multi-dimension queries the
     * rollup exists to replace.
     */
    public static function get_visitors_total( $start, $end ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_token) FROM $table
             WHERE hit_type = 'view' AND created_at BETWEEN %s AND %s",
            $start,
            $end
        ) );
    }

    /** Same "raw, not rollup" reasoning as get_visitors_total() — per-channel unique visitors, for the Sources page. */
    public static function get_visitors_by_channel( $start, $end ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(DISTINCT visitor_token) AS visitors FROM $table
             WHERE hit_type = 'view' AND created_at BETWEEN %s AND %s
             GROUP BY channel",
            $start,
            $end
        ) );
    }

    /**
     * device_type is captured per-hit but never made it into the daily
     * rollup (wp_wps_daily_stats has no device dimension), so — same as
     * visitors — this reads raw data, bounded by the retention window.
     * Counts distinct visitors per device (not raw hits), so one heavy
     * mobile visitor with many pageviews can't skew the device share.
     */
    public static function get_device_breakdown( $start, $end ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT device_type, COUNT(DISTINCT visitor_token) AS visitors FROM $table
             WHERE hit_type = 'view' AND created_at BETWEEN %s AND %s
             GROUP BY device_type",
            $start,
            $end
        ) );
    }

    /** @return array<object{request_path:string,hits:int}> ordered by hits desc — which broken URLs to fix first. */
    public static function get_404_breakdown( $start, $end, $limit = 50 ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT request_path, COUNT(*) AS hits FROM $table
             WHERE object_type = '404' AND hit_type = 'view' AND created_at BETWEEN %s AND %s
             GROUP BY request_path
             ORDER BY hits DESC
             LIMIT %d",
            $start,
            $end,
            $limit
        ) );
    }

    /** Distinct visitors who added something to cart, per channel — funnel stage 2 (WPS_Cart_Tracker writes these). */
    public static function get_add_to_cart_by_channel( $start, $end ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(DISTINCT visitor_token) AS visitors FROM $table
             WHERE hit_type = 'add_to_cart' AND created_at BETWEEN %s AND %s
             GROUP BY channel",
            $start,
            $end
        ) );
    }

    /**
     * Funnel stage 3 ("checkout reached") needs no new tracking — the
     * checkout page is a normal tracked page view, so this just filters the
     * existing view data down to that one page.
     */
    public static function get_checkout_reached_by_channel( $start, $end, $checkout_page_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_hits';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(DISTINCT visitor_token) AS visitors FROM $table
             WHERE hit_type = 'view' AND object_type = 'page' AND object_id = %d AND created_at BETWEEN %s AND %s
             GROUP BY channel",
            $checkout_page_id,
            $start,
            $end
        ) );
    }

    private static function prune_stale_presence() {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_presence';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table WHERE last_seen < %s",
            self::minutes_ago( 30 )
        ) );
    }

    /**
     * current_time( 'timestamp' ) is already shifted to the site's local
     * time (per wp_timezone()), so gmdate() here avoids re-applying the
     * server's own OS timezone on top of that — same convention as
     * current_time( 'mysql' ), which is what wrote last_seen in the first place.
     */
    private static function minutes_ago( $minutes ) {
        return gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $minutes * MINUTE_IN_SECONDS ) );
    }
}
