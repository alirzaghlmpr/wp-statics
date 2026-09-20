<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Most-viewed posts/pages report — the list-page counterpart to
 * WPS_Content_Metabox. Combines both post types into one table (sorted by
 * views desc) with a "نوع" column distinguishing them, plus a compact
 * per-channel breakdown so the source split is visible without opening
 * each item individually.
 */
class WPS_Admin_Pages_Page {

    private static $TYPE_LABELS = array(
        'post' => 'نوشته',
        'page' => 'برگه',
    );

    public static function render() {
        $range     = WPS_Date_Range::resolve();
        $rows      = self::build_rows( $range );
        $max_views = ! empty( $rows ) ? $rows[0]['views'] : 0;

        WPS_Admin_UI::page_start( array(
            'title'      => 'صفحات پربازدید',
            'range'      => $range,
            'slug'       => 'wp-statics-pages',
            'export_url' => WPS_CSV_Export::export_url( 'pages', $range ),
        ) );

        WPS_Date_Range::render_rollup_lag_notice( $range );
        ?>
        <div class="wps-panel">
            <div class="wps-table-wrap">
                <table class="wps-table">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>نوع</th>
                            <th class="is-num">بازدید</th>
                            <th>منابع</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <?php WPS_Admin_UI::empty_row( 4 ); ?>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $row['object_id'] ) ); ?>"><?php echo esc_html( get_the_title( $row['object_id'] ) ); ?></a></td>
                                <td><span class="wps-tag"><?php echo esc_html( self::$TYPE_LABELS[ $row['object_type'] ] ); ?></span></td>
                                <td class="is-num"><?php echo WPS_Admin_UI::cell_bar( $row['views'], $max_views ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></td>
                                <td><?php self::render_channels( $row['channels'] ); ?></td>
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

    /** Top three channels as coloured chips (+N for the rest) over a share bar of all of them. */
    private static function render_channels( array $channel_rows ) {
        if ( empty( $channel_rows ) ) {
            echo '—';
            return;
        }

        $shown     = array_slice( $channel_rows, 0, 3 );
        $remaining = count( $channel_rows ) - count( $shown );
        $items     = array();

        foreach ( $channel_rows as $channel_row ) {
            $items[] = array( 'slug' => $channel_row->channel, 'value' => (int) $channel_row->views );
        }
        ?>
        <div class="wps-chans">
            <div class="wps-chans__list">
                <?php foreach ( $shown as $channel_row ) : ?>
                    <?php echo WPS_Admin_UI::channel_chip( $channel_row->channel, (int) $channel_row->views ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
                <?php endforeach; ?>
                <?php if ( $remaining > 0 ) : ?>
                    <span class="wps-chan__n">+<?php echo esc_html( $remaining ); ?></span>
                <?php endif; ?>
            </div>
            <?php echo WPS_Admin_UI::share_bar( $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
        </div>
        <?php
    }

    /**
     * @return array<int, array{object_id:int, object_type:string, views:int, channels:array}> sorted by views desc.
     */
    public static function build_rows( array $range ) {
        $rows = array();

        foreach ( array_keys( self::$TYPE_LABELS ) as $object_type ) {
            $views_by_object    = array();
            $channels_by_object = array();

            foreach ( WPS_Rollup_Repository::get_views_by_object( $object_type, $range['start_date'], $range['end_date'] ) as $row ) {
                $views_by_object[ (int) $row->object_id ] = (int) $row->views;
            }

            foreach ( WPS_Rollup_Repository::get_object_channel_breakdown( $object_type, $range['start_date'], $range['end_date'] ) as $row ) {
                $channels_by_object[ (int) $row->object_id ][] = $row;
            }

            foreach ( $views_by_object as $object_id => $views ) {
                $rows[] = array(
                    'object_id'   => $object_id,
                    'object_type' => $object_type,
                    'views'       => $views,
                    'channels'    => isset( $channels_by_object[ $object_id ] ) ? $channels_by_object[ $object_id ] : array(),
                );
            }
        }

        usort( $rows, function ( $a, $b ) {
            return $b['views'] <=> $a['views'];
        } );

        return $rows;
    }
}
