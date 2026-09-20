<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Admin_Menu {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        add_menu_page(
            'آمار سایت',
            'آمار سایت',
            'manage_options',
            'wp-statics',
            array( 'WPS_Admin_Overview_Page', 'render' ),
            'dashicons-chart-area',
            26
        );

        add_submenu_page(
            'wp-statics',
            'نمای کلی',
            'نمای کلی',
            'manage_options',
            'wp-statics',
            array( 'WPS_Admin_Overview_Page', 'render' )
        );

        add_submenu_page(
            'wp-statics',
            'کاربران آنلاین',
            'کاربران آنلاین',
            'manage_options',
            'wp-statics-online',
            array( 'WPS_Admin_Online_Page', 'render' )
        );

        add_submenu_page(
            'wp-statics',
            'منابع بازدید',
            'منابع بازدید',
            'manage_options',
            'wp-statics-sources',
            array( 'WPS_Admin_Sources_Page', 'render' )
        );

        add_submenu_page(
            'wp-statics',
            'صفحات پربازدید',
            'صفحات پربازدید',
            'manage_options',
            'wp-statics-pages',
            array( 'WPS_Admin_Pages_Page', 'render' )
        );

        // Only meaningful with WooCommerce active — the underlying sales
        // data has no writer otherwise (WPS_Order_Sales_Recorder hooks
        // WooCommerce order events).
        if ( function_exists( 'wc_get_order' ) ) {
            add_submenu_page(
                'wp-statics',
                'محصولات',
                'محصولات',
                'manage_options',
                'wp-statics-products',
                array( 'WPS_Admin_Products_Page', 'render' )
            );

            add_submenu_page(
                'wp-statics',
                'قیف فروش',
                'قیف فروش',
                'manage_options',
                'wp-statics-funnel',
                array( 'WPS_Admin_Funnel_Page', 'render' )
            );
        }

        add_submenu_page(
            'wp-statics',
            'صفحات یافت نشد',
            'صفحات یافت نشد',
            'manage_options',
            'wp-statics-404s',
            array( 'WPS_Admin_404_Page', 'render' )
        );

        add_submenu_page(
            'wp-statics',
            'لینک‌ساز کمپین',
            'لینک‌ساز کمپین',
            'manage_options',
            'wp-statics-links',
            array( 'WPS_Admin_Links_Page', 'render' )
        );

        add_submenu_page(
            'wp-statics',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'wp-statics-settings',
            array( 'WPS_Admin_Settings_Page', 'render' )
        );
    }
}
