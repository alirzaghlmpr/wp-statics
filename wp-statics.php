<?php
/**
 * Plugin Name: WP Statics
 * Description: آمار بازدید صفحات، کاربران آنلاین و تفکیک منبع بازدید (مستقیم، گوگل، اینستاگرام، تلگرام، ترب، دیجی‌کالا، باسلام، بله، روبیکا، ایتا، واتس‌اپ) به همراه گزارش بازدید و فروش هر محصول ووکامرس به تفکیک منبع.
 * Version:     0.2.1
 * Text Domain: wp-statics
 * Requires at least: 6.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPS_VERSION',     '0.2.1' );
define( 'WPS_PLUGIN_FILE', __FILE__ );
define( 'WPS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once WPS_PLUGIN_DIR . 'includes/class-wps-activator.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-deactivator.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-source-classifier.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-visitor-identity.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-bot-filter.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-hits-repository.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-tracking-endpoint.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-frontend.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-date-range.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-rollup-repository.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-rollup-cron.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-retention-cron.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-ui.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-menu.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-online-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-overview-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-settings-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-sources-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-pages-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-content-metabox.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-links-page.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-404-page.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-csv-export.php';

// WooCommerce-dependent modules — safe to define even without WooCommerce
// active (they only reference WC_* classes inside method bodies/type hints,
// which PHP doesn't resolve until called), but only wired up below if
// WooCommerce is actually active. Page-view tracking works standalone.
require_once WPS_PLUGIN_DIR . 'includes/class-wps-sales-repository.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-order-attribution-reader.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-order-sales-recorder.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-order-channel-metabox.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-product-metabox.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-products-page.php';
require_once WPS_PLUGIN_DIR . 'includes/class-wps-cart-tracker.php';
require_once WPS_PLUGIN_DIR . 'admin/class-wps-admin-funnel-page.php';

register_activation_hook( __FILE__, array( 'WPS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPS_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', 'wps_init_plugin' );

function wps_init_plugin() {
    // Keeps the DB schema current if files were updated without re-running activation.
    WPS_Activator::maybe_upgrade();

    WPS_Tracking_Endpoint::init();
    WPS_Frontend::init();
    WPS_Admin_UI::init();
    WPS_Admin_Menu::init();
    WPS_Admin_Online_Page::init();
    WPS_Admin_Overview_Page::init();
    WPS_Admin_Settings_Page::init();
    WPS_Content_Metabox::init();
    WPS_Admin_Links_Page::init();
    WPS_CSV_Export::init();
    WPS_Rollup_Cron::init();
    WPS_Retention_Cron::init();

    if ( class_exists( 'WooCommerce' ) ) {
        WPS_Order_Sales_Recorder::init();
        WPS_Order_Channel_Metabox::init();
        WPS_Product_Metabox::init();
        WPS_Cart_Tracker::init();
    }
}
