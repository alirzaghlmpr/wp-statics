<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product view -> add to cart -> checkout reached -> purchase, per channel.
 * WooCommerce-only (gated in the menu, like Products) — every stage here
 * depends on WC data.
 */
class WPS_Admin_Funnel_Page {

    public static function render() {
        $range = WPS_Date_Range::resolve();

        $views = self::index( WPS_Rollup_Repository::get_channel_breakdown_for_type( 'product', $range['start_date'], $range['end_date'] ), 'views' );
        $carts = self::index( WPS_Hits_Repository::get_add_to_cart_by_channel( $range['start'], $range['end'] ), 'visitors' );

        $checkout_page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
        $checkouts         = $checkout_page_id > 0
            ? self::index( WPS_Hits_Repository::get_checkout_reached_by_channel( $range['start'], $range['end'], $checkout_page_id ), 'visitors' )
            : array();

        $orders = self::index( WPS_Sales_Repository::get_orders_by_channel( $range['start_date'], $range['end_date'] ), 'orders' );

        $channels = array_unique( array_merge(
            array_keys( $views ),
            array_keys( $carts ),
            array_keys( $checkouts ),
            array_keys( $orders )
        ) );

        usort( $channels, function ( $a, $b ) use ( $views ) {
            $va = isset( $views[ $a ] ) ? $views[ $a ] : 0;
            $vb = isset( $views[ $b ] ) ? $views[ $b ] : 0;
            return $vb <=> $va;
        } );
        WPS_Admin_UI::page_start( array(
            'title'       => 'قیف فروش',
            'description' => 'مسیر بازدید محصول تا خرید، به تفکیک منبع: بازدید محصول ← افزودن به سبد ← ورود به صفحه پرداخت ← خرید نهایی.',
            'range'       => $range,
            'slug'        => 'wp-statics-funnel',
        ) );

        WPS_Date_Range::render_rollup_lag_notice( $range );

        if ( ! $checkout_page_id ) :
            ?>
            <p class="wps-note wps-note--warn">صفحه پرداخت ووکامرس شناسایی نشد؛ ستون «ورود به پرداخت» خالی نمایش داده می‌شود.</p>
            <?php
        endif;
        ?>
        <div class="wps-panel">
            <div class="wps-table-wrap">
                <table class="wps-table">
                    <thead>
                        <tr>
                            <th>منبع</th>
                            <th class="is-num">بازدید محصول</th>
                            <th class="is-num">افزودن به سبد</th>
                            <th class="is-num">ورود به پرداخت</th>
                            <th class="is-num">خرید</th>
                            <th class="is-num">نرخ تبدیل کلی</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $channels ) ) : ?>
                        <?php WPS_Admin_UI::empty_row( 6 ); ?>
                    <?php else : ?>
                        <?php foreach ( $channels as $channel ) :
                            $v    = isset( $views[ $channel ] ) ? $views[ $channel ] : 0;
                            $c    = isset( $carts[ $channel ] ) ? $carts[ $channel ] : 0;
                            $co   = isset( $checkouts[ $channel ] ) ? $checkouts[ $channel ] : 0;
                            $o    = isset( $orders[ $channel ] ) ? $orders[ $channel ] : 0;
                            $rate = $v > 0 ? round( ( $o / $v ) * 100, 1 ) : 0;
                            // Bars are scaled to this row's largest stage so the drop-off between stages is visible at a glance.
                            $peak  = max( $v, $c, $co, $o );
                            $color = WPS_Admin_UI::channel_color( $channel );
                            ?>
                            <tr>
                                <td><?php echo WPS_Admin_UI::channel_chip( $channel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $v, $peak, $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $c, $peak, $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $co, $peak, $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $o, $peak, $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
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

    /** @return array<string,int> channel => value from a $wpdb->get_results() row set. */
    private static function index( array $rows, $value_field ) {
        $indexed = array();

        foreach ( $rows as $row ) {
            $indexed[ $row->channel ] = (int) $row->{$value_field};
        }

        return $indexed;
    }
}
