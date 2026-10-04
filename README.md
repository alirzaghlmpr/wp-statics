# WP Statics

**Lightweight, privacy-friendly site analytics for WordPress and WooCommerce.**

WP Statics shows you who is visiting your site, which pages they read, where they came from (Google, Instagram, Telegram, Torob, Digikala, WhatsApp and more), and which traffic source actually leads to sales. It runs inside your own WordPress database, so there is no third-party script and no data leaves your server.

> آمار بازدید صفحات، کاربران آنلاین و تفکیک منبع بازدید به همراه گزارش فروش هر محصول ووکامرس.

---

## Features

- **Overview dashboard**: views, unique visitors and trends for the selected date range.
- **Online users**: who is on the site right now.
- **Traffic sources**: direct, Google, Bing, other search engines, Instagram, Telegram, WhatsApp, Torob, Digikala, Basalam, Bale, Rubika, Eitaa and other referrals, classified from UTM parameters and referrer hosts.
- **Top pages**: most-viewed pages and posts, with a per-page channel breakdown.
- **WooCommerce products**: sales and revenue per product, split by traffic source.
- **Sales funnel**: how visitors move from views to cart to order.
- **404 monitor**: the most-requested missing URLs, so you can fix broken links or add redirects.
- **Link builder**: generate UTM-tagged links for campaigns and social posts.
- **Order channel attribution**: each WooCommerce order is tagged with the channel that brought the customer, and admins can override it from the order screen.
- **CSV export** of reports.
- **Bot filtering**: known crawlers and headless clients are excluded from the numbers.

## How it works

1. A tiny front-end beacon (`assets/js/wps-tracker.js`, about 1.4 KB) sends the page URL, referrer and UTM parameters to a REST endpoint after the page loads. It does not block rendering and does not load any external library.
2. The REST endpoint classifies the visit on the server (`WPS_Source_Classifier`), filters bots, rate-limits per visitor hash and writes a row to a custom hits table.
3. A daily cron job rolls raw hits into a summary table (`wp_wps_daily_stats`) and prunes raw rows after the retention window. Summaries are kept indefinitely, so long-term reports stay fast.
4. The admin pages read from the summary and raw tables and render charts with the bundled Chart.js.

WP Statics is cookieless. Each visitor gets a token derived with a keyed HMAC from IP, user agent, the current day and a site-specific salt. The token rotates every day, so visits cannot be linked across days. IP addresses are stored only as a separate salted hash, used for rate limiting. Raw hits are deleted after the retention period (35 days by default, configurable in settings); daily summaries are kept.

## Requirements

| | Version |
|---|---|
| WordPress | 6.9 or higher |
| PHP | 7.4 or higher |
| WooCommerce | Optional, required only for product sales and order attribution |

## Installation

1. Copy the `wp-statics` folder into `wp-content/plugins/`, or upload the zip from **Plugins → Add New → Upload Plugin**.
2. Activate **WP Statics** from the **Plugins** screen. The activator creates its tables.
3. Open **آمار سایت** (Site Stats) in the admin menu.
4. Optional: adjust the retention window and other options under the settings page.

## Admin pages

| Page | Slug | What it shows |
|---|---|---|
| نمای کلی (Overview) | `wp-statics` | Totals and trends |
| کاربران آنلاین (Online) | `wp-statics-online` | Live visitors |
| منابع بازدید (Sources) | `wp-statics-sources` | Traffic by channel |
| صفحات پربازدید (Pages) | `wp-statics-pages` | Top content |
| محصولات (Products) | `wp-statics-products` | WooCommerce sales by product and source |
| قیف فروش (Funnel) | `wp-statics-funnel` | Conversion steps |
| صفحات یافت نشد (404s) | `wp-statics-404s` | Broken URLs |
| تنظیمات (Settings) | `wp-statics-settings` | Retention and options |

## Development

```
wp-statics/
├── wp-statics.php            # bootstrap, constants, hooks
├── includes/                 # core: tracking endpoint, repositories, cron, classifiers
├── admin/                    # admin menu and page renderers
├── assets/
│   ├── js/                   # wps-tracker.js (front end), admin scripts
│   ├── css/
│   └── vendor/chartjs/       # bundled Chart.js (admin only)
└── uninstall.php             # removes plugin tables and options
```

Core classes follow the `WPS_` prefix. Channel slugs and their labels live in one place, `WPS_Source_Classifier`, so the tracker, the dashboards and WooCommerce attribution always report against the same taxonomy.

To add a traffic source, add its `utm_source` aliases to `UTM_ALIASES` and its domains to `DOMAIN_MAP`, then add a label to `LABELS`.

## Uninstalling

Deleting the plugin through the **Plugins** screen runs `uninstall.php`, which drops the plugin's tables (`wps_hits`, `wps_daily_stats`, `wps_sales_daily`, `wps_presence`), deletes its options and clears its scheduled jobs.

## Changelog

**0.2.1**: current version.

## License

Add your license here.
