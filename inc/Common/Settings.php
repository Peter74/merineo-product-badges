<?php
/**
 * Settings handler.
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

namespace Merineo\ProductBadges\Common;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    private string $option_name = MERINEO_PB_OPTION_NAME;

    public function bootstrap_defaults(): void {
        $current = get_option( $this->option_name );
        if ( false === $current ) {
            add_option( $this->option_name, $this->get_default_settings() );
        }
    }

    public function register(): void {
        register_setting(
            'merineo_product_badges',
            $this->option_name,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'show_in_rest'      => false,
                'default'           => $this->get_default_settings(),
            ]
        );
    }

    public function all(): array {
        $settings = get_option( $this->option_name, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }
        return wp_parse_args( $settings, $this->get_default_settings() );
    }

    public function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) {
            return $this->get_default_settings();
        }

        $defaults = $this->get_default_settings();
        $output   = $defaults;

        $general                      = $input['general'] ?? [];
        $output['general']['enabled'] = ! empty( $general['enabled'] );

        foreach ( [ 'single', 'loop', 'custom' ] as $area ) {
            $src = $general[ $area ] ?? [];
            $output['general'][ $area ]['hook']        = isset( $src['hook'] ) ? sanitize_text_field( (string) $src['hook'] ) : '';
            $output['general'][ $area ]['custom_hook'] = isset( $src['custom_hook'] ) ? sanitize_text_field( (string) $src['custom_hook'] ) : '';
            $output['general'][ $area ]['priority']    = isset( $src['priority'] ) ? max( 1, intval( $src['priority'] ) ) : $defaults['general'][ $area ]['priority'];
            $output['general'][ $area ]['align']       = ( isset( $src['align'] ) && in_array( $src['align'], [ 'left', 'right' ], true ) ) ? $src['align'] : 'left';
        }

        $output['general']['font_size']      = isset( $general['font_size'] ) ? max( 8, floatval( $general['font_size'] ) ) : $defaults['general']['font_size'];
        $output['general']['text_transform'] = ( isset( $general['text_transform'] ) && in_array( $general['text_transform'], [ 'none', 'uppercase' ], true ) )
            ? $general['text_transform']
            : 'none';
        $output['general']['font_weight']    = ( isset( $general['font_weight'] ) && in_array( $general['font_weight'], [ 'normal', 'bold' ], true ) )
            ? $general['font_weight']
            : 'normal';
        $output['general']['letter_spacing'] = isset( $general['letter_spacing'] ) ? floatval( $general['letter_spacing'] ) : 0.0;
        $output['general']['shadow_enabled'] = ! empty( $general['shadow_enabled'] );
        $output['general']['style_variant']  = isset( $general['style_variant'] ) ? sanitize_text_field( (string) $general['style_variant'] ) : 'default';

        $automatic = $input['automatic'] ?? [];

        $output['automatic']['new'] = $this->sanitize_auto_badge(
            $automatic['new'] ?? [],
            $defaults['automatic']['new'],
            True
        );

        foreach ( [ 'featured', 'sale', 'outofstock', 'instock', 'backorder', 'bestseller' ] as $key ) {
            $output['automatic'][ $key ] = $this->sanitize_auto_badge(
                $automatic[ $key ] ?? [],
                $defaults['automatic'][ $key ],
                'bestseller' === $key
            );
        }

        $sale_mode                           = $automatic['sale']['mode'] ?? 'label';
        $allowed_sale_modes                  = [ 'hidden', 'label', 'percent', 'amount' ];
        $output['automatic']['sale']['mode'] = in_array( $sale_mode, $allowed_sale_modes, true ) ? $sale_mode : 'label';

        $new_days                           = $automatic['new']['days'] ?? $defaults['automatic']['new']['days'];
        $output['automatic']['new']['days'] = max( 1, intval( $new_days ) );

        $bestseller_count                           = $automatic['bestseller']['count'] ?? $defaults['automatic']['bestseller']['count'];
        $output['automatic']['bestseller']['count'] = max( 1, intval( $bestseller_count ) );

        $output['css']['custom'] = isset( $input['css']['custom'] ) ? (string) $input['css']['custom'] : '';

        return $output;
    }

    private function sanitize_auto_badge( array $src, array $defaults, bool $has_extra = false ): array {
        $target               = $defaults;
        $target['enabled']    = ! empty( $src['enabled'] );
        $target['label']      = isset( $src['label'] ) ? sanitize_text_field( (string) $src['label'] ) : $defaults['label'];
        $target['bg_color']   = isset( $src['bg_color'] ) ? sanitize_hex_color( (string) $src['bg_color'] ) : $defaults['bg_color'];
        $target['text_color'] = isset( $src['text_color'] ) ? sanitize_hex_color( (string) $src['text_color'] ) : $defaults['text_color'];

        if ( $has_extra && isset( $defaults['days'] ) ) {
            $target['days'] = isset( $src['days'] ) ? max( 1, intval( $src['days'] ) ) : $defaults['days'];
        }
        if ( $has_extra && isset( $defaults['count'] ) ) {
            $target['count'] = isset( $src['count'] ) ? max( 1, intval( $src['count'] ) ) : $defaults['count'];
        }

        return $target;
    }

    public function get_default_settings(): array {
        return [
            'general'   => [
                'enabled' => true,
                'single'  => [
                    'hook'        => 'woocommerce_single_product_summary',
                    'custom_hook' => '',
                    'priority'    => 6,
                    'align'       => 'left',
                ],
                'loop'    => [
                    'hook'        => 'woocommerce_before_shop_loop_item_title',
                    'custom_hook' => '',
                    'priority'    => 10,
                    'align'       => 'left',
                ],
                'custom'  => [
                    'hook'        => '',
                    'custom_hook' => '',
                    'priority'    => 10,
                    'align'       => 'left',
                ],
                'font_size'      => 14.0,
                'text_transform' => 'none',
                'font_weight'    => 'normal',
                'letter_spacing' => 0.0,
                'shadow_enabled' => false,
                'style_variant'  => 'default',
            ],
            'automatic' => [
                'new'        => [
                    'enabled'    => true,
                    'label'      => __( 'New', 'merineo-product-badges' ),
                    'bg_color'   => '#2563eb',
                    'text_color' => '#ffffff',
                    'days'       => 14,
                ],
                'featured'   => [
                    'enabled'    => true,
                    'label'      => __( 'Recommended', 'merineo-product-badges' ),
                    'bg_color'   => '#10b981',
                    'text_color' => '#ffffff',
                ],
                'sale'       => [
                    'enabled'    => true,
                    'label'      => __( 'Sale', 'merineo-product-badges' ),
                    'bg_color'   => '#dc2626',
                    'text_color' => '#ffffff',
                    'mode'       => 'percent',
                ],
                'outofstock' => [
                    'enabled'    => true,
                    'label'      => __( 'Out of stock', 'merineo-product-badges' ),
                    'bg_color'   => '#4b5563',
                    'text_color' => '#ffffff',
                ],
                'instock'    => [
                    'enabled'    => false,
                    'label'      => __( 'In stock', 'merineo-product-badges' ),
                    'bg_color'   => '#16a34a',
                    'text_color' => '#ffffff',
                ],
                'backorder'  => [
                    'enabled'    => true,
                    'label'      => __( 'Backorder', 'merineo-product-badges' ),
                    'bg_color'   => '#ca8a04',
                    'text_color' => '#ffffff',
                ],
                'bestseller' => [
                    'enabled'    => true,
                    'label'      => __( 'Bestseller', 'merineo-product-badges' ),
                    'bg_color'   => '#d97706',
                    'text_color' => '#ffffff',
                    'count'      => 20,
                ],
            ],
            'css'       => [
                'custom' => '',
            ],
        ];
    }
}
