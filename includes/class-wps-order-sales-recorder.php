<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Writes wp_wps_sales_daily directly on the paid-status transition — event-
 * driven, not a cron scan (plan §1: order volume is low enough this is
 * simpler and always current). Idempotent via the _wps_sales_recorded order
 * meta flag, with the sales table's UNIQUE(order_id, product_id) as a
 * DB-level backstop against double-counting on repeated status transitions
 * (e.g. processing -> completed both being "paid").
 *
 * Refunds/cancellations after recording are out of scope for this MVP —
 * revenue-by-source refinement is Phase 2 (plan §8).
 */
class WPS_Order_Sales_Recorder {

    const RECORDED_META_KEY = '_wps_sales_recorded';

    public static function init() {
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_record' ), 10, 3 );
    }

    /**
     * Only $order_id is used — the hook's 4th "$order" argument is a
     * WP_Post for legacy (non-HPOS) orders but a WC_Order for HPOS ones,
     * so resolving via wc_get_order( $order_id ) is the one form that's
     * reliable under both.
     */
    public static function maybe_record( $order_id, $from_status, $to_status ) {
        if ( ! in_array( $to_status, wc_get_is_paid_statuses(), true ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( self::RECORDED_META_KEY ) ) {
            return;
        }

        $channel   = WPS_Order_Attribution_Reader::get_channel( $order );
        $stat_date = self::get_paid_date( $order );

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $product_id = $item->get_product_id();
            if ( ! $product_id ) {
                continue;
            }

            WPS_Sales_Repository::record_sale( array(
                'stat_date'  => $stat_date,
                'product_id' => $product_id,
                'channel'    => $channel,
                'order_id'   => $order->get_id(),
                'qty'        => $item->get_quantity(),
                'line_total' => $item->get_total(),
            ) );
        }

        $order->update_meta_data( self::RECORDED_META_KEY, 1 );
        $order->save();
    }

    private static function get_paid_date( WC_Order $order ) {
        $date = $order->get_date_paid();

        if ( ! $date ) {
            $date = $order->get_date_created();
        }

        return $date ? $date->date( 'Y-m-d' ) : current_time( 'Y-m-d' );
    }
}
