<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPS_Activator {

    const DB_VERSION_OPTION = 'wps_db_version';
    const IP_SALT_OPTION    = 'wps_ip_salt';
    const SETTINGS_OPTION   = 'wps_settings';

    public static function activate() {
        self::create_tables();
        self::maybe_generate_ip_salt();
        self::maybe_create_default_settings();

        update_option( self::DB_VERSION_OPTION, WPS_VERSION );
    }

    /**
     * Re-runs table creation whenever the plugin version changes, so upgrades
     * that add/alter columns apply even if activation didn't fire (e.g. the
     * plugin files were updated in place without a deactivate/reactivate cycle).
     */
    public static function maybe_upgrade() {
        if ( get_option( self::DB_VERSION_OPTION ) !== WPS_VERSION ) {
            self::create_tables();
            self::maybe_generate_ip_salt();
            self::maybe_create_default_settings();

            update_option( self::DB_VERSION_OPTION, WPS_VERSION );
        }
    }

    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        self::create_hits_table( $wpdb, $charset_collate );
        self::create_daily_stats_table( $wpdb, $charset_collate );
        self::create_sales_daily_table( $wpdb, $charset_collate );
        self::create_presence_table( $wpdb, $charset_collate );
    }

    /**
     * Raw event log. Short retention (pruned by the retention cron, milestone 7);
     * this is the source of truth that wp_wps_daily_stats gets rolled up from.
     *
     * request_path (added for 404 tracking, Phase 2) is only populated when
     * object_type = '404' — a 404 has no post ID to key off, so the request
     * path itself is what needs recording; NULL for every other hit type to
     * keep the common case unaffected.
     *
     * hit_type widened from varchar(10) to varchar(20) in the same Phase 2
     * pass to fit 'add_to_cart' (funnel tracking) alongside 'view'/'heartbeat'.
     */
    private static function create_hits_table( $wpdb, $charset_collate ) {
        $table_name = $wpdb->prefix . 'wps_hits';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_type varchar(20) NOT NULL DEFAULT 'other',
            object_id bigint(20) unsigned DEFAULT NULL,
            visitor_token char(32) NOT NULL,
            channel varchar(20) NOT NULL DEFAULT 'direct',
            utm_source varchar(100) DEFAULT NULL,
            utm_medium varchar(100) DEFAULT NULL,
            utm_campaign varchar(100) DEFAULT NULL,
            referrer_host varchar(190) DEFAULT NULL,
            request_path varchar(500) DEFAULT NULL,
            device_type varchar(10) NOT NULL DEFAULT 'desktop',
            ip_hash char(64) NOT NULL,
            hit_type varchar(20) NOT NULL DEFAULT 'view',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY object_lookup (object_type, object_id, created_at),
            KEY channel_lookup (channel, created_at),
            KEY visitor_lookup (visitor_token, created_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Daily rollup: views = COUNT(DISTINCT visitor_token) from wp_wps_hits for
     * that day, not raw hit count (see plan §1 for why). object_id = 0 is the
     * site-wide / "other" bucket. Long retention — this table stays small.
     */
    private static function create_daily_stats_table( $wpdb, $charset_collate ) {
        $table_name = $wpdb->prefix . 'wps_daily_stats';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            stat_date date NOT NULL,
            object_type varchar(20) NOT NULL DEFAULT 'other',
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            channel varchar(20) NOT NULL DEFAULT 'direct',
            views int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY stat_lookup (stat_date, object_type, object_id, channel),
            KEY stat_date (stat_date),
            KEY object_lookup (object_type, object_id, stat_date)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * WooCommerce sales rollup, written directly on order status change
     * (event-driven, not cron-aggregated — see plan §1). order_product is the
     * idempotency key alongside the _wps_sales_recorded order-meta flag.
     */
    private static function create_sales_daily_table( $wpdb, $charset_collate ) {
        $table_name = $wpdb->prefix . 'wps_sales_daily';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            stat_date date NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            channel varchar(20) NOT NULL DEFAULT 'direct',
            order_id bigint(20) unsigned NOT NULL,
            qty int(10) unsigned NOT NULL DEFAULT 0,
            line_total decimal(19,4) NOT NULL DEFAULT 0.0000,
            PRIMARY KEY (id),
            UNIQUE KEY order_product (order_id, product_id),
            KEY product_lookup (product_id, stat_date),
            KEY channel_lookup (channel, stat_date)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * "Online now" presence. Tiny, constantly churning table; self-cleaned by
     * an inline DELETE before each count query rather than a dedicated cron.
     */
    private static function create_presence_table( $wpdb, $charset_collate ) {
        $table_name = $wpdb->prefix . 'wps_presence';

        $sql = "CREATE TABLE $table_name (
            visitor_token char(32) NOT NULL,
            last_seen datetime NOT NULL,
            object_id bigint(20) unsigned DEFAULT NULL,
            channel varchar(20) DEFAULT NULL,
            PRIMARY KEY (visitor_token),
            KEY last_seen (last_seen)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Per-site random salt used to hash IP+UA into a daily-rotating visitor
     * token (plan §3) — never displayed or exported, generated once.
     */
    private static function maybe_generate_ip_salt() {
        if ( ! get_option( self::IP_SALT_OPTION ) ) {
            add_option( self::IP_SALT_OPTION, wp_generate_password( 32, false ) );
        }
    }

    private static function maybe_create_default_settings() {
        if ( ! get_option( self::SETTINGS_OPTION ) ) {
            add_option( self::SETTINGS_OPTION, array(
                'exclude_admins'  => true,
                'retention_days'  => 35,
                'extra_bot_terms' => array(),
            ) );
        }
    }
}
