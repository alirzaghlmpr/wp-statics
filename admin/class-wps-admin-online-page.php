<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Admin_Online_Page {

    const NONCE_ACTION     = 'wps_online_count';
    const POLL_INTERVAL_MS = 15000;

    public static function init() {
        add_action( 'wp_ajax_wps_online_count', array( __CLASS__, 'ajax_online_count' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( ! WPS_Admin_UI::is_screen( $hook, 'wp-statics-online' ) ) {
            return;
        }

        wp_enqueue_script(
            'wps-admin-online',
            WPS_PLUGIN_URL . 'assets/js/wps-admin-online.js',
            array(),
            WPS_VERSION,
            true
        );

        wp_localize_script( 'wps-admin-online', 'wpsOnlineData', array(
            'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
            'pollInterval' => self::POLL_INTERVAL_MS,
        ) );
    }

    public static function render() {
        $count = WPS_Hits_Repository::get_online_count();
        WPS_Admin_UI::page_start( array(
            'title'       => 'کاربران آنلاین',
            'description' => 'تعداد کاربرانی که در ۵ دقیقه گذشته در سایت فعال بوده‌اند (هر ۱۵ ثانیه به‌روزرسانی می‌شود).',
        ) );
        ?>
        <div class="wps-grid wps-grid--1-3 wps-grid--flush">
            <div class="wps-kpis wps-kpis--single">
                <div class="wps-kpi">
                    <div class="wps-kpi__label"><span class="wps-live" aria-hidden="true"></span>آنلاین اکنون</div>
                    <div class="wps-kpi__value" id="wps-online-count" aria-live="polite"><?php echo esc_html( $count ); ?></div>
                </div>
            </div>

            <div class="wps-panel">
                <div class="wps-table-wrap">
                    <table class="wps-table">
                        <thead>
                            <tr>
                                <th>صفحه</th>
                                <th>منبع</th>
                            </tr>
                        </thead>
                        <tbody id="wps-online-items">
                            <tr><td colspan="2" class="wps-table__empty"><div class="wps-empty"><span>در حال بارگذاری…</span></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        WPS_Admin_UI::page_end();
    }

    public static function ajax_online_count() {
        check_ajax_referer( self::NONCE_ACTION );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        wp_send_json_success( array(
            'count' => WPS_Hits_Repository::get_online_count(),
            'items' => self::format_active_items( WPS_Hits_Repository::get_active_presence() ),
        ) );
    }

    private static function format_active_items( $rows ) {
        $items = array();

        foreach ( $rows as $row ) {
            $items[] = array(
                'title'   => $row->object_id ? get_the_title( $row->object_id ) : 'صفحه دیگر',
                'channel' => WPS_Source_Classifier::get_label( $row->channel ),
                'color'   => WPS_Admin_UI::channel_color( $row->channel ),
            );
        }

        return $items;
    }
}
