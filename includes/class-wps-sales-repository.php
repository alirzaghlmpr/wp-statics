<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * $wpdb wrapper for wp_wps_sales_daily. Written to by WPS_Order_Sales_Recorder
 * on the woocommerce_order_status_changed hook (event-driven, not cron —
 * plan §1); read by the product metabox and the Products dashboard tab.
 */
class WPS_Sales_Repository {

    /**
     * order_product is the UNIQUE key — a duplicate call (e.g. a second
     * status transition slipping past the _wps_sales_recorded guard) is a
     * no-op rather than double-counting. This is the DB-level safety net
     * described in plan §1; the primary guard is the order-meta flag.
     */
    public static function record_sale( array $data ) {
        global $wpdb;

        // line_total is formatted to a fixed-point string in PHP first,
        // rather than passed through %wpdb->prepare()'s %f placeholder,
        // because %f runs through sprintf() using the process's current
        // LC_NUMERIC locale — under a locale where the decimal separator
        // isn't '.', %f would emit e.g. "12,50" and silently corrupt or
        // truncate the value. %s with a pre-formatted string sidesteps that.
        $line_total = number_format( (float) $data['line_total'], 4, '.', '' );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}wps_sales_daily
                (stat_date, product_id, channel, order_id, qty, line_total)
             VALUES (%s, %d, %s, %d, %d, %s)
             ON DUPLICATE KEY UPDATE qty = qty",
            $data['stat_date'],
            $data['product_id'],
            $data['channel'],
            $data['order_id'],
            $data['qty'],
            $line_total
        ) );
    }

    public static function get_total_for_product( $product_id, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(qty), 0) FROM $table WHERE product_id = %d AND stat_date BETWEEN %s AND %s",
            $product_id,
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{channel:string,qty:int,revenue:float}> for one product, ordered by qty desc. */
    public static function get_channel_breakdown_for_product( $product_id, $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(qty) AS qty, SUM(line_total) AS revenue
             FROM $table
             WHERE product_id = %d AND stat_date BETWEEN %s AND %s
             GROUP BY channel
             ORDER BY qty DESC",
            $product_id,
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{product_id:int,qty:int,revenue:float}> across all products — used by the Products dashboard tab. */
    public static function get_sales_by_product( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT product_id, SUM(qty) AS qty, SUM(line_total) AS revenue
             FROM $table
             WHERE stat_date BETWEEN %s AND %s
             GROUP BY product_id",
            $start_date,
            $end_date
        ) );
    }

    /** @return array<object{channel:string,qty:int,revenue:float}> across all products — used by the Sources dashboard tab. */
    public static function get_sales_by_channel( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(qty) AS qty, SUM(line_total) AS revenue
             FROM $table
             WHERE stat_date BETWEEN %s AND %s
             GROUP BY channel",
            $start_date,
            $end_date
        ) );
    }

    /**
     * Distinct orders per channel — funnel stage 4 ("purchase"). Deliberately
     * order count, not SUM(qty): a single order with 3 units shouldn't count
     * as 3 "purchases" when comparing against stage-1/2/3 visitor counts.
     */
    public static function get_orders_by_channel( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, COUNT(DISTINCT order_id) AS orders
             FROM $table
             WHERE stat_date BETWEEN %s AND %s
             GROUP BY channel",
            $start_date,
            $end_date
        ) );
    }
}
