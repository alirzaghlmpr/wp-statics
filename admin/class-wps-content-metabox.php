<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Views + per-channel breakdown for regular posts/pages, in the edit-screen
 * sidebar — same idea as WPS_Product_Metabox, minus the sales/conversion
 * data products have and posts/pages don't. Registered on both post types:
 * "pages" in the everyday sense (any content the user browses) covers
 * blog posts too, not just the literal WordPress "Page" post type.
 */
class WPS_Content_Metabox {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        foreach ( array( 'post', 'page' ) as $post_type ) {
            add_meta_box(
                'wps_content_stats',
                'آمار بازدید (آمار سایت)',
                array( __CLASS__, 'render' ),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public static function render( $post ) {
        $object_type = ( 'page' === $post->post_type ) ? 'page' : 'post';
        $object_id   = $post->ID;

        // Fixed 30-day window, matching the product metabox — full
        // date-range comparison lives on the "صفحات پربازدید" report page.
        $end_date   = current_time( 'Y-m-d' );
        $start_date = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 29 * DAY_IN_SECONDS ) );

        $views    = WPS_Rollup_Repository::get_views_total_for_object( $object_type, $object_id, $start_date, $end_date );
        $channels = WPS_Rollup_Repository::get_channel_breakdown_for_object( $object_type, $object_id, $start_date, $end_date );

        echo '<p class="description">۳۰ روز اخیر</p>';
        echo '<p style="margin-bottom:4px;"><strong>بازدید کل:</strong> ' . esc_html( $views ) . '</p>';

        if ( empty( $channels ) ) {
            echo '<p class="description">بازدیدی ثبت نشده است.</p>';
            return;
        }

        echo '<ul style="margin-top:0;">';
        foreach ( $channels as $row ) {
            printf(
                '<li>%1$s: %2$s</li>',
                esc_html( WPS_Source_Classifier::get_label( $row->channel ) ),
                esc_html( $row->views )
            );
        }
        echo '</ul>';
    }
}
