<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reads wp_wps_daily_stats — the fast path for the Overview/Products pages
 * and the product metabox, now that WPS_Rollup_Cron populates it (plan §7
 * milestone 7). Numerically equivalent to the raw-hit queries these replace
 * (WPS_Hits_Repository's old get_views_total()/get_channel_breakdown()/etc.,
 * removed in this milestone), by construction: daily_stats.views is exactly
 * COUNT(DISTINCT visitor_token) per (day, object, channel).
 *
 * Caveat worth knowing: the rollup only recomputes on the hourly cron tick,
 * so a date range including *today* can lag live raw data by up to ~1 hour
 * until the next tick — same tradeoff every rollup-based analytics tool
 * makes. Admin pages surface a note when this applies.
 */
class WPS_Rollup_Repository {

    public static function get_views_total( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(views), 0) FROM $table WHERE stat_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{channel:string,views:int}> ordered by views desc. */
    public static function get_channel_breakdown( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(views) AS views FROM $table
             WHERE stat_date BETWEEN %s AND %s
             GROUP BY channel
             ORDER BY views DESC",
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{channel:string,views:int}> restricted to one object_type — used by the funnel report (product views only). */
    public static function get_channel_breakdown_for_type( $object_type, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(views) AS views FROM $table
             WHERE object_type = %s AND stat_date BETWEEN %s AND %s
             GROUP BY channel
             ORDER BY views DESC",
            $object_type,
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{stat_date:string,views:int}> ordered by date asc. */
    public static function get_daily_views( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT stat_date, SUM(views) AS views FROM $table
             WHERE stat_date BETWEEN %s AND %s
             GROUP BY stat_date
             ORDER BY stat_date ASC",
            $start_date,
            $end_date
        ) );
    }

    public static function get_views_total_for_object( $object_type, $object_id, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(views), 0) FROM $table
             WHERE object_type = %s AND object_id = %d AND stat_date BETWEEN %s AND %s",
            $object_type,
            $object_id,
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{channel:string,views:int}> for one object, ordered by views desc. */
    public static function get_channel_breakdown_for_object( $object_type, $object_id, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(views) AS views FROM $table
             WHERE object_type = %s AND object_id = %d AND stat_date BETWEEN %s AND %s
             GROUP BY channel
             ORDER BY views DESC",
            $object_type,
            $object_id,
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{object_id:int,views:int}> across all objects of one type — used by the Products dashboard tab. */
    public static function get_views_by_object( $object_type, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT object_id, SUM(views) AS views FROM $table
             WHERE object_type = %s AND object_id != 0 AND stat_date BETWEEN %s AND %s
             GROUP BY object_id
             ORDER BY views DESC",
            $object_type,
            $start_date,
            $end_date
        ) );
    }

    /**
     * Per-object channel breakdown for ALL objects of one type in a single
     * query — used by the Pages report, which needs "views by channel" for
     * potentially many posts/pages at once. One aggregate query + grouping
     * in PHP, rather than calling get_channel_breakdown_for_object() once
     * per row (which would be an N+1 query per page load).
     *
     * @return array<object{object_id:int,channel:string,views:int}> ordered by object_id, then views desc.
     */
    public static function get_object_channel_breakdown( $object_type, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_daily_stats';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT object_id, channel, SUM(views) AS views FROM $table
             WHERE object_type = %s AND object_id != 0 AND stat_date BETWEEN %s AND %s
             GROUP BY object_id, channel
             ORDER BY object_id, views DESC",
            $object_type,
            $start_date,
            $end_date
        ) );
    }
}
