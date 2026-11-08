<?php
/**
 * Frontend renderer for product badges.
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

namespace Merineo\ProductBadges\Frontend;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles:
 * - hooking into WooCommerce template actions to output badges;
 * - enqueueing frontend styles and inline variables;
 * - collecting automatic / category / product-specific badges.
 */
class Renderer {

    /**
     * Settings service.
     *
     * @var Settings
     */
    private Settings $settings;


    /**
     * Runtime cache for YITH promo rules index (per request).
     *
     * @var array<string,mixed>|null
     *
     * @since 1.0.8
     */
    private static ?array $yith_promo_index = null;

    /**
     * Runtime cache for YITH promo rule objects (per request).
     *
     * @var array<int,object>|null
     *
     * @since 1.0.8
     */
    private static ?array $yith_promo_rules = null;

    /**
     * Runtime cache of detected displayed discounts, keyed by product ID (per request).
     *
     * Not persisted: displayed prices may differ per user (roles, logged-in state).
     *
     * @var array<int,array<string,float>|null>
     */
    private static array $displayed_discount_cache = [];

    /**
     * Constructor.
     *
     * @param Settings $settings Settings service.
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Register main frontend hooks.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/add_filter/
     * @link https://developer.wordpress.org/reference/functions/add_action/
     */
    public function hooks(): void {
        // Replace default WooCommerce "Sale!" badge while our badges are enabled.
        // @link https://woocommerce.github.io/code-reference/hooks/hooks.html
        add_filter( 'woocommerce_sale_flash', [ $this, 'filter_sale_flash' ], 10 );

        // Register dynamic hooks for single / loop / custom placements.
        add_action( 'wp', [ $this, 'register_output_hooks' ] );

        // Enqueue frontend styles and inline CSS variables.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    /**
     * Hide the default WooCommerce "Sale!" flash only when plugin badges are enabled.
     *
     * When the global toggle is off, WooCommerce output is left untouched so the
     * store does not lose its only sale indicator.
     *
     * @param string|mixed $html Default sale flash HTML.
     *
     * @return string|mixed
     */
    public function filter_sale_flash( $html ) {
        $options = $this->settings->all();

        return empty( $options['general']['enabled'] ) ? $html : '';
    }

    /**
     * Register output hooks based on settings (single / loop / custom).
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/add_action/
     */
    public function register_output_hooks(): void {
        $options = $this->settings->all();

        if ( empty( $options['general']['enabled'] ) ) {
            return;
        }

        foreach ( [ 'single', 'loop', 'custom' ] as $area ) {
            $conf = $options['general'][ $area ] ?? null;

            if ( ! is_array( $conf ) ) {
                continue;
            }

            $hook = $conf['hook'] ?: $conf['custom_hook'];
            if ( ! $hook ) {
                continue;
            }

            $priority = isset( $conf['priority'] ) ? (int) $conf['priority'] : 10;

            // Map area → callback.
            $callback = 'render_for_single';
            if ( 'loop' === $area ) {
                $callback = 'render_for_loop';
            } elseif ( 'custom' === $area ) {
                $callback = 'render_for_custom';
            }

            add_action( $hook, [ $this, $callback ], $priority );
        }
    }

    /**
     * Enqueue frontend CSS and inline custom properties.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_style/
     * @link https://developer.wordpress.org/reference/functions/wp_add_inline_style/
     * @link https://developer.woocommerce.com/2023/03/28/the-different-woocommerce-page-types/
     */
    public function enqueue_styles(): void {
        if ( is_admin() ) {
            return;
        }

        wp_enqueue_style(
            'merineo-product-badges',
            MERINEO_PB_URL . 'assets/frontend/css/badges.css',
            [],
            MERINEO_PB_VERSION
        );

        $options = $this->settings->all();
        $general = $options['general'];

        $font_size      = (float) ( $general['font_size'] ?? 14.0 );
        $text_transform = $general['text_transform'] ?? 'none';
        $font_weight    = $general['font_weight'] ?? 'normal';
        $letter_spacing = (float) ( $general['letter_spacing'] ?? 0.0 );
        $shadow         = ! empty( $general['shadow_enabled'] );

        $css = '.merineo-badges-scope{';
        $css .= '--merineo-badge-font-size:' . $font_size . 'px;';
        $css .= '--merineo-badge-text-transform:' . ( 'uppercase' === $text_transform ? 'uppercase' : 'none' ) . ';';
        $css .= '--merineo-badge-font-weight:' . ( 'bold' === $font_weight ? '700' : '400' ) . ';';
        $css .= '--merineo-badge-letter-spacing:' . $letter_spacing . 'px;';
        if ( $shadow ) {
            $css .= '--merineo-badge-shadow:0 1px 2px rgba(0,0,0,0.15);';
        }
        $css .= '}';

        // Append custom CSS, already scoped in UI recommendation.
        // Sanitized again on output so values stored by older versions cannot close the <style> tag.
        if ( ! empty( $options['css']['custom'] ) ) {
            $css .= "\n" . Settings::sanitize_custom_css( (string) $options['css']['custom'] );
        }

        wp_add_inline_style( 'merineo-product-badges', $css );
    }

    /**
     * Render badges for single product page hooks.
     *
     * @return void
     *
     * @link https://docs.woocommerce.com/document/woocommerce-hooks/
     */
    public function render_for_single(): void {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $this->render_for_product( $product, 'single' );
    }

    /**
     * Render badges in product loops (shop / category).
     *
     * @return void
     */
    public function render_for_loop(): void {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            $product = wc_get_product( get_the_ID() );
        }

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $this->render_for_product( $product, 'loop' );
    }

