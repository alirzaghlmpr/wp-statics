<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Product_Metabox {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        add_meta_box(
            'wps_product_stats',
            'آمار محصول (آمار سایت)',
            array( __CLASS__, 'render' ),
            'product',
            'side',
            'high'
        );
    }

    public static function render( $post ) {
        $product_id = $post->ID;

        // Fixed 30-day window (no picker here — this is a compact sidebar
        // summary; the full date-range comparison lives on the Products
        // dashboard tab).
        $end_date   = current_time( 'Y-m-d' );
        $start_date = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 29 * DAY_IN_SECONDS ) );

        $views         = WPS_Rollup_Repository::get_views_total_for_object( 'product', $product_id, $start_date, $end_date );
        $view_channels = WPS_Rollup_Repository::get_channel_breakdown_for_object( 'product', $product_id, $start_date, $end_date );
        $sales         = WPS_Sales_Repository::get_total_for_product( $product_id, $start_date, $end_date );
        $sale_channels = WPS_Sales_Repository::get_channel_breakdown_for_product( $product_id, $start_date, $end_date );

        echo '<p class="description">۳۰ روز اخیر</p>';

        echo '<p style="margin-bottom:4px;"><strong>بازدید کل:</strong> ' . esc_html( $views ) . '</p>';
        self::render_view_channels( $view_channels );

        echo '<p style="margin-bottom:4px;"><strong>فروش کل:</strong> ' . esc_html( $sales ) . '</p>';
        self::render_sale_channels( $sale_channels );

        if ( $views > 0 ) {
            $rate = round( ( $sales / $views ) * 100, 1 );
            echo '<p><strong>نرخ تبدیل:</strong> ' . esc_html( $rate ) . '٪</p>';
        }
    }

    private static function render_view_channels( $rows ) {
        if ( empty( $rows ) ) {
            echo '<p class="description">بازدیدی ثبت نشده است.</p>';
            return;
        }

        echo '<ul style="margin-top:0;">';
        foreach ( $rows as $row ) {
            printf(
                '<li>%1$s: %2$s</li>',
                esc_html( WPS_Source_Classifier::get_label( $row->channel ) ),
                esc_html( $row->views )
            );
        }
        echo '</ul>';
    }

    private static function render_sale_channels( $rows ) {
        if ( empty( $rows ) ) {
            echo '<p class="description">فروشی ثبت نشده است.</p>';
            return;
        }

        echo '<ul style="margin-top:0;">';
        foreach ( $rows as $row ) {
            printf(
                '<li>%1$s: %2$s</li>',
                esc_html( WPS_Source_Classifier::get_label( $row->channel ) ),
                esc_html( $row->qty )
            );
        }
        echo '</ul>';
    }
}
