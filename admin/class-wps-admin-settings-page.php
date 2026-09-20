<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Admin_Settings_Page {

    const NONCE_ACTION = 'wps_save_settings';
    const PAGE_SLUG     = 'wp-statics-settings';

    public static function init() {
        // admin_init runs before admin-header.php fires admin_notices, so
        // this is where the save + redirect must happen — doing it inside
        // render() would be too late to register a notice for this same load.
        add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notice' ) );
    }

    public static function maybe_save() {
        if ( ! isset( $_POST['wps_settings_submit'] ) ) {
            return;
        }

        check_admin_referer( self::NONCE_ACTION );

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $retention_days = isset( $_POST['wps_retention_days'] ) ? absint( $_POST['wps_retention_days'] ) : WPS_Retention_Cron::DEFAULT_DAYS;
        $retention_days = max( 1, min( 365, $retention_days ) );

        $raw_terms = isset( $_POST['wps_extra_bot_terms'] ) ? (string) wp_unslash( $_POST['wps_extra_bot_terms'] ) : '';
        $terms     = array_filter( array_map( 'trim', explode( "\n", $raw_terms ) ) );
        $terms     = array_values( array_map( 'sanitize_text_field', $terms ) );

        update_option( WPS_Activator::SETTINGS_OPTION, array(
            'exclude_admins'  => ! empty( $_POST['wps_exclude_admins'] ),
            'retention_days'  => $retention_days,
            'extra_bot_terms' => $terms,
        ) );

        wp_safe_redirect( add_query_arg(
            array( 'page' => self::PAGE_SLUG, 'wps_saved' => '1' ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public static function maybe_show_notice() {
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice trigger.

        if ( self::PAGE_SLUG !== $page || empty( $_GET['wps_saved'] ) ) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
    }

    public static function render() {
        $settings = self::get_settings();
        WPS_Admin_UI::page_start( array( 'title' => 'تنظیمات آمار سایت' ) );
        ?>
        <form method="post" class="wps-panel">
            <?php wp_nonce_field( self::NONCE_ACTION ); ?>

            <div class="wps-field">
                <div class="wps-field__label">حذف بازدید مدیران</div>
                <div class="wps-field__control">
                    <label class="wps-switch" for="wps_exclude_admins">
                        <input type="checkbox" id="wps_exclude_admins" name="wps_exclude_admins" value="1" <?php checked( ! empty( $settings['exclude_admins'] ) ); ?> />
                        <span class="wps-switch__track" aria-hidden="true"></span>
                        <span>بازدید کاربران دارای دسترسی مدیریت (manage_options) ثبت نشود</span>
                    </label>
                    <p class="wps-help">پیشنهاد می‌شود فعال بماند تا مرور خودتان از سایت آمار بازدید و کاربران آنلاین را دستکاری نکند.</p>
                </div>
            </div>

            <div class="wps-field">
                <div class="wps-field__label"><label for="wps_retention_days">مدت نگهداری داده خام</label><small>روز، بین ۱ تا ۳۶۵</small></div>
                <div class="wps-field__control">
                    <input type="number" id="wps_retention_days" name="wps_retention_days" min="1" max="365" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="wps-input wps-input--sm" />
                    <p class="wps-help">داده خام بازدید (رویدادهای تک‌تک) پس از این مدت حذف می‌شود. آمار تجمیعی روزانه (نمودارها و گزارش‌ها) برای همیشه باقی می‌ماند.</p>
                </div>
            </div>

            <div class="wps-field">
                <div class="wps-field__label"><label for="wps_extra_bot_terms">عبارات ربات اضافی</label><small>هر خط یک عبارت</small></div>
                <div class="wps-field__control">
                    <textarea id="wps_extra_bot_terms" name="wps_extra_bot_terms" rows="5" class="wps-input wps-input--code"><?php echo esc_textarea( implode( "\n", $settings['extra_bot_terms'] ) ); ?></textarea>
                    <p class="wps-help">اگر User-Agent بازدیدکننده شامل این عبارت باشد (بدون توجه به بزرگی/کوچکی حروف)، به‌عنوان ربات در نظر گرفته می‌شود و بازدیدش ثبت نمی‌شود. این عبارات علاوه بر فهرست پیش‌فرض ربات‌های شناخته‌شده اعمال می‌شوند.</p>
                </div>
            </div>

            <div class="wps-form__foot">
                <button type="submit" name="wps_settings_submit" value="1" class="wps-btn wps-btn--primary">ذخیره تنظیمات</button>
            </div>
        </form>
        <?php
        WPS_Admin_UI::page_end();
    }

    private static function get_settings() {
        $defaults = array(
            'exclude_admins'  => true,
            'retention_days'  => WPS_Retention_Cron::DEFAULT_DAYS,
            'extra_bot_terms' => array(),
        );

        return wp_parse_args( get_option( WPS_Activator::SETTINGS_OPTION, array() ), $defaults );
    }
}
