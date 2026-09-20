<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * UA-substring bot/crawler filter for the tracking endpoint (plan §3).
 * The JS-beacon design is itself a strong first line of defense — a simple
 * scraper that only GETs the HTML never executes JS and never calls the
 * endpoint at all. This list catches the rest (known bots that DO run JS,
 * and non-browser HTTP clients hitting the endpoint directly).
 */
class WPS_Bot_Filter {

    const DEFAULT_TERMS = array(
        'bot', 'spider', 'crawl', 'slurp', 'googlebot', 'bingbot', 'yandexbot',
        'baiduspider', 'duckduckbot', 'applebot', 'facebookexternalhit',
        'semrushbot', 'ahrefsbot', 'mj12bot', 'petalbot', 'dotbot',
        'python-requests', 'curl', 'go-http-client', 'headlesschrome',
        'phantomjs', 'scrapy', 'okhttp', 'java/',
    );

    public static function is_bot( $user_agent ) {
        $ua = strtolower( trim( (string) $user_agent ) );

        if ( '' === $ua ) {
            return true;
        }

        foreach ( self::get_terms() as $term ) {
            $term = strtolower( trim( $term ) );

            if ( '' !== $term && false !== strpos( $ua, $term ) ) {
                return true;
            }
        }

        return false;
    }

    private static function get_terms() {
        $settings = get_option( WPS_Activator::SETTINGS_OPTION, array() );
        $extra    = isset( $settings['extra_bot_terms'] ) && is_array( $settings['extra_bot_terms'] )
            ? $settings['extra_bot_terms']
            : array();

        return array_merge( self::DEFAULT_TERMS, $extra );
    }
}
