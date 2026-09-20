<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single source of truth for turning a UTM/referrer pair into a wp-statics
 * channel slug. Used identically by the view-tracking beacon (plan §3) and
 * by the WooCommerce order-attribution reader (plan §4) so views and sales
 * are always reported against the same taxonomy.
 */
class WPS_Source_Classifier {

    /**
     * utm_source value (lowercased) => channel slug. Presence of any
     * recognized utm_source overrides referrer-based detection.
     */
    const UTM_ALIASES = array(
        'google'    => 'google',
        'bing'      => 'bing',
        'instagram' => 'instagram',
        'ig'        => 'instagram',
        'telegram'  => 'telegram',
        'tg'        => 'telegram',
        'torob'     => 'torob',
        'digikala'  => 'digikala',
        'basalam'   => 'basalam',
        'bale'      => 'bale',
        'rubika'    => 'rubika',
        'eitaa'     => 'eitaa',
        'whatsapp'  => 'whatsapp',
        'wa'        => 'whatsapp',
    );

    /**
     * channel slug => base domain(s). A referrer host matches a channel if
     * it equals a domain here or is a subdomain of it (see host_matches_domain()).
     * 'google' is handled separately (google.php, google.co.uk, google.ir, ...).
     */
    const DOMAIN_MAP = array(
        'bing'          => array( 'bing.com' ),
        'other_search'  => array( 'yahoo.com', 'yandex.com', 'duckduckgo.com', 'search.brave.com' ),
        'instagram'     => array( 'instagram.com' ),
        'telegram'      => array( 't.me', 'telegram.me', 'telegram.org' ),
        'torob'         => array( 'torob.com' ),
        'digikala'      => array( 'digikala.com' ),
        'basalam'       => array( 'basalam.com' ),
        'bale'          => array( 'bale.ai' ),
        'rubika'        => array( 'rubika.ir' ),
        'eitaa'         => array( 'eitaa.com' ),
        'whatsapp'      => array( 'wa.me', 'whatsapp.com' ),
    );

    /**
     * channel slug => Persian label, for the admin UI (dashboard tables,
     * order channel-override dropdown). Single source of truth so the
     * channel list can't drift between files as later phases add UI.
     */
    const LABELS = array(
        'direct'         => 'مستقیم',
        'google'         => 'گوگل',
        'bing'           => 'بینگ',
        'other_search'   => 'سایر موتورهای جستجو',
        'instagram'      => 'اینستاگرام',
        'telegram'       => 'تلگرام',
        'torob'          => 'ترب',
        'digikala'       => 'دیجی‌کالا',
        'basalam'        => 'باسلام',
        'bale'           => 'بله',
        'rubika'         => 'روبیکا',
        'eitaa'          => 'ایتا',
        'whatsapp'       => 'واتس‌اپ',
        'other_referral' => 'سایر',
    );

    /**
     * @param array  $utm          May contain 'utm_source' (and optionally utm_medium/utm_campaign, unused here).
     * @param string $referrer_raw Full referrer URL (e.g. document.referrer), or empty string for none.
     * @return string Channel slug — always a key of self::LABELS.
     */
    public static function classify( array $utm, $referrer_raw ) {
        $utm_source = isset( $utm['utm_source'] ) ? strtolower( trim( (string) $utm['utm_source'] ) ) : '';

        if ( '' !== $utm_source ) {
            if ( isset( self::UTM_ALIASES[ $utm_source ] ) ) {
                return self::UTM_ALIASES[ $utm_source ];
            }
            // An unrecognized utm_source still proves this wasn't a direct/typed
            // visit, so fall through to referrer matching rather than 'direct'.
        }

        return self::classify_referrer( (string) $referrer_raw );
    }

    public static function get_label( $channel ) {
        return isset( self::LABELS[ $channel ] ) ? self::LABELS[ $channel ] : self::LABELS['other_referral'];
    }

    /** @return array<string,string> channel slug => Persian label, in display order. */
    public static function get_all_labels() {
        return self::LABELS;
    }

    /**
     * Compact "label: count, label: count (+N)" text for a channel
     * breakdown row set (already ordered by views desc) — used where a full
     * per-channel table would be too wide, e.g. one cell in a list of pages.
     *
     * @param array $rows objects with ->channel and ->views, views-desc ordered.
     */
    public static function format_breakdown_summary( array $rows, $limit = 3 ) {
        if ( empty( $rows ) ) {
            return '—';
        }

        $shown = array_slice( $rows, 0, $limit );
        $parts = array();

        foreach ( $shown as $row ) {
            $parts[] = self::get_label( $row->channel ) . ': ' . (int) $row->views;
        }

        $summary   = implode( '، ', $parts );
        $remaining = count( $rows ) - count( $shown );

        if ( $remaining > 0 ) {
            $summary .= sprintf( ' (+%d)', $remaining );
        }

        return $summary;
    }

    private static function classify_referrer( $referrer_raw ) {
        $host = self::normalize_host( $referrer_raw );

        if ( '' === $host ) {
            return 'direct';
        }

        $site_host = self::normalize_host( home_url() );
        if ( '' !== $site_host && $host === $site_host ) {
            return 'direct';
        }

        if ( self::is_google_host( $host ) ) {
            return 'google';
        }

        foreach ( self::DOMAIN_MAP as $channel => $domains ) {
            foreach ( $domains as $domain ) {
                if ( self::host_matches_domain( $host, $domain ) ) {
                    return $channel;
                }
            }
        }

        return 'other_referral';
    }

    private static function normalize_host( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '';
        }

        if ( false === strpos( $value, '://' ) ) {
            $value = 'https://' . $value;
        }

        $host = wp_parse_url( $value, PHP_URL_HOST );

        if ( ! $host ) {
            return '';
        }

        $host = strtolower( $host );

        if ( 0 === strpos( $host, 'www.' ) ) {
            $host = substr( $host, 4 );
        }

        return $host;
    }

    private static function host_matches_domain( $host, $domain ) {
        return $host === $domain || substr( $host, -( strlen( $domain ) + 1 ) ) === '.' . $domain;
    }

    /** Matches google.com, google.co.uk, google.ir, www.google.de, etc. */
    private static function is_google_host( $host ) {
        return (bool) preg_match( '/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2,3})?$/', $host );
    }
}