    /**
     * Render badges for custom hook placement.
     *
     * @return void
     */
    public function render_for_custom(): void {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            $product = wc_get_product( get_the_ID() );
        }

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $this->render_for_product( $product, 'custom' );
    }

    /**
     * Render badges wrapper for a concrete product + context.
     *
     * @param \WC_Product $product Product instance.
     * @param string      $context Context key: single|loop|custom.
     *
     * @return void
     */
    private function render_for_product( \WC_Product $product, string $context ): void {
        $options = $this->settings->all();

        if ( empty( $options['general']['enabled'] ) ) {
            return;
        }

        $badges = $this->collect_badges_for_product( $product, $options );
        if ( empty( $badges ) ) {
            return;
        }

        $general       = $options['general'];
        $align         = $general[ $context ]['align'] ?? 'left';
        $style_variant = $general['style_variant'] ?? 'pill';
        $layout        = $general['layout'] ?? 'inline';
        $outline       = ! empty( $general['style_outline'] );

        // Inner badges wrapper classes.
        $classes = [
            'merineo-badges',
            'merineo-badges--align-' . ( 'right' === $align ? 'right' : 'left' ),
            'merineo-badges--style-' . sanitize_html_class( (string) $style_variant ),
            'merineo-badges--' . ( 'stacked' === $layout ? 'stacked' : 'inline' ),
            // Context-specific class so CSS can target single/loop/custom separately.
            'merineo-badges--context-' . sanitize_html_class( $context ),
        ];

        if ( $outline ) {
            $classes[] = 'merineo-badges--outline';
        }

        // Outer scope wrapper classes (also context-aware).
        $scope_classes = [
            'merineo-badges-scope',
            'merineo-badges-scope--' . sanitize_html_class( $context ),
        ];

        echo '<div class="' . esc_attr( implode( ' ', $scope_classes ) ) . '"><div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

        foreach ( $badges as $badge ) {
            $label      = $badge['label'] ?? '';
            $bg_color   = $badge['bg_color'] ?? '';
            $text_color = $badge['text_color'] ?? '';
            $type       = $badge['type'] ?? 'custom';
            $source     = $badge['source'] ?? 'custom';

            if ( '' === $label ) {
                continue;
            }

            // Use CSS variables instead of hard background-color/color, so that
            // outline mode and ribbons/tags can reuse them for borders and shapes.
            $style_parts = [];
            if ( $bg_color ) {
                $style_parts[] = '--merineo-badge-bg:' . $bg_color;
            }
            if ( $text_color ) {
                $style_parts[] = '--merineo-badge-color:' . $text_color;
            }
            $style_attr = implode( ';', $style_parts );
            if ( '' !== $style_attr ) {
                $style_attr .= ';';
            }

            printf(
                '<span class="merineo-badge merineo-badge--type-%1$s merineo-badge--source-%2$s" style="%3$s">%4$s</span>',
                esc_attr( $type ),
                esc_attr( $source ),
                esc_attr( $style_attr ),
                esc_html( $label )
            );
        }

        echo '</div></div>';
    }

    /**
     * Collect all badges for a product in correct order.
     *
     * Order:
     * 1) Recommended (featured)
     * 2) Sale
     * 3) Discounted price (price shown by price plugins, no WC sale price)
     * 4) YITH promotion rule
     * 5) YITH dynamic pricing rule
     * 6) Multipack
     * 7) New
     * 8) Bestseller
     * 9) Stock state
     * 10) Category badges
     * 11) Product-specific badges
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     */
    private function collect_badges_for_product( \WC_Product $product, array $options ): array {
        $list = [];

        $list = array_merge( $list, $this->get_featured_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_sale_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_price_drop_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_yith_promo_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_yith_dynamic_pricing_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_multipack_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_new_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_bestseller_badge( $product, $options ) );
        $list = array_merge( $list, $this->get_stock_badges( $product, $options ) );
        $list = array_merge( $list, $this->get_category_badges( $product ) );
        $list = array_merge( $list, $this->get_product_badges( $product ) );

        return $this->deduplicate_badges( $list );
    }

    /**
     * Deduplicate badges by (type|label) pair.
     *
     * @param array<int,array<string,mixed>> $badges Badges.
     *
     * @return array<int,array<string,mixed>>
     */
    private function deduplicate_badges( array $badges ): array {
        $seen = [];
        $out  = [];

        foreach ( $badges as $badge ) {
            $key = ( $badge['type'] ?? '' ) . '|' . ( $badge['label'] ?? '' );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $out[]        = $badge;
        }

        return $out;
    }

    /**
     * Featured (Recommended) badge based on _featured flag.
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://docs.woocommerce.com/wc-apidocs/class-WC_Product.html#_is_featured
     */
    private function get_featured_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['featured'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        if ( ! $product->is_featured() ) {
            return [];
        }

        return [
            [
                'type'       => 'featured',
                'source'     => 'auto',
                'label'      => (string) $conf['label'],
                'bg_color'   => (string) $conf['bg_color'],
                'text_color' => (string) $conf['text_color'],
            ],
        ];
    }

    /**
     * Sale badge (label / percent / amount).
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://woocommerce.com/wc-apidocs/class-WC_Product.html#_is_on_sale
     * @link https://woocommerce.com/wc-apidocs/function-wc_price.html
     * @link https://woocommerce.com/wc-apidocs/function-wc_get_price_to_display.html
     */
    private function get_sale_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['sale'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        if ( ! $product->is_on_sale() ) {
            return [];
        }

        $mode = $conf['mode'] ?? 'percent';
        if ( 'hidden' === $mode ) {
            return [];
        }

        $label = (string) $conf['label'];

        [ $regular, $sale ] = $this->get_sale_price_pair( $product, (string) $mode );

        if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
            $mode = 'label';
        }

        // -15 %
        if ( 'percent' === $mode && $regular > 0 && $sale > 0 ) {
            $percent = round( ( 1 - ( $sale / $regular ) ) * 100 );
            $label   = sprintf( '-%d %%', (int) $percent );

            // -5,00 €
        } elseif ( 'amount' === $mode && $regular > 0 && $sale > 0 ) {
            $amount = $regular - $sale;

            // Plain text with currency symbol (no <span> markup; entities survive esc_html()).
            $amount_display = wc_price(
                $amount,
                [
                    'in_span' => false,
                ]
            );

            $label = '-' . $amount_display;
        }

        return [
            [
                'type'       => 'sale',
                'source'     => 'auto',
                'label'      => $label,
                'bg_color'   => (string) $conf['bg_color'],
                'text_color' => (string) $conf['text_color'],
            ],
        ];
    }

    /**
     * "Discounted price" badge – discount visible in the final price HTML.
     *
     * Plugin-agnostic: the decision is made from the price HTML the customer
     * actually sees (after all price plugins such as YITH Dynamic Pricing have
     * filtered it), compared with the regular price. Products that are on sale
     * (the "Sale" badge condition) are skipped.
     *
     * Label placeholders:
     * - {percent} discount in percent, rounded to a whole number (e.g. "25"),
     * - {price}   saved amount with 2 decimals and currency (e.g. "5,00 €").
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     */
    private function get_price_drop_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['price_drop'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        // Products on sale are handled by the "Sale" badge.
        if ( $product->is_on_sale() ) {
            return [];
        }

        $product_id = (int) $product->get_id();
        if ( ! array_key_exists( $product_id, self::$displayed_discount_cache ) ) {
            self::$displayed_discount_cache[ $product_id ] = $this->detect_displayed_discount( $product );
        }

        $discount = self::$displayed_discount_cache[ $product_id ];
        if ( null === $discount ) {
            return [];
        }

        $min_discount = isset( $conf['min_discount'] ) ? (float) $conf['min_discount'] : 0.0;
        if ( $min_discount > 0 && $discount['percent'] < $min_discount ) {
            return [];
        }

        $label = trim( (string) ( $conf['label'] ?? '' ) );
        if ( '' === $label ) {
            $label = '-{percent} %';
        }

        $percent = (int) round( $discount['percent'] );

        // Avoid "-0 %" for tiny discounts (e.g. rounding differences of price plugins).
        if ( $percent < 1 && false !== strpos( $label, '{percent}' ) ) {
            return [];
        }

        $label = str_replace(
            [ '{percent}', '{price}' ],
            [ (string) $percent, $this->format_plain_price( $discount['amount'] ) ],
            $label
        );

        return [
            [
                'type'       => 'price_drop',
                'source'     => 'auto',
                'label'      => $label,
                'bg_color'   => (string) ( $conf['bg_color'] ?? '' ),
                'text_color' => (string) ( $conf['text_color'] ?? '' ),
            ],
        ];
    }

    /**
     * Detect a discount from the final price HTML of a product.
     *
     * The lowest amount in the price HTML (price suffix excluded) is compared
     * with the regular display price. This does not depend on <del>/<ins>
     * markup, because price plugins use configurable formats (YITH default is
     * "<del>%original_price%</del> %discounted_price%" without <ins>).
     * Variable products compare the lowest displayed amount with the lowest
     * regular variation price ("from" discount).
     *
     * @param \WC_Product $product Product.
     *
     * @return array{regular:float,price:float,amount:float,percent:float}|null Null when no discount is displayed.
     *
     * @link https://woocommerce.github.io/code-reference/classes/WC-Product.html#method_get_price_html
     */
    private function detect_displayed_discount( \WC_Product $product ): ?array {
        $decimals = wc_get_price_decimals();

        if ( $product instanceof \WC_Product_Variable ) {
            $regular = (float) $product->get_variation_regular_price( 'min', true );
        } else {
            $raw_regular = $product->get_regular_price();
            if ( '' === $raw_regular ) {
                return null;
            }
            $regular = (float) wc_get_price_to_display( $product, [ 'price' => (float) $raw_regular ] );
        }

        // Compare at the precision the shop displays prices with.
        $regular = round( $regular, $decimals );
        if ( $regular <= 0 ) {
            return null;
        }

        $amounts = $this->extract_price_amounts( (string) $product->get_price_html() );
        if ( empty( $amounts ) ) {
            return null;
        }

        $shown = round( min( $amounts ), $decimals );
        if ( $shown <= 0 || $shown >= $regular ) {
            return null;
        }

        return [
            'regular' => $regular,
            'price'   => $shown,
            'amount'  => $regular - $shown,
            'percent' => ( 1 - ( $shown / $regular ) ) * 100,
        ];
    }

    /**
     * Extract numeric amounts from WooCommerce price HTML.
     *
     * Reads every ".woocommerce-Price-amount" element (markup produced by wc_price())
     * except those inside ".woocommerce-price-suffix" – the suffix may contain a
     * lower price excluding tax (e.g. "8,13 € excl. VAT"), which is not a discount.
     *
     * @param string $html Price HTML.
     *
     * @return float[] Positive amounts found in the HTML.
     *
     * @link https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-formatting-functions.html#function_wc_price
     */
    private function extract_price_amounts( string $html ): array {
        if ( '' === trim( $html ) || false === stripos( $html, 'woocommerce-Price-amount' ) || ! class_exists( '\DOMDocument' ) ) {
            return [];
        }

        $previous = libxml_use_internal_errors( true );

        $dom    = new \DOMDocument();
        $loaded = $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
            LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            return [];
        }

        $xpath = new \DOMXPath( $dom );
        $nodes = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-Price-amount ')]"
            . "[not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' woocommerce-price-suffix ')])]"
        );

        if ( false === $nodes ) {
            return [];
        }

        $amounts = [];
        foreach ( $nodes as $node ) {
            $value = $this->parse_price_text( (string) $node->textContent );
            if ( null !== $value && $value > 0 ) {
                $amounts[] = $value;
            }
        }

        return $amounts;
    }

    /**
     * Parse a formatted price text (e.g. "1 234,50 €") using shop separators.
     *
     * @param string $text Price text (entities already decoded by DOM).
     *
     * @return float|null
     *
     * @link https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-core-functions.html#function_wc_get_price_decimal_separator
     */
    private function parse_price_text( string $text ): ?float {
        $decimal  = html_entity_decode( (string) wc_get_price_decimal_separator(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $thousand = html_entity_decode( (string) wc_get_price_thousand_separator(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        if ( '' !== $thousand && $thousand !== $decimal ) {
            $text = str_replace( $thousand, '', $text );
        }

        // Remove any whitespace (incl. non-breaking spaces used as thousand separators).
        $text = (string) preg_replace( '/[\s\x{00A0}\x{202F}]+/u', '', $text );

        if ( '' !== $decimal && '.' !== $decimal ) {
            $text = str_replace( $decimal, '.', $text );
        }

        if ( 1 !== preg_match( '/\d+(?:\.\d+)?/', $text, $matches ) ) {
            return null;
        }

        return (float) $matches[0];
    }

    /**
     * Format an amount as plain text with currency and 2 decimals (e.g. "5,00 €").
     *
     * @param float $amount Amount.
     *
     * @return string Plain text (no HTML, entities decoded; escaped on output).
     */
    private function format_plain_price( float $amount ): string {
        $html = wc_price(
            $amount,
            [
                'decimals' => 2,
                'in_span'  => false,
            ]
        );

        return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }

    /**
     * Resolve display regular/sale price pair used for the sale badge value.
     *
     * Simple products use their own prices. Variable products have no own
     * regular/sale price, so the variation with the biggest discount is used
     * (biggest percent for "percent" mode, biggest saved amount for "amount" mode).
     * Prices are converted with wc_get_price_to_display() / display variation prices,
     * so they match what the customer sees (taxes, rounding).
     *
     * @param \WC_Product $product Product.
     * @param string      $mode    Sale display mode (percent|amount|label).
     *
     * @return array{0:float,1:float} [ regular, sale ]; zeros when not resolvable.
     *
     * @link https://woocommerce.github.io/code-reference/classes/WC-Product-Variable.html#method_get_variation_prices
     * @link https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-product-functions.html#function_wc_get_price_to_display
     */
    private function get_sale_price_pair( \WC_Product $product, string $mode ): array {
        if ( $product instanceof \WC_Product_Variable ) {
            $prices = $product->get_variation_prices( true );

            $best_regular = 0.0;
            $best_sale    = 0.0;
            $best_score   = 0.0;

            foreach ( (array) ( $prices['regular_price'] ?? [] ) as $variation_id => $regular_raw ) {
                $regular = (float) $regular_raw;
                $active  = isset( $prices['price'][ $variation_id ] ) ? (float) $prices['price'][ $variation_id ] : 0.0;

                if ( $regular <= 0 || $active <= 0 || $active >= $regular ) {
                    continue;
                }

                $score = 'amount' === $mode ? $regular - $active : 1 - ( $active / $regular );

                if ( $score > $best_score ) {
                    $best_score   = $score;
                    $best_regular = $regular;
                    $best_sale    = $active;
                }
            }

            return [ $best_regular, $best_sale ];
        }

        $raw_regular = $product->get_regular_price();
        $raw_sale    = $product->get_sale_price();

        // Without both prices a discount cannot be calculated (e.g. grouped products).
        if ( '' === $raw_regular || '' === $raw_sale ) {
            return [ 0.0, 0.0 ];
        }

        return [
            (float) wc_get_price_to_display( $product, [ 'price' => (float) $raw_regular ] ),
            (float) wc_get_price_to_display( $product, [ 'price' => (float) $raw_sale ] ),
        ];
    }

    /**
     * "New" badge based on product creation date and configured days.
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://docs.woocommerce.com/wc-apidocs/class-WC_Data.html#_get_date_created
     * @link https://developer.wordpress.org/reference/functions/current_time/
     */
    private function get_new_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['new'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        $created = $product->get_date_created();
        if ( ! $created ) {
            return [];
        }

        $days      = isset( $conf['days'] ) ? (int) $conf['days'] : 14;
        $timestamp = $created->getTimestamp();
        // Both values are real UTC timestamps (current_time( 'timestamp' ) would add the site offset).
        $diff_days = ( time() - $timestamp ) / DAY_IN_SECONDS;

        if ( $diff_days > $days ) {
            return [];
        }

        return [
            [
                'type'       => 'new',
                'source'     => 'auto',
                'label'      => (string) $conf['label'],
                'bg_color'   => (string) $conf['bg_color'],
                'text_color' => (string) $conf['text_color'],
            ],
        ];
    }

    /**
     * Bestseller badge based on top-selling products.
     *
     * Uses a transient cache keyed by configured "count".
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://docs.woocommerce.com/wc-apidocs/function-wc_get_products.html
     * @link https://developer.wordpress.org/reference/functions/get_transient/
     * @link https://developer.wordpress.org/reference/functions/set_transient/
     */
    private function get_bestseller_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['bestseller'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        $count = isset( $conf['count'] ) ? max( 1, (int) $conf['count'] ) : 20;

        $cache = get_transient( MERINEO_PB_TRANSIENT_BESTSELLERS );
        // "schema" invalidates caches built by older versions (which used a wrong ordering).
        $cache_schema = 2;

        if (
            ! is_array( $cache )
            || (int) ( $cache['top_count'] ?? 0 ) !== $count
            || (int) ( $cache['schema'] ?? 0 ) !== $cache_schema
        ) {
            /*
             * wc_get_products() does not map "popularity" to any ordering; unknown
             * orderby values are passed to WP_Query, which falls back to post date.
             * Order explicitly by the "total_sales" meta maintained by WooCommerce.
             * Note: a custom "meta_query" is dropped by WC_Data_Store_WP::get_wp_query_args(),
             * so products that were never sold are filtered out below instead.
             */
            $ids = wc_get_products(
                [
                    'limit'    => $count,
                    'status'   => [ 'publish' ],
                    'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- result cached in a transient for a day.
                    'orderby'  => 'meta_value_num',
                    'order'    => 'DESC',
                    'return'   => 'ids',
                ]
            );

            if ( ! is_array( $ids ) ) {
                $ids = [];
            }

            $ids = array_values(
                array_filter(
                    array_map( 'intval', $ids ),
                    static function ( int $id ): bool {
                        return (int) get_post_meta( $id, 'total_sales', true ) > 0;
                    }
                )
            );

            $cache = [
                'schema'      => $cache_schema,
                'top_count'   => $count,
                'product_ids' => $ids,
            ];

            set_transient( MERINEO_PB_TRANSIENT_BESTSELLERS, $cache, DAY_IN_SECONDS );
        }

        $ids = $cache['product_ids'] ?? [];
        if ( ! in_array( $product->get_id(), $ids, true ) ) {
            return [];
        }

        return [
            [
                'type'       => 'bestseller',
                'source'     => 'auto',
                'label'      => (string) $conf['label'],
                'bg_color'   => (string) $conf['bg_color'],
                'text_color' => (string) $conf['text_color'],
            ],
        ];
    }

    /**
     * Stock-related badges: out of stock, in stock, backorder.
     *
     * @param \WC_Product         $product Product.
     * @param array<string,mixed> $options Settings.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://docs.woocommerce.com/wc-apidocs/class-WC_Product.html#_get_stock_status
     */
    private function get_stock_badges( \WC_Product $product, array $options ): array {
        $status = $product->get_stock_status();
        $out    = [];

        if ( 'outofstock' === $status ) {
            $conf = $options['automatic']['outofstock'] ?? null;
            if ( is_array( $conf ) && ! empty( $conf['enabled'] ) ) {
                $out[] = [
                    'type'       => 'outofstock',
                    'source'     => 'auto',
                    'label'      => (string) $conf['label'],
                    'bg_color'   => (string) $conf['bg_color'],
                    'text_color' => (string) $conf['text_color'],
                ];
            }
        } elseif ( 'onbackorder' === $status ) {
            $conf = $options['automatic']['backorder'] ?? null;
            if ( is_array( $conf ) && ! empty( $conf['enabled'] ) ) {
                $out[] = [
                    'type'       => 'backorder',
                    'source'     => 'auto',
                    'label'      => (string) $conf['label'],
                    'bg_color'   => (string) $conf['bg_color'],
                    'text_color' => (string) $conf['text_color'],
                ];
            }
        } elseif ( 'instock' === $status ) {
            $conf = $options['automatic']['instock'] ?? null;
            if ( is_array( $conf ) && ! empty( $conf['enabled'] ) ) {
                $out[] = [
                    'type'       => 'instock',
                    'source'     => 'auto',
                    'label'      => (string) $conf['label'],
                    'bg_color'   => (string) $conf['bg_color'],
                    'text_color' => (string) $conf['text_color'],
                ];
            }
        }

        return $out;
    }

    /**
     * Category-level badges (inherited by all products in given categories).
     *
     * @param \WC_Product $product Product.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://docs.woocommerce.com/document/managing-product-taxonomies/
     * @link https://developer.wordpress.org/reference/functions/get_term_meta/
     */
    private function get_category_badges( \WC_Product $product ): array {
        $terms = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
        if ( empty( $terms ) ) {
            return [];
        }

        $meta_key = $this->get_category_meta_key();

        $badges = [];

        foreach ( $terms as $term_id ) {
            /** @link https://developer.wordpress.org/reference/functions/get_term_meta/ */
            $json = get_term_meta( $term_id, $meta_key, true );
            if ( ! is_string( $json ) || '' === $json ) {
                continue;
            }

            $data = json_decode( $json, true );
            if ( ! is_array( $data ) ) {
                continue;
            }

            foreach ( $data as $badge ) {
                if ( ! is_array( $badge ) || empty( $badge['label'] ) ) {
                    continue;
                }

                $badges[] = [
                    'type'       => 'category',
                    'source'     => 'category',
                    'label'      => (string) $badge['label'],
                    'bg_color'   => (string) sanitize_hex_color( (string) ( $badge['bg_color'] ?? '' ) ),
                    'text_color' => (string) sanitize_hex_color( (string) ( $badge['text_color'] ?? '' ) ),
                ];
            }
        }

        return $badges;
    }

    /**
     * Product-specific badges (set directly on product edit screen).
     *
     * @param \WC_Product $product Product.
     *
     * @return array<int,array<string,mixed>>
     *
     * @link https://developer.wordpress.org/reference/functions/get_post_meta/
     */
    private function get_product_badges( \WC_Product $product ): array {
        $json = get_post_meta( $product->get_id(), '_merineo_product_badges', true );
        if ( ! is_string( $json ) || '' === $json ) {
            return [];
        }

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return [];
        }

        $badges = [];

        foreach ( $data as $badge ) {
            if ( ! is_array( $badge ) || empty( $badge['label'] ) ) {
                continue;
            }

            $badges[] = [
                'type'       => 'product',
                'source'     => 'product',
                'label'      => (string) $badge['label'],
                // Hex-only on output too: older versions stored product colors unvalidated.
                'bg_color'   => (string) sanitize_hex_color( (string) ( $badge['bg_color'] ?? '' ) ),
                'text_color' => (string) sanitize_hex_color( (string) ( $badge['text_color'] ?? '' ) ),
            ];
        }

        return $badges;
    }

    /**
     * YITH promo/eligible badge (BOGO, Special offer, Gift products).
     *
     * This is a lightweight, catalog-safe badge: it indicates that a promotion rule
     * applies to this product (scope match) and is currently valid for the current user
     * (rule->is_valid()). It does NOT attempt to simulate cart quantities/combination logic.
     *
     * Version: show only 1 promo badge (highest priority) per product.
     *
     * @param \WC_Product $product Product.
     * @param array       $options Plugin options.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.8
     */
    private function get_yith_promo_badge( \WC_Product $product, array $options ): array {
        if ( ! function_exists( 'ywdpd_get_price_rules' ) ) {
            return [];
        }

        $candidates = $this->get_yith_promo_candidates_for_product( $product );

        if ( empty( $candidates ) ) {
            return [];
        }

        $best_rule     = null;
        $best_priority = null;

        foreach ( $candidates as $rule ) {
            if ( ! is_object( $rule ) ) {
                continue;
            }

            if ( method_exists( $rule, 'is_valid' ) && true !== $rule->is_valid() ) {
                continue;
            }

            if ( method_exists( $rule, 'is_valid_to_apply' ) && true !== $rule->is_valid_to_apply( $product ) ) {
                continue;
            }

            $rule_id = $this->get_yith_rule_id( $rule );
            if ( '' === $rule_id ) {
                continue;
            }

            $key  = 'yith_rule_' . preg_replace( '/\D+/', '', $rule_id );
            $conf = $options['automatic'][ $key ] ?? null;

            if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
                continue;
            }

            $priority = null;
            if ( method_exists( $rule, 'get_priority' ) ) {
                $priority = (int) $rule->get_priority();
            }

            if ( null === $best_rule ) {
                $best_rule     = $rule;
                $best_priority = $priority;
                continue;
            }

            // Lower priority number usually means higher priority; we keep consistent with admin ordering.
            if ( null !== $priority && ( null === $best_priority || $priority < $best_priority ) ) {
                $best_rule     = $rule;
                $best_priority = $priority;
            }
        }

        if ( null === $best_rule ) {
            return [];
        }

        $rule_id = $this->get_yith_rule_id( $best_rule );
        if ( '' === $rule_id ) {
            return [];
        }

        $key  = 'yith_rule_' . preg_replace( '/\D+/', '', $rule_id );
        $conf = $options['automatic'][ $key ] ?? null;

        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        $label = isset( $conf['label'] ) ? trim( (string) $conf['label'] ) : '';
        if ( '' === $label && method_exists( $best_rule, 'get_name' ) ) {
            $label = trim( (string) $best_rule->get_name() );
        }

        if ( '' === $label ) {
            $label = __( 'Promotion', 'merineo-product-badges' );
        }

        $bg_color   = isset( $conf['bg_color'] ) ? (string) $conf['bg_color'] : '#111827';
        $text_color = isset( $conf['text_color'] ) ? (string) $conf['text_color'] : '#ffffff';

        return [
            [
                'type'       => 'yith_rule',
                'source'     => 'auto',
                'label'      => $label,
                'bg_color'   => $bg_color,
                'text_color' => $text_color,
            ],
        ];
    }

    /**
     * YITH catalog price-rule badge (Bulk, Global discount, Category discount).
     *
     * This uses YITH's "winning rule for product" resolution (priorities + schedule),
     * so it reflects real catalog pricing rule application.
     *
     * @param \WC_Product $product Product.
     * @param array       $options Plugin options.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.8
     */
    private function get_yith_dynamic_pricing_badge( \WC_Product $product, array $options ): array {
        // If YITH plugin is not active, skip quietly.
        if ( ! class_exists( '\\YWDPD_Frontend' ) || ! method_exists( '\\YWDPD_Frontend', 'get_instance' ) ) {
            return [];
        }

        $frontend = \YWDPD_Frontend::get_instance();
        if ( ! is_object( $frontend ) || ! method_exists( $frontend, 'get_valid_bulk_rule_for_product' ) ) {
            return [];
        }

        $rule = $frontend->get_valid_bulk_rule_for_product( $product );
        if ( ! is_object( $rule ) || ! method_exists( $rule, 'get_discount_mode' ) ) {
            return [];
        }

        $mode = (string) $rule->get_discount_mode();
        if ( ! in_array( $mode, [ 'bulk', 'discount_whole', 'category_discount' ], true ) ) {
            return [];
        }

        $rule_id = $this->get_yith_rule_id( $rule );
        if ( '' === $rule_id ) {
            return [];
        }

        $key  = 'yith_rule_' . preg_replace( '/\D+/', '', $rule_id );
        $conf = $options['automatic'][ $key ] ?? null;

        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        $label = isset( $conf['label'] ) ? trim( (string) $conf['label'] ) : '';
        if ( '' === $label && method_exists( $rule, 'get_name' ) ) {
            $label = trim( (string) $rule->get_name() );
        }

        if ( '' === $label ) {
            $label = __( 'Discount', 'merineo-product-badges' );
        }

        $bg_color   = isset( $conf['bg_color'] ) ? (string) $conf['bg_color'] : '#111827';
        $text_color = isset( $conf['text_color'] ) ? (string) $conf['text_color'] : '#ffffff';

        return [
            [
                'type'       => 'yith_rule',
                'source'     => 'auto',
                'label'      => $label,
                'bg_color'   => $bg_color,
                'text_color' => $text_color,
            ],
        ];
    }

    /**
     * Get YITH promo rule candidates for the given product using a lightweight, runtime index.
     *
     * @param \WC_Product $product Product.
     * @return array<int, object>
     *
     * @since 1.0.8
     */
    private function get_yith_promo_candidates_for_product( \WC_Product $product ): array {
        $index = $this->get_yith_promo_index();
        if ( empty( $index ) ) {
            return [];
        }

        $product_id = (int) $product->get_id();
        $rule_ids   = [];

        // Global promo rules.
        if ( ! empty( $index['global'] ) && is_array( $index['global'] ) ) {
            $rule_ids = array_merge( $rule_ids, $index['global'] );
        }

        // Product-specific promo rules.
        if ( ! empty( $index['products'][ $product_id ] ) && is_array( $index['products'][ $product_id ] ) ) {
            $rule_ids = array_merge( $rule_ids, $index['products'][ $product_id ] );
        }

        // Category-based promo rules.
        if ( ! empty( $index['categories'] ) && is_array( $index['categories'] ) ) {
            $cat_ids = wc_get_product_term_ids( $product_id, 'product_cat' );
            foreach ( $cat_ids as $cat_id ) {
                $cat_id = (int) $cat_id;
                if ( ! empty( $index['categories'][ $cat_id ] ) && is_array( $index['categories'][ $cat_id ] ) ) {
                    $rule_ids = array_merge( $rule_ids, $index['categories'][ $cat_id ] );
                }
            }
        }

        // Tag-based promo rules.
        if ( ! empty( $index['tags'] ) && is_array( $index['tags'] ) ) {
            $tag_ids = wc_get_product_term_ids( $product_id, 'product_tag' );
            foreach ( $tag_ids as $tag_id ) {
                $tag_id = (int) $tag_id;
                if ( ! empty( $index['tags'][ $tag_id ] ) && is_array( $index['tags'][ $tag_id ] ) ) {
                    $rule_ids = array_merge( $rule_ids, $index['tags'][ $tag_id ] );
                }
            }
        }

        $rule_ids = array_values( array_unique( array_map( 'intval', $rule_ids ) ) );
        if ( empty( $rule_ids ) ) {
            return [];
        }

        $rules = self::$yith_promo_rules;
        if ( empty( $rules ) ) {
            return [];
        }

        $out = [];
        foreach ( $rules as $rule ) {
            $rid = $this->get_yith_rule_id( $rule );
            if ( '' === $rid ) {
                continue;
            }
            $rid_int = (int) preg_replace( '/\D+/', '', $rid );
            if ( in_array( $rid_int, $rule_ids, true ) ) {
                $out[] = $rule;
            }
        }

        return $out;
    }

    /**
     * Build or return cached promo index (per request).
     *
     * @return array<string, mixed>
     *
     * @since 1.0.8
     */
    private function get_yith_promo_index(): array {
        if ( null !== self::$yith_promo_index && null !== self::$yith_promo_rules ) {
            return self::$yith_promo_index;
        }

        self::$yith_promo_index = [];
        self::$yith_promo_rules = [];

        if ( ! function_exists( 'ywdpd_get_price_rules' ) ) {
            return [];
        }

        $promo_modes = [ 'bogo', 'special_offer', 'gift_products' ];
        $rules       = ywdpd_get_price_rules( $promo_modes );

        if ( ! is_array( $rules ) || empty( $rules ) ) {
            return [];
        }

        self::$yith_promo_rules = $rules;

        $index = [
            'global'     => [],
            'products'   => [],
            'categories' => [],
            'tags'       => [],
        ];

        foreach ( $rules as $rule ) {
            if ( ! is_object( $rule ) || ! method_exists( $rule, 'get_discount_mode' ) ) {
                continue;
            }

            $mode = (string) $rule->get_discount_mode();
            if ( ! in_array( $mode, $promo_modes, true ) ) {
                continue;
            }

            $rule_id = $this->get_yith_rule_id( $rule );
            if ( '' === $rule_id ) {
                continue;
            }
            $rid = (int) preg_replace( '/\D+/', '', $rule_id );
            if ( $rid <= 0 ) {
                continue;
            }

            if ( ! method_exists( $rule, 'get_rule_for' ) ) {
                // Fallback: assume global.
                $index['global'][] = $rid;
                continue;
            }

            $rule_for = (string) $rule->get_rule_for();

            if ( 'all_products' === $rule_for ) {
                $index['global'][] = $rid;
                continue;
            }

            if ( 'specific_products' === $rule_for && method_exists( $rule, 'get_rule_for_product_list' ) ) {
                $ids = (array) $rule->get_rule_for_product_list();
                foreach ( $ids as $pid ) {
                    $pid = (int) $pid;
                    if ( $pid > 0 ) {
                        $index['products'][ $pid ][] = $rid;
                    }
                }
                continue;
            }

            if ( 'specific_categories' === $rule_for && method_exists( $rule, 'get_rule_for_categories_list' ) ) {
                $ids = (array) $rule->get_rule_for_categories_list();
                foreach ( $ids as $tid ) {
                    $tid = (int) $tid;
                    if ( $tid > 0 ) {
                        $index['categories'][ $tid ][] = $rid;
                    }
                }
                continue;
            }

            if ( 'specific_tag' === $rule_for && method_exists( $rule, 'get_rule_for_tags_list' ) ) {
                $ids = (array) $rule->get_rule_for_tags_list();
                foreach ( $ids as $tid ) {
                    $tid = (int) $tid;
                    if ( $tid > 0 ) {
                        $index['tags'][ $tid ][] = $rid;
                    }
                }
                continue;
            }

            // Unknown scope: fall back to global to avoid missing the rule completely.
            $index['global'][] = $rid;
        }

        // Normalize arrays.
        $index['global'] = array_values( array_unique( array_map( 'intval', $index['global'] ) ) );

        foreach ( [ 'products', 'categories', 'tags' ] as $bucket ) {
            foreach ( $index[ $bucket ] as $k => $vals ) {
                $index[ $bucket ][ $k ] = array_values( array_unique( array_map( 'intval', (array) $vals ) ) );
            }
        }

        self::$yith_promo_index = $index;

        return self::$yith_promo_index;
    }

    /**
     * Extract a rule ID from a YITH rule object.
     *
     * @param object $rule YITH rule.
     * @return string
     *
     * @since 1.0.8
     */
    private function get_yith_rule_id( object $rule ): string {
        if ( method_exists( $rule, 'get_id' ) ) {
            return (string) $rule->get_id();
        }

        if ( property_exists( $rule, 'id' ) ) {
            return (string) $rule->id;
        }

        return '';
    }

    /**
     * Multipack badge – integration with Merineo Multipack Discount.
     *
     * @param \WC_Product         $product Product instance.
     * @param array<string,mixed> $options All plugin options.
     *
     * @return array<int,array<string,string>>
     */
    private function get_multipack_badge( \WC_Product $product, array $options ): array {
        // If multipack plugin is not active, skip quietly.
        if ( ! class_exists( '\Merineo\Multipack_Discount\Common\Multipack_Helper' ) ) {
            return [];
        }

        $conf = $options['automatic']['multipack'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }

        $product_id = $product->get_id();

        // Check if product has active multipacks.
        $multipacks = \Merineo\Multipack_Discount\Common\Multipack_Helper::get_active_multipacks_for_product( $product_id );
        if ( ! is_array( $multipacks ) || empty( $multipacks ) ) {
            return [];
        }

        // Get maximum discount info (normalised to percent).
        $max = \Merineo\Multipack_Discount\Common\Multipack_Helper::get_max_discount_for_product( $product_id );
        if ( ! is_array( $max ) ) {
            return [];
        }

        $percent = isset( $max['percent'] ) ? (float) $max['percent'] : 0.0;
        if ( $percent <= 0 ) {
            return [];
        }

        // Apply "Minimum discount to show badge" threshold (in percent).
        $min_discount = isset( $conf['min_discount'] ) ? (float) $conf['min_discount'] : 0.0;
        if ( $min_discount > 0 && $percent < $min_discount ) {
            return [];
        }

        // Prepare label with {max_discount} placeholder support.
        $label = (string) ( $conf['label'] ?? '' );
        if ( '' === $label ) {
            $label = __( 'Multipack', 'merineo-product-badges' );
        }

        if ( false !== strpos( $label, '{max_discount}' ) ) {
            $placeholder_value = \Merineo\Multipack_Discount\Common\Multipack_Helper::format_max_discount_placeholder( $product_id );
            if ( '' !== $placeholder_value ) {
                $label = str_replace( '{max_discount}', $placeholder_value, $label );
            } else {
                // If we cannot format the value, just remove the placeholder.
                $label = str_replace( '{max_discount}', '', $label );
            }
        }

        return [
            [
                'type'       => 'multipack',
                'source'     => 'auto',
                'label'      => $label,
                'bg_color'   => (string) ( $conf['bg_color'] ?? '' ),
                'text_color' => (string) ( $conf['text_color'] ?? '' ),
            ],
        ];
    }

    /**
     * Get category meta key for current site.
     *
     * Must match the key used in Category_Meta to avoid overwriting /
     * sharing data between sites in a multisite network.
     *
     * @return string
     *
     * @link https://developer.wordpress.org/reference/functions/get_current_blog_id/
     * @link https://developer.wordpress.org/reference/functions/is_multisite/
     */
    private function get_category_meta_key(): string {
        $meta_key = 'merineo_product_badges';

        $blog_id = (int) get_current_blog_id();
        if ( is_multisite() && $blog_id > 0 ) {
            $meta_key .= '_' . $blog_id;
        }

        return $meta_key;
    }
}