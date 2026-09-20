<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campaign link builder — lets the store owner generate a distinct
 * trackable URL per channel/campaign (e.g. one for the Instagram bio link,
 * a different one for a Telegram post), so views/sales through that link
 * attribute to the intended channel via WPS_Source_Classifier's utm_source
 * matching rather than relying on referrer detection alone (which breaks
 * for in-app browsers that strip/rewrite referrers — exactly the Instagram/
 * Telegram case this tool targets). Fully client-side/stateless — nothing
 * is persisted, so no save handler, nonce, or DB table needed.
 */
class WPS_Admin_Links_Page {

    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets( $hook ) {
        if ( ! WPS_Admin_UI::is_screen( $hook, 'wp-statics-links' ) ) {
            return;
        }

        wp_enqueue_script(
            'wps-admin-link-builder',
            WPS_PLUGIN_URL . 'assets/js/wps-admin-link-builder.js',
            array(),
            WPS_VERSION,
            true
        );
    }

    public static function render() {
        $channels = self::get_utm_channel_options();
        WPS_Admin_UI::page_start( array(
            'title'       => 'لینک‌ساز کمپین',
            'description' => 'برای هر کانال (مثلاً بایوی اینستاگرام، پست تلگرام) یک لینک اختصاصی بسازید تا بازدید و فروش حاصل از آن به‌طور دقیق به همان منبع نسبت داده شود — به‌خصوص برای اینستاگرام و تلگرام که مرورگر داخلی‌شان معمولاً منبع واقعی را مخفی می‌کند.',
        ) );
        ?>
        <div class="wps-panel">
            <div class="wps-field">
                <div class="wps-field__label"><label for="wps-link-url">آدرس مقصد</label></div>
                <div class="wps-field__control">
                    <input type="text" id="wps-link-url" class="wps-input wps-input--code" value="<?php echo esc_attr( home_url( '/' ) ); ?>" />
                </div>
            </div>
            <div class="wps-field">
                <div class="wps-field__label"><label for="wps-link-source">منبع</label></div>
                <div class="wps-field__control">
                    <select id="wps-link-source" class="wps-input">
                        <?php foreach ( $channels as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="wps-field">
                <div class="wps-field__label"><label for="wps-link-medium">رسانه</label><small>اختیاری</small></div>
                <div class="wps-field__control">
                    <input type="text" id="wps-link-medium" class="wps-input" dir="auto" placeholder="مثلاً bio، post، story" />
                </div>
            </div>
            <div class="wps-field">
                <div class="wps-field__label"><label for="wps-link-campaign">نام کمپین</label><small>اختیاری</small></div>
                <div class="wps-field__control">
                    <input type="text" id="wps-link-campaign" class="wps-input" dir="auto" placeholder="مثلاً حراج-تابستان" />
                </div>
            </div>
        </div>

        <div class="wps-panel wps-section">
            <div class="wps-panel__head"><h2>لینک نهایی</h2></div>
            <div class="wps-panel__body">
                <div class="wps-copy">
                    <input type="text" id="wps-link-output" class="wps-input wps-input--code" readonly onclick="this.select();" />
                    <button type="button" id="wps-link-copy" class="wps-btn wps-btn--primary">کپی</button>
                </div>
                <div class="wps-copy-row"><span id="wps-link-copied" class="wps-copied" role="status">کپی شد!</span></div>
            </div>
        </div>
        <?php
        WPS_Admin_UI::page_end();
    }

    /** Excludes 'direct'/'other_search'/'other_referral' — not real channels to deliberately tag a link with. */
    private static function get_utm_channel_options() {
        $all = WPS_Source_Classifier::get_all_labels();
        unset( $all['direct'], $all['other_search'], $all['other_referral'] );

        return $all;
    }
}
