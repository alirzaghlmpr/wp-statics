<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Deactivator {

    public static function deactivate() {
        // Safe to call even before these are scheduled (added in a later milestone).
        wp_clear_scheduled_hook( 'wps_rollup_cron' );
        wp_clear_scheduled_hook( 'wps_retention_cron' );
    }
}
