<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Admin_Overview_Page {

    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( 'toplevel_page_wp-statics' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'wps-chartjs',
            WPS_PLUGIN_URL . 'assets/vendor/chartjs/chart.umd.min.js',
            array(),
            '4.5.1',
            true
        );

        wp_enqueue_script(
            'wps-admin-charts',
            WPS_PLUGIN_URL . 'assets/js/wps-admin-charts.js',
            array( 'wps-chartjs' ),
            WPS_VERSION,
            true
        );

        $range   = WPS_Date_Range::resolve();
        $daily   = WPS_Rollup_Repository::get_daily_views( $range['start_date'], $range['end_date'] );
        $chans   = WPS_Rollup_Repository::get_channel_breakdown( $range['start_date'], $range['end_date'] );
        $devices = WPS_Hits_Repository::get_device_breakdown( $range['start'], $range['end'] );

        wp_localize_script( 'wps-admin-charts', 'wpsChartsData', array(
            'dailyViews' => array_map( function ( $row ) {
                return array( 'date' => $row->stat_date, 'views' => (int) $row->views );
            }, $daily ),
            'channels'   => array_map( function ( $row ) {
                return array(
                    'label' => WPS_Source_Classifier::get_label( $row->channel ),
                    'color' => WPS_Admin_UI::channel_color( $row->channel ),
                    'views' => (int) $row->views,
                );
            }, $chans ),
            'devices'    => array_map( function ( $row ) {
                return array(
                    'label'    => self::get_device_label( $row->device_type ),
                    'visitors' => (int) $row->visitors,
                );
            }, $devices ),
        ) );
    }

    private static function get_device_label( $device_type ) {
        $labels = array(
            'desktop' => 'دسکتاپ',
            'mobile'  => 'موبایل',
            'tablet'  => 'تبلت',
        );

        return isset( $labels[ $device_type ] ) ? $labels[ $device_type ] : $device_type;
    }

    public static function render() {
        $range    = WPS_Date_Range::resolve();
        $views    = WPS_Rollup_Repository::get_views_total( $range['start_date'], $range['end_date'] );
        // Unique visitors genuinely needs visitor-level data the daily
        // rollup doesn't keep, so this one stays on raw wp_wps_hits — a
        // single indexed COUNT(DISTINCT), cheap even over 30 days.
        $visitors = WPS_Hits_Repository::get_visitors_total( $range['start'], $range['end'] );
        $online   = WPS_Hits_Repository::get_online_count();
        $sales    = self::get_sales_total( $range['start_date'], $range['end_date'] );

        WPS_Admin_UI::page_start( array(
            'title' => 'آمار سایت',
            'range' => $range,
            'slug'  => 'wp-statics',
        ) );

        WPS_Date_Range::render_rollup_lag_notice( $range );
        ?>
        <div class="wps-kpis">
            <?php
            self::render_kpi( 'بازدید', $views );
            self::render_kpi( 'بازدیدکننده', $visitors );
            self::render_kpi( 'آنلاین اکنون', $online, true );
            self::render_kpi( 'فروش', $sales );
            ?>
        </div>

        <div class="wps-panel wps-section">
            <div class="wps-panel__head"><h2>روند بازدید</h2></div>
            <div class="wps-panel__body">
                <div class="wps-chart"><canvas id="wps-chart-views"></canvas></div>
            </div>
        </div>

        <div class="wps-grid wps-grid--2-1">
            <div class="wps-panel">
                <div class="wps-panel__head"><h2>بازدید به تفکیک منبع</h2></div>
                <div class="wps-panel__body">
                    <div class="wps-chart wps-chart--short"><canvas id="wps-chart-channels"></canvas></div>
                </div>
            </div>

            <div class="wps-panel">
                <div class="wps-panel__head"><h2>دستگاه بازدیدکنندگان</h2></div>
                <div class="wps-panel__body">
                    <div class="wps-chart wps-chart--short"><canvas id="wps-chart-devices"></canvas></div>
                </div>
            </div>
        </div>
        <?php
        WPS_Admin_UI::page_end();
    }

    private static function render_kpi( $label, $value, $live = false ) {
        ?>
        <div class="wps-kpi">
            <div class="wps-kpi__label">
                <?php if ( $live ) : ?><span class="wps-live" aria-hidden="true"></span><?php endif; ?>
                <?php echo esc_html( $label ); ?>
            </div>
            <div class="wps-kpi__value"><?php echo esc_html( WPS_Admin_UI::number( $value ) ); ?></div>
        </div>
        <?php
    }

    private static function get_sales_total( $start_date, $end_date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wps_sales_daily';

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(qty), 0) FROM $table WHERE stat_date BETWEEN %s AND %s",
            $start_date,
            $end_date
        ) );
    }
}
