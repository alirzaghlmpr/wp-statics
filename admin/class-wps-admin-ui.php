<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared markup helpers + stylesheet loading for the admin pages, so every
 * page gets the same header, date-range control, empty states and — the one
 * distinctive piece of the design — the same colour for a given traffic
 * source everywhere it appears (chart bars, table dots, share bars).
 *
 * Everything returned as a string here is already escaped.
 */
class WPS_Admin_UI {

    /** channel slug => colour. Keys mirror WPS_Source_Classifier::LABELS. */
    const CHANNEL_COLORS = array(
        'direct'         => '#6b7a90',
        'google'         => '#3b5bdb',
        'bing'           => '#0ca678',
        'other_search'   => '#9aa7b8',
        'instagram'      => '#d6336c',
        'telegram'       => '#22a6e0',
        'torob'          => '#f76707',
        'digikala'       => '#e03131',
        'basalam'        => '#f59f00',
        'bale'           => '#74b816',
        'rubika'         => '#ae3ec9',
        'eitaa'          => '#1098ad',
        'whatsapp'       => '#2f9e44',
        'other_referral' => '#adb5bd',
    );

    const FALLBACK_COLOR = '#adb5bd';

    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
    }

    public static function enqueue_styles() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen check.

        if ( 0 !== strpos( $page, 'wp-statics' ) ) {
            return;
        }

        wp_enqueue_style(
            'wps-admin',
            WPS_PLUGIN_URL . 'assets/css/wps-admin.css',
            array(),
            WPS_VERSION
        );
    }

    /**
     * Whether $hook (from admin_enqueue_scripts) is the plugin page $slug.
     *
     * Core builds a submenu hook as "<sanitized parent menu title>_page_<slug>".
     * The parent title here is Persian, so that prefix is percent-encoded
     * ("%d8%a2…_page_wp-statics-online"), never "wp-statics_page_…" — match on
     * the stable "_page_<slug>" suffix instead of hard-coding the prefix.
     */
    public static function is_screen( $hook, $slug ) {
        $suffix = '_page_' . $slug;

        return substr( (string) $hook, -strlen( $suffix ) ) === $suffix;
    }

    public static function channel_color( $slug ) {
        return isset( self::CHANNEL_COLORS[ $slug ] ) ? self::CHANNEL_COLORS[ $slug ] : self::FALLBACK_COLOR;
    }

    public static function number( $value ) {
        return number_format_i18n( (float) $value );
    }

    /**
     * Opens the page: wrapper, title, optional description, and the header
     * actions (date-range control + CSV export). Pair with page_end().
     *
     * @param array $args title, description?, range? + slug? (renders the picker), export_url?.
     */
    public static function page_start( array $args ) {
        $description = isset( $args['description'] ) ? $args['description'] : '';
        ?>
        <div class="wrap wps-app" dir="rtl">
            <header class="wps-head">
                <div>
                    <h1><?php echo esc_html( $args['title'] ); ?></h1>
                    <?php if ( '' !== $description ) : ?>
                        <p class="wps-head__desc"><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( ! empty( $args['range'] ) || ! empty( $args['export_url'] ) ) : ?>
                    <div class="wps-head__actions">
                        <?php
                        if ( ! empty( $args['range'] ) ) {
                            WPS_Date_Range::render_picker( $args['range'], $args['slug'] );
                        }

                        if ( ! empty( $args['export_url'] ) ) {
                            self::render_export_button( $args['export_url'] );
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </header>
            <?php // Anchor for core's admin-notice relocation, so notices land below the header, not inside it. ?>
            <hr class="wp-header-end" />
        <?php
    }

    public static function page_end() {
        echo '</div>';
    }

    public static function render_export_button( $url ) {
        ?>
        <a class="wps-btn" href="<?php echo esc_url( $url ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
            دانلود CSV
        </a>
        <?php
    }

    /** Table row holding an empty state. */
    public static function empty_row( $colspan, $title = 'داده‌ای برای این بازه یافت نشد.', $hint = 'بازه زمانی دیگری را امتحان کنید.' ) {
        ?>
        <tr>
            <td colspan="<?php echo esc_attr( (int) $colspan ); ?>" class="wps-table__empty">
                <div class="wps-empty">
                    <strong><?php echo esc_html( $title ); ?></strong>
                    <?php if ( '' !== $hint ) : ?>
                        <span><?php echo esc_html( $hint ); ?></span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /** Colour dot + label (+ optional count) for a channel slug. */
    public static function channel_chip( $slug, $count = null ) {
        $html = '<span class="wps-chan" style="--c:' . esc_attr( self::channel_color( $slug ) ) . '">'
            . esc_html( WPS_Source_Classifier::get_label( $slug ) );

        if ( null !== $count ) {
            $html .= ' <span class="wps-chan__n">' . esc_html( self::number( $count ) ) . '</span>';
        }

        return $html . '</span>';
    }

    /**
     * Stacked bar, one segment per channel, width proportional to value.
     *
     * @param array $items list of array( 'slug' => string, 'value' => number ).
     */
    public static function share_bar( array $items, $large = false ) {
        $total = 0;

        foreach ( $items as $item ) {
            $total += max( 0, (float) $item['value'] );
        }

        if ( $total <= 0 ) {
            return '';
        }

        $html = '<div class="wps-share' . ( $large ? ' wps-share--lg' : '' ) . '" aria-hidden="true">';

        foreach ( $items as $item ) {
            if ( $item['value'] <= 0 ) {
                continue;
            }

            $title = WPS_Source_Classifier::get_label( $item['slug'] ) . ': ' . self::number( $item['value'] );
            $html .= '<i style="--c:' . esc_attr( self::channel_color( $item['slug'] ) ) . ';--v:' . (int) $item['value'] . '" title="' . esc_attr( $title ) . '"></i>';
        }

        return $html . '</div>';
    }

    /** Number with a thin bar under it, scaled against $max (typically the column's top value). */
    public static function cell_bar( $value, $max, $color = '' ) {
        $pct   = $max > 0 ? (int) min( 100, max( 0, round( ( $value / $max ) * 100 ) ) ) : 0;
        $style = '--w:' . $pct . '%' . ( '' !== $color ? ';--c:' . $color : '' );

        return '<span class="wps-cell-bar"><span>' . esc_html( self::number( $value ) ) . '</span>'
            . '<span class="wps-meter" aria-hidden="true"><i style="' . esc_attr( $style ) . '"></i></span></span>';
    }

    /** Conversion-rate style percentage badge; muted when zero. */
    public static function rate_pill( $rate ) {
        return '<span class="wps-pill' . ( $rate > 0 ? '' : ' wps-pill--zero' ) . '">' . esc_html( $rate ) . '٪</span>';
    }
}
