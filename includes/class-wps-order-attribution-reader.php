<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reads WooCommerce's native order attribution (verified against
 * woocommerce/src/Internal/Traits/OrderAttributionMeta.php — meta keys are
 * "_wc_order_attribution_{field}", source_type is one of typein/organic/
 * referral/utm/mobile_app/admin/pos) and normalizes it into a wp-statics
 * channel via the shared WPS_Source_Classifier. Does NOT reimplement
 * attribution — WC already does last-non-direct-touch (plan §0).
 */
class WPS_Order_Attribution_Reader {

    const OVERRIDE_META_KEY = '_wps_channel_override';

    /**
     * source_type values meaning WC's native attribution ran but there is no
     * real channel to report: the customer typed the URL directly, or the
     * order was created by staff/POS with no browser tracking possible
     * (plan §0's gap — closed for these by the manual override metabox).
     */
    const DIRECT_SOURCE_TYPES = array( 'typein', 'admin', 'pos' );

    public static function get_channel( WC_Order $order ) {
        $override = $order->get_meta( self::OVERRIDE_META_KEY );
        if ( $override ) {
            return $override;
        }

        $source_type = (string) $order->get_meta( '_wc_order_attribution_source_type' );

        if ( '' !== $source_type ) {
            if ( in_array( $source_type, self::DIRECT_SOURCE_TYPES, true ) ) {
                return 'direct';
            }

            return WPS_Source_Classifier::classify(
                array( 'utm_source' => (string) $order->get_meta( '_wc_order_attribution_utm_source' ) ),
                (string) $order->get_meta( '_wc_order_attribution_referrer' )
            );
        }

        // WooCommerce's native attribution meta is entirely absent (order
        // predates the feature, or JS tracking was blocked/skipped) — fall
        // back to the legacy meta keys bale-woocommerce-notifier's
        // get_order_source() already reads. Whatever value they hold is
        // used directly as the source signal; re-querying the (also empty)
        // native _wc_order_attribution_* fields here would just discard it.
        $legacy_source = self::get_legacy_source( $order );

        if ( '' === $legacy_source ) {
            return 'direct';
        }

        return WPS_Source_Classifier::classify( array( 'utm_source' => $legacy_source ), '' );
    }

    private static function get_legacy_source( WC_Order $order ) {
        $value = (string) $order->get_meta( '_order_source' );

        if ( '' === $value ) {
            $value = (string) $order->get_meta( '_utm_source' );
        }

        return $value;
    }
}
