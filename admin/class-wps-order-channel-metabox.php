<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manual channel override on the order edit screen — closes the gap where
 * WooCommerce's native attribution has nothing useful (staff/admin-created
 * orders, e.g. a sale phoned in or DM'd on Instagram) (plan §0/§4).
 */
class WPS_Order_Channel_Metabox {

    // Must match WPS_Order_Attribution_Reader::OVERRIDE_META_KEY. Kept as a
    // plain string rather than a cross-class constant reference so this
    // file has no load-order dependency on that one.
    const META_KEY   = '_wps_channel_override';
    const FIELD_NAME = 'wps_channel_override';

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ) );
    }

    public static function register() {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $screen = function_exists( 'wc_get_page_screen_id' )
            ? wc_get_page_screen_id( 'shop-order' )
            : array( 'shop_order', 'woocommerce_page_wc-orders' );

        add_meta_box(
            'wps_order_channel',
            'منبع فروش (آمار سایت)',
            array( __CLASS__, 'render' ),
            $screen,
            'side',
            'default'
        );
    }

    public static function render( $post_or_order ) {
        $order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );

        if ( ! $order ) {
            echo '<p>سفارش یافت نشد.</p>';
            return;
        }

        $detected = WPS_Order_Attribution_Reader::get_channel( $order );
        $override = $order->get_meta( self::META_KEY );

        echo '<p><strong>منبع شناسایی‌شده:</strong> ' . esc_html( WPS_Source_Classifier::get_label( $detected ) ) . '</p>';
        echo '<p class="description">اگر این سفارش به‌صورت دستی ثبت شده (مثلاً پیام اینستاگرام یا تماس تلفنی) و منبع واقعی را می‌دانید، از لیست زیر انتخاب کنید:</p>';

        echo '<select name="' . esc_attr( self::FIELD_NAME ) . '" style="width:100%;">';
        echo '<option value="">— بدون تغییر (تشخیص خودکار) —</option>';

        foreach ( WPS_Source_Classifier::get_all_labels() as $slug => $label ) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr( $slug ),
                selected( $override, $slug, false ),
                esc_html( $label )
            );
        }

        echo '</select>';
    }

    /**
     * Hooked to woocommerce_process_shop_order_meta, which already runs
     * after WooCommerce's own nonce check for both the legacy (post.php)
     * and HPOS (Edit::save(), which calls check_admin_referer() before
     * firing this action) save flows — see
     * src/Internal/Admin/Orders/Edit.php and includes/admin/class-wc-admin-meta-boxes.php.
     */
    public static function save( $order_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! isset( $_POST[ self::FIELD_NAME ] ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $value          = sanitize_key( wp_unslash( $_POST[ self::FIELD_NAME ] ) );
        $valid_channels = array_keys( WPS_Source_Classifier::get_all_labels() );

        if ( '' === $value ) {
            $order->delete_meta_data( self::META_KEY );
        } elseif ( in_array( $value, $valid_channels, true ) ) {
            $order->update_meta_data( self::META_KEY, $value );
        } else {
            return;
        }

        $order->save();
    }
}
