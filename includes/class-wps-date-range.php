<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared date-range resolver + picker markup for admin report pages
 * (Overview, Products, and Sources once it exists) — factored out once a
 * second page needed identical logic, to avoid the two drifting apart.
 */
class WPS_Date_Range {

    public static function resolve() {
        $range      = isset( $_GET['wps_range'] ) ? sanitize_key( $_GET['wps_range'] ) : '7d'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter, no state change.
        $now        = current_time( 'timestamp' );
        $today_date = gmdate( 'Y-m-d', $now );

        if ( 'today' === $range ) {
            $start_date = $today_date;
            $end_date   = $today_date;
        } elseif ( '30d' === $range ) {
            $start_date = gmdate( 'Y-m-d', $now - ( 29 * DAY_IN_SECONDS ) );
            $end_date   = $today_date;
        } elseif ( 'custom' === $range ) {
            $custom_start = isset( $_GET['wps_start'] ) ? sanitize_text_field( wp_unslash( $_GET['wps_start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $custom_end   = isset( $_GET['wps_end'] ) ? sanitize_text_field( wp_unslash( $_GET['wps_end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $start_date = ( $custom_start && false !== strtotime( $custom_start ) )
                ? gmdate( 'Y-m-d', strtotime( $custom_start ) )
                : gmdate( 'Y-m-d', $now - ( 6 * DAY_IN_SECONDS ) );

            $end_date = ( $custom_end && false !== strtotime( $custom_end ) )
                ? gmdate( 'Y-m-d', strtotime( $custom_end ) )
                : $today_date;

            if ( $start_date > $end_date ) {
                list( $start_date, $end_date ) = array( $end_date, $start_date );
            }
        } else {
            $range      = '7d';
            $start_date = gmdate( 'Y-m-d', $now - ( 6 * DAY_IN_SECONDS ) );
            $end_date   = $today_date;
        }

        return array(
            'range'      => $range,
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'start'      => $start_date . ' 00:00:00',
            'end'        => $end_date . ' 23:59:59',
        );
    }

    /** Whether the resolved range's end date is today — the rollup-lag caveat only applies then. */
    public static function includes_today( array $range ) {
        return $range['end_date'] >= gmdate( 'Y-m-d', current_time( 'timestamp' ) );
    }

    public static function render_rollup_lag_notice( array $range ) {
        if ( ! self::includes_today( $range ) ) {
            return;
        }
        ?>
        <p class="wps-note">آمار امروز هر ساعت به‌روزرسانی می‌شود؛ ممکن است بازدیدهای چند دقیقه اخیر هنوز نمایش داده نشوند.</p>
        <?php
    }

    /** Segmented range control (plain links, no auto-submitting select) + inline date inputs for a custom range. */
    public static function render_picker( array $range, $page_slug ) {
        $options = array(
            'today'  => 'امروز',
            '7d'     => '۷ روز اخیر',
            '30d'    => '۳۰ روز اخیر',
            'custom' => 'بازه دلخواه',
        );
        ?>
        <div class="wps-range-wrap">
            <nav class="wps-range" aria-label="بازه زمانی">
                <?php foreach ( $options as $key => $label ) :
                    $is_active = $key === $range['range'];
                    $url       = add_query_arg(
                        array( 'page' => $page_slug, 'wps_range' => $key ),
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>"<?php echo $is_active ? ' class="is-active" aria-current="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ( 'custom' === $range['range'] ) : ?>
                <form method="get" class="wps-range__custom">
                    <input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
                    <input type="hidden" name="wps_range" value="custom" />
                    <label>از <input type="date" class="wps-input wps-input--date" name="wps_start" value="<?php echo esc_attr( $range['start_date'] ); ?>" /></label>
                    <label>تا <input type="date" class="wps-input wps-input--date" name="wps_end" value="<?php echo esc_attr( $range['end_date'] ); ?>" /></label>
                    <button type="submit" class="wps-btn wps-btn--primary">اعمال</button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
