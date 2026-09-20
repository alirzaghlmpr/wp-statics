<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Channel-level report (plan §5's "Sources" page, deferred out of the MVP
 * milestones and built now as part of the revenue/AOV-by-source backlog
 * item — that data needs a home, and this is the page the plan always
 * intended for it). Not WooCommerce-gated in the admin menu, unlike
 * Products: views/visitors by channel are meaningful with or without WC;
 * sales/revenue/AOV/conversion just read 0 without it.
 */
class WPS_Admin_Sources_Page {

    public static function render() {
        $range = WPS_Date_Range::resolve();

        $views_by_channel    = self::index_by_channel( WPS_Rollup_Repository::get_channel_breakdown( $range['start_date'], $range['end_date'] ), 'views' );
        $visitors_by_channel = self::index_by_channel( WPS_Hits_Repository::get_visitors_by_channel( $range['start'], $range['end'] ), 'visitors' );
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

        usort( $channels, function ( $a, $b ) use ( $views_by_channel ) {
            $va = isset( $views_by_channel[ $a ] ) ? $views_by_channel[ $a ] : 0;
            $vb = isset( $views_by_channel[ $b ] ) ? $views_by_channel[ $b ] : 0;
            return $vb <=> $va;
        } );

        $total_views = array_sum( $views_by_channel );
        $max_views   = $total_views > 0 ? max( $views_by_channel ) : 0;

        $share_items = array();
        foreach ( $channels as $channel ) {
            if ( ! empty( $views_by_channel[ $channel ] ) ) {
                $share_items[] = array( 'slug' => $channel, 'value' => $views_by_channel[ $channel ] );
            }
        }

        WPS_Admin_UI::page_start( array(
            'title'      => 'منابع بازدید',
            'range'      => $range,
            'slug'       => 'wp-statics-sources',
            'export_url' => WPS_CSV_Export::export_url( 'sources', $range ),
        ) );

        WPS_Date_Range::render_rollup_lag_notice( $range );
        ?>

        <?php if ( $total_views > 0 ) : ?>
            <div class="wps-panel">
                <div class="wps-panel__head"><h2>سهم هر منبع از بازدید</h2></div>
                <div class="wps-panel__body">
                    <?php echo WPS_Admin_UI::share_bar( $share_items, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                    <div class="wps-legend">
                        <?php foreach ( $share_items as $item ) : ?>
                            <span>
                                <?php echo WPS_Admin_UI::channel_chip( $item['slug'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                                <span class="wps-chan__n"><?php echo esc_html( round( ( $item['value'] / $total_views ) * 100, 1 ) ); ?>٪</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="wps-panel wps-section">
            <div class="wps-table-wrap">
                <table class="wps-table">
                    <thead>
                        <tr>
                            <th>منبع</th>
                            <th class="is-num">بازدید</th>
                            <th class="is-num">بازدیدکننده</th>
                            <th class="is-num">فروش</th>
                            <th class="is-num">درآمد</th>
                            <th class="is-num">میانگین ارزش سفارش</th>
                            <th class="is-num">نرخ تبدیل</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $channels ) ) : ?>
                        <?php WPS_Admin_UI::empty_row( 7 ); ?>
                    <?php else : ?>
                        <?php foreach ( $channels as $channel ) :
                            $views    = isset( $views_by_channel[ $channel ] ) ? $views_by_channel[ $channel ] : 0;
                            $visitors = isset( $visitors_by_channel[ $channel ] ) ? $visitors_by_channel[ $channel ] : 0;
                            $qty      = isset( $sales_by_channel[ $channel ] ) ? $sales_by_channel[ $channel ] : 0;
                            $revenue  = isset( $revenue_by_channel[ $channel ] ) ? $revenue_by_channel[ $channel ] : 0.0;
                            $aov      = $qty > 0 ? $revenue / $qty : 0;
                            $rate     = $views > 0 ? round( ( $qty / $views ) * 100, 1 ) : 0;
                            ?>
                            <tr>
                                <td><?php echo WPS_Admin_UI::channel_chip( $channel ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $views, $max_views, WPS_Admin_UI::channel_color( $channel ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td class="is-num"><?php echo esc_html( WPS_Admin_UI::number( $visitors ) ); ?></td>
                                <td class="is-num"><?php echo esc_html( WPS_Admin_UI::number( $qty ) ); ?></td>
                                <td class="is-num"><?php echo esc_html( number_format_i18n( $revenue, 0 ) ); ?></td>
                                <td class="is-num"><?php echo esc_html( number_format_i18n( $aov, 0 ) ); ?></td>
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
    private static function index_by_channel( array $rows, $value_field ) {
        $indexed = array();

        foreach ( $rows as $row ) {
            $indexed[ $row->channel ] = (int) $row->{$value_field};
        }

        return $indexed;
    }
}
