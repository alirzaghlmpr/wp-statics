<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lists which nonexistent URLs visitors are actually hitting, most-hit
 * first — useful for a store to catch broken links from ads/marketplaces
 * pointing at an old product slug. Reads raw wp_wps_hits directly (like
 * "visitors"/"device breakdown" — 404 data has no rollup table of its own,
 * bounded by the retention window, which is fine for this use case).
 */
class WPS_Admin_404_Page {

    public static function render() {
        $range = WPS_Date_Range::resolve();
        $rows  = WPS_Hits_Repository::get_404_breakdown( $range['start'], $range['end'] );
        $max_hits = ! empty( $rows ) ? max( array_map( function ( $row ) {
            return (int) $row->hits;
        }, $rows ) ) : 0;

        WPS_Admin_UI::page_start( array(
            'title'       => 'صفحات یافت نشد (۴۰۴)',
            'description' => 'آدرس‌هایی که بازدیدکنندگان به آن‌ها مراجعه کرده‌اند ولی صفحه‌ای برایشان وجود ندارد — معمولاً لینک شکسته از تبلیغات، مارکت‌پلیس‌ها یا تغییر آدرس محصول.',
            'range'       => $range,
            'slug'        => 'wp-statics-404s',
        ) );
        ?>
        <div class="wps-panel">
            <div class="wps-table-wrap">
                <table class="wps-table">
                    <thead>
                        <tr>
                            <th>آدرس</th>
                            <th class="is-num">تعداد بازدید</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <?php WPS_Admin_UI::empty_row( 2, 'در این بازه هیچ صفحه یافت‌نشده‌ای ثبت نشده است.', '' ); ?>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $row->request_path ); ?></code></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( (int) $row->hits, $max_hits ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
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
