<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Frontend;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Renderer {

    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function hooks(): void {
        add_filter( 'woocommerce_sale_flash', '__return_empty_string', 10 );
        add_action( 'wp', [ $this, 'register_output_hooks' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

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
            $callback = 'render_for_single';
            if ( 'loop' === $area ) {
                $callback = 'render_for_loop';
            } elseif ( 'custom' === $area ) {
                $callback = 'render_for_custom';
            }
            add_action( $hook, [ $this, $callback ], $priority );
        }
    }

    public function enqueue_styles(): void {
        if ( ! ( is_shop() || is_product() || is_product_category() || is_product_tag() ) ) {
            return;
        }
        wp_enqueue_style(
            'merineo-product-badges',
            MERINEO_PB_URL . 'assets/frontend/css/badges.css',
            [],
            MERINEO_PB_VERSION
        );

        $options       = $this->settings->all();
        $font_size     = (float) $options['general']['font_size'];
        $text_transform = $options['general']['text_transform'];
        $font_weight   = $options['general']['font_weight'];
        $letter_spacing = (float) $options['general']['letter_spacing'];
        $shadow        = ! empty( $options['general']['shadow_enabled'] );

        $css = '.merineo-badges-scope .merineo-badge{font-size:' . $font_size . 'px;text-transform:' . ( 'uppercase' === $text_transform ? 'uppercase' : 'none' ) . ';font-weight:' . ( 'bold' === $font_weight ? '700' : '400' ) . ';letter-spacing:' . $letter_spacing . 'px;';
        if ( $shadow ) {
            $css .= 'box-shadow:0 1px 2px rgba(0,0,0,0.15);';
        }
        $css .= '}';

        if ( ! empty( $options['css']['custom'] ) ) {
            $css .= "
" . $options['css']['custom'];
        }

        wp_add_inline_style( 'merineo-product-badges', $css );
    }

    public function render_for_single(): void {
        global $product;
        if ( ! $product instanceof \WC_Product ) {
            return;
        }
        $this->render_for_product( $product, 'single' );
    }

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

    private function render_for_product( \WC_Product $product, string $context ): void {
        $options = $this->settings->all();
        if ( empty( $options['general']['enabled'] ) ) {
            return;
        }
        $badges = $this->collect_badges_for_product( $product, $options );
        if ( empty( $badges ) ) {
            return;
        }
        $align = $options['general'][ $context ]['align'] ?? 'left';
        echo '<div class="merineo-badges-scope"><div class="merineo-badges merineo-badges--align-' . esc_attr( $align ) . '">';
        foreach ( $badges as $badge ) {
            $label      = $badge['label'] ?? '';
            $bg_color   = $badge['bg_color'] ?? '';
            $text_color = $badge['text_color'] ?? '';
            $type       = $badge['type'] ?? 'custom';
            $source     = $badge['source'] ?? 'custom';
            if ( '' === $label ) {
                continue;
            }
            $style = '';
            if ( $bg_color ) {
                $style .= 'background-color:' . $bg_color . ';';
            }
            if ( $text_color ) {
                $style .= 'color:' . $text_color . ';';
            }
            printf(
                '<span class="merineo-badge merineo-badge--type-%1$s merineo-badge--source-%2$s" style="%3$s">%4$s</span>',
                esc_attr( $type ),
                esc_attr( $source ),
                esc_attr( $style ),
                esc_html( $label )
            );
        }
        echo '</div></div>';
    }

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

    private function get_featured_badge( \WC_Product $product, array $options ): array {
        $conf = $options['automatic']['featured'] ?? null;
        if ( ! is_array( $conf ) || empty( $conf['enabled'] ) ) {
            return [];
        }
        if ( ! $product->is_featured() ) {
            return [];
        }
        return [[
            'type'       => 'featured',
            'source'     => 'auto',
            'label'      => (string) $conf['label'],
            'bg_color'   => (string) $conf['bg_color'],
            'text_color' => (string) $conf['text_color'],
        ]];
    }

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
        $label   = (string) $conf['label'];
        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_sale_price();
        if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
            $mode = 'label';
        }
        if ( 'percent' === $mode && $regular > 0 && $sale > 0 ) {
            $percent = round( ( 1 - ( $sale / $regular ) ) * 100 );
            $label   = sprintf( '-%d %%', (int) $percent );
        } elseif ( 'amount' === $mode && $regular > 0 && $sale > 0 ) {
            $amount = $regular - $sale;
            $label  = '-' . wc_price( $amount );
        }
        return [[
            'type'       => 'sale',
            'source'     => 'auto',
            'label'      => $label,
            'bg_color'   => (string) $conf['bg_color'],
            'text_color' => (string) $conf['text_color'],
        ]];
    }

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
        return [[
            'type'       => 'new',
            'source'     => 'auto',
            'label'      => (string) $conf['label'],
            'bg_color'   => (string) $conf['bg_color'],
            'text_color' => (string) $conf['text_color'],
        ]];
    }

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
        return [[
            'type'       => 'bestseller',
            'source'     => 'auto',
            'label'      => (string) $conf['label'],
            'bg_color'   => (string) $conf['bg_color'],
            'text_color' => (string) $conf['text_color'],
        ]];
    }

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
