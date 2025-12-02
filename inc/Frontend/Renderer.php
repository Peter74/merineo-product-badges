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
        // Remove default WooCommerce "Sale!" badge. Our plugin controls sale badges.
        // @link https://docs.woocommerce.com/document/hooks/
        add_filter( 'woocommerce_sale_flash', '__return_empty_string', 10 );

        // Register dynamic hooks for single / loop / custom placements.
        add_action( 'wp', [ $this, 'register_output_hooks' ] );

        // Enqueue frontend styles and inline CSS variables.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
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
        // Only load on product-related pages.
        if ( ! ( is_shop() || is_product() || is_product_category() || is_product_tag() ) ) {
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
        if ( ! empty( $options['css']['custom'] ) ) {
            $css .= "\n" . $options['css']['custom'];
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
     * 3) New
     * 4) Bestseller
     * 5) Stock state
     * 6) Category badges
     * 7) Product-specific badges
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

        $label        = (string) $conf['label'];
        $raw_regular  = $product->get_regular_price();
        $raw_sale     = $product->get_sale_price();

        // Bez platných cien nemá zmysel počítať zľavu.
        if ( '' === $raw_regular || '' === $raw_sale ) {
            $mode = 'label';
        }

        // Pre výpočty použijeme Woo way: wc_get_price_to_display()
        // aby sedeli s tým, čo vidí zákazník (dane, zaokrúhlenie atď.).
        $regular = ( '' !== $raw_regular )
            ? (float) wc_get_price_to_display( $product, [ 'price' => (float) $raw_regular ] )
            : 0.0;

        $sale = ( '' !== $raw_sale )
            ? (float) wc_get_price_to_display( $product, [ 'price' => (float) $raw_sale ] )
            : 0.0;

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

            // Text s menou bez <span>.
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
        $diff_days = ( current_time( 'timestamp' ) - $timestamp ) / DAY_IN_SECONDS;

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
        if ( ! is_array( $cache ) || (int) ( $cache['top_count'] ?? 0 ) !== $count ) {
            $ids = wc_get_products(
                [
                    'limit'   => $count,
                    'status'  => [ 'publish' ],
                    'orderby' => 'popularity',
                    'order'   => 'DESC',
                    'return'  => 'ids',
                ]
            );

            if ( ! is_array( $ids ) ) {
                $ids = [];
            }

            $cache = [
                'top_count'   => $count,
                'product_ids' => array_map( 'intval', $ids ),
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

        $badges = [];

        foreach ( $terms as $term_id ) {
            $json = get_term_meta( $term_id, 'merineo_product_badges', true );
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
                    'bg_color'   => (string) ( $badge['bg_color'] ?? '' ),
                    'text_color' => (string) ( $badge['text_color'] ?? '' ),
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
                'bg_color'   => (string) ( $badge['bg_color'] ?? '' ),
                'text_color' => (string) ( $badge['text_color'] ?? '' ),
            ];
        }

        return $badges;
    }
}