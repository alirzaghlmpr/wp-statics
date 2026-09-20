<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Daily job pruning raw wp_wps_hits older than the configured retention
 * window (default 35 days, Settings-configurable — plan §1). Only the raw
 * table is pruned; wp_wps_daily_stats (the rollup) keeps history indefinitely.
 */
class WPS_Retention_Cron {

    const HOOK          = 'wps_retention_cron';
    const DEFAULT_DAYS  = 35;

    public static function init() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK );
        }
    }

    public static function run() {
        global $wpdb;

        $settings = get_option( WPS_Activator::SETTINGS_OPTION, array() );
        $days     = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : self::DEFAULT_DAYS;
        $days     = max( 1, $days );

        $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );
        $table  = $wpdb->prefix . 'wps_hits';

        $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $cutoff ) );
    }
}
