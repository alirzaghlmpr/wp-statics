<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSV export for the Sources and Products reports, via admin-post.php.
 * Re-runs the same repository queries those pages already use (rather than
 * refactoring their inline render() logic into a shared "get rows" step) —
 * keeps this self-contained and low-risk; the pages already query
 * repositories directly with no shared abstraction to hook into.
 */
class WPS_CSV_Export {

    const NONCE_ACTION = 'wps_export_csv';

    public static function init() {
        add_action( 'admin_post_wps_export_csv', array( __CLASS__, 'handle' ) );
    }

    public static function handle() {
        check_admin_referer( self::NONCE_ACTION );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }

        $report = isset( $_GET['report'] ) ? sanitize_key( $_GET['report'] ) : '';
        $range  = WPS_Date_Range::resolve();

        if ( 'products' === $report ) {
            self::export_products( $range );
        } elseif ( 'pages' === $report ) {
            self::export_pages( $range );
        } else {
            self::export_sources( $range );
        }
    }

    public static function export_url( $report, array $range ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action'    => 'wps_export_csv',
                    'report'    => $report,
                    'wps_range' => $range['range'],
                    'wps_start' => $range['start_date'],
                    'wps_end'   => $range['end_date'],
                ),
                admin_url( 'admin-post.php' )
            ),
            self::NONCE_ACTION
        );
    }

    private static function export_sources( array $range ) {
        $views_by_channel    = self::index( WPS_Rollup_Repository::get_channel_breakdown( $range['start_date'], $range['end_date'] ), 'views' );
        $visitors_by_channel = self::index( WPS_Hits_Repository::get_visitors_by_channel( $range['start'], $range['end'] ), 'visitors' );
        $sales_by_channel    = array();
        $revenue_by_channel  = array();

        foreach ( WPS_Sales_Repository::get_sales_by_channel( $range['start_date'], $range['end_date'] ) as $row ) {
            $sales_by_channel[ $row->channel ]   = (int) $row->qty;
            $revenue_by_channel[ $row->channel ] = (float) $row->revenue;
        }

        $channels = array_unique( array_merge(
            array_keys( $views_by_channel ),
            array_keys( $visitors_by_channel ),
            array_keys( $sales_by_channel )
        ) );

        $rows = array();
        foreach ( $channels as $channel ) {
            $views    = isset( $views_by_channel[ $channel ] ) ? $views_by_channel[ $channel ] : 0;
            $visitors = isset( $visitors_by_channel[ $channel ] ) ? $visitors_by_channel[ $channel ] : 0;
            $qty      = isset( $sales_by_channel[ $channel ] ) ? $sales_by_channel[ $channel ] : 0;
            $revenue  = isset( $revenue_by_channel[ $channel ] ) ? $revenue_by_channel[ $channel ] : 0.0;
            $aov      = $qty > 0 ? round( $revenue / $qty, 2 ) : 0;
            $rate     = $views > 0 ? round( ( $qty / $views ) * 100, 1 ) : 0;

            $rows[] = array( WPS_Source_Classifier::get_label( $channel ), $views, $visitors, $qty, round( $revenue, 2 ), $aov, $rate );
        }

        self::stream_csv(
            'wp-statics-sources-' . $range['start_date'] . '-to-' . $range['end_date'] . '.csv',
            array( 'Channel', 'Views', 'Visitors', 'Sales', 'Revenue', 'AOV', 'Conversion Rate (%)' ),
            $rows
        );
    }

    private static function export_products( array $range ) {
        $sales_by_product   = array();
        $revenue_by_product = array();

        foreach ( WPS_Sales_Repository::get_sales_by_product( $range['start_date'], $range['end_date'] ) as $row ) {
            $sales_by_product[ (int) $row->product_id ]   = (int) $row->qty;
            $revenue_by_product[ (int) $row->product_id ] = (float) $row->revenue;
        }

        $views_by_product = array();
        foreach ( WPS_Rollup_Repository::get_views_by_object( 'product', $range['start_date'], $range['end_date'] ) as $row ) {
            $views_by_product[ (int) $row->object_id ] = (int) $row->views;
        }

        $product_ids = array_unique( array_merge( array_keys( $sales_by_product ), array_keys( $views_by_product ) ) );

        $rows = array();
        foreach ( $product_ids as $product_id ) {
            $views   = isset( $views_by_product[ $product_id ] ) ? $views_by_product[ $product_id ] : 0;
            $qty     = isset( $sales_by_product[ $product_id ] ) ? $sales_by_product[ $product_id ] : 0;
            $revenue = isset( $revenue_by_product[ $product_id ] ) ? $revenue_by_product[ $product_id ] : 0.0;
            $rate    = $views > 0 ? round( ( $qty / $views ) * 100, 1 ) : 0;

            $rows[] = array( get_the_title( $product_id ), $views, $qty, round( $revenue, 2 ), $rate );
        }

        self::stream_csv(
            'wp-statics-products-' . $range['start_date'] . '-to-' . $range['end_date'] . '.csv',
            array( 'Product', 'Views', 'Sales', 'Revenue', 'Conversion Rate (%)' ),
            $rows
        );
    }

    /** Reuses WPS_Admin_Pages_Page's row-building so the CSV and the on-screen table can never drift apart. */
    private static function export_pages( array $range ) {
        $type_labels = array( 'post' => 'Post', 'page' => 'Page' );

        $rows = array_map( function ( $row ) use ( $type_labels ) {
            return array(
                get_the_title( $row['object_id'] ),
                $type_labels[ $row['object_type'] ],
                $row['views'],
                WPS_Source_Classifier::format_breakdown_summary( $row['channels'], 10 ),
            );
        }, WPS_Admin_Pages_Page::build_rows( $range ) );

        self::stream_csv(
            'wp-statics-pages-' . $range['start_date'] . '-to-' . $range['end_date'] . '.csv',
            array( 'Title', 'Type', 'Views', 'Sources' ),
            $rows
        );
    }

    /** @return array<string,int> */
    private static function index( array $wpdb_rows, $value_field ) {
        $indexed = array();

        foreach ( $wpdb_rows as $row ) {
            $indexed[ $row->channel ] = (int) $row->{$value_field};
        }

        return $indexed;
    }

    private static function stream_csv( $filename, array $header, array $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );

        // UTF-8 BOM so Excel opens Persian channel/product names correctly
        // instead of mojibake — Excel doesn't assume UTF-8 without it.
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, $header );

        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }

        fclose( $out );
        exit;
    }
}
