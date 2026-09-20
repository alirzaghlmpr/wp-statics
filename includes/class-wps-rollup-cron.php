<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hourly job that rolls raw wp_wps_hits into wp_wps_daily_stats (plan §1).
 * Recomputes today AND yesterday each run — yesterday as a safety net for a
 * missed run or a late-arriving hit near midnight — via a full delete+
 * reinsert per date rather than incremental updates, which avoids
 * counter-drift and is cheap at this row count.
 */
class WPS_Rollup_Cron {

    const HOOK = 'wps_rollup_cron';

    public static function init() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK );
        }
    }

    public static function run() {
        $today     = current_time( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS );

        self::rollup_date( $yesterday );
        self::rollup_date( $today );
    }

    private static function rollup_date( $date ) {
        global $wpdb;

        $hits_table  = $wpdb->prefix . 'wps_hits';
        $stats_table = $wpdb->prefix . 'wps_daily_stats';

        $range_start = $date . ' 00:00:00';
        $range_end   = gmdate( 'Y-m-d', strtotime( $date ) + DAY_IN_SECONDS ) . ' 00:00:00';

        $wpdb->query( $wpdb->prepare( "DELETE FROM $stats_table WHERE stat_date = %s", $date ) );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $stats_table (stat_date, object_type, object_id, channel, views)
             SELECT %s, object_type, COALESCE(object_id, 0), channel, COUNT(DISTINCT visitor_token)
             FROM $hits_table
             WHERE hit_type = 'view' AND created_at >= %s AND created_at < %s
             GROUP BY object_type, COALESCE(object_id, 0), channel",
            $date,
            $range_start,
            $range_end
        ) );
    }
}
