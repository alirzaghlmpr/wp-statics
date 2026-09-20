<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Admin_Products_Page {

    public static function render() {
        $range = WPS_Date_Range::resolve();

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

        usort( $product_ids, function ( $a, $b ) use ( $views_by_product ) {
            $va = isset( $views_by_product[ $a ] ) ? $views_by_product[ $a ] : 0;
            $vb = isset( $views_by_product[ $b ] ) ? $views_by_product[ $b ] : 0;
            return $vb <=> $va;
        } );
        $max_views = ! empty( $views_by_product ) ? max( $views_by_product ) : 0;

        WPS_Admin_UI::page_start( array(
            'title'      => 'محصولات',
            'range'      => $range,
            'slug'       => 'wp-statics-products',
            'export_url' => WPS_CSV_Export::export_url( 'products', $range ),
        ) );

        WPS_Date_Range::render_rollup_lag_notice( $range );
        ?>
        <div class="wps-panel">
            <div class="wps-table-wrap">
                <table class="wps-table">
                    <thead>
                        <tr>
                            <th>محصول</th>
                            <th class="is-num">بازدید</th>
                            <th class="is-num">فروش</th>
                            <th class="is-num">درآمد</th>
                            <th class="is-num">نرخ تبدیل</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $product_ids ) ) : ?>
                        <?php WPS_Admin_UI::empty_row( 5 ); ?>
                    <?php else : ?>
                        <?php foreach ( $product_ids as $product_id ) :
                            $views   = isset( $views_by_product[ $product_id ] ) ? $views_by_product[ $product_id ] : 0;
                            $qty     = isset( $sales_by_product[ $product_id ] ) ? $sales_by_product[ $product_id ] : 0;
                            $revenue = isset( $revenue_by_product[ $product_id ] ) ? $revenue_by_product[ $product_id ] : 0.0;
                            $rate    = $views > 0 ? round( ( $qty / $views ) * 100, 1 ) : 0;
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $views, $max_views ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo esc_html( WPS_Admin_UI::number( $qty ) ); ?></td>
                                <td class="is-num"><?php echo esc_html( number_format_i18n( $revenue, 0 ) ); ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::rate_pill( $rate ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        WPS_Admin_UI::page_end();
    }
}
