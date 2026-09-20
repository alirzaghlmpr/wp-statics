<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'wps_hits',
    $wpdb->prefix . 'wps_daily_stats',
    $wpdb->prefix . 'wps_sales_daily',
    $wpdb->prefix . 'wps_presence',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is a fixed, code-controlled constant, not user input.
}

delete_option( 'wps_db_version' );
delete_option( 'wps_ip_salt' );
delete_option( 'wps_settings' );

wp_clear_scheduled_hook( 'wps_rollup_cron' );
wp_clear_scheduled_hook( 'wps_retention_cron' );
