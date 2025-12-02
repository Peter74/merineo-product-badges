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

/**
 * Handles plugin settings registration, defaults and sanitization.
 *
 * Settings are stored as a single option array under MERINEO_PB_OPTION_NAME.
 * The settings page is split into multiple tabs, so the sanitization callback
 * must support partial updates (only fields from the current tab are posted).
 */
class Settings {

    /**
     * Option name used to store all plugin settings.
     *
     * @var string
     */
    private string $option_name = MERINEO_PB_OPTION_NAME;

    /**
     * Ensure default options are present on first run.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/add_option/
     * @link https://developer.wordpress.org/reference/functions/get_option/
     */
    public function bootstrap_defaults(): void {
        $current = get_option( $this->option_name );
        if ( false === $current ) {
            add_option( $this->option_name, $this->get_default_settings() );
        }
    }

    /**
     * Register settings with the Settings API.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/register_setting/
     */
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

    /**
     * Get all settings, merged with defaults.
     *
     * @return array<string,mixed>
     *
     * @link https://developer.wordpress.org/reference/functions/get_option/
     * @link https://developer.wordpress.org/reference/functions/wp_parse_args/
     */
    public function all(): array {
        $settings = get_option( $this->option_name, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        return wp_parse_args( $settings, $this->get_default_settings() );
    }

    /**
     * Sanitize all plugin settings.
     *
     * IMPORTANT:
     * - The settings UI is split into multiple tabs (Layout / Automatic / Design / Advanced).
     * - Each submit posts ONLY fields from the current tab.
     * - To avoid resetting other sections, we must:
     *   1) load existing stored settings from the DB,
     *   2) merge them with defaults,
     *   3) merge the current input on top,
     *   4) sanitize the merged result.
     *
     * @param mixed $raw_input Raw input from Settings API (should be array).
     *
     * @return array<string,mixed> Sanitized options.
     *
     * @link https://developer.wordpress.org/reference/functions/register_setting/
     * @link https://developer.wordpress.org/reference/functions/sanitize_text_field/
     */
    public function sanitize_settings( $raw_input ): array {
        // Normalize raw input to array.
        $input = is_array( $raw_input ) ? $raw_input : [];

        $defaults = $this->get_default_settings();

        // Load currently stored options to support partial updates (tabs).
        $stored = get_option( $this->option_name, [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        /*
         * Merge order:
         * - start with defaults,
         * - overlay stored values,
         * - overlay current input from the submitted tab.
         */
        $merged = array_replace_recursive( $defaults, $stored, $input );

        // Start sanitized output with defaults to ensure full structure exists.
        $output = $defaults;

        /*
         * GENERAL SECTION
         * ---------------------------------------------------------------------
         */
        $general = $merged['general'] ?? [];

        // Global toggle.
        $output['general']['enabled'] = ! empty( $general['enabled'] );

        // Placement settings: single / loop / custom.
        foreach ( [ 'single', 'loop', 'custom' ] as $area ) {
            $src = $general[ $area ] ?? [];

            $output['general'][ $area ]['hook'] = isset( $src['hook'] )
                ? sanitize_text_field( (string) $src['hook'] )
                : $defaults['general'][ $area ]['hook'];

            $output['general'][ $area ]['custom_hook'] = isset( $src['custom_hook'] )
                ? sanitize_text_field( (string) $src['custom_hook'] )
                : $defaults['general'][ $area ]['custom_hook'];

            $output['general'][ $area ]['priority'] = isset( $src['priority'] )
                ? max( 1, (int) $src['priority'] )
                : $defaults['general'][ $area ]['priority'];

            $align = $src['align'] ?? $defaults['general'][ $area ]['align'];
            $output['general'][ $area ]['align'] = in_array( $align, [ 'left', 'right' ], true ) ? $align : 'left';
        }

        // Typography + high-level style.
        $output['general']['font_size'] = isset( $general['font_size'] )
            ? max( 8.0, (float) $general['font_size'] )
            : $defaults['general']['font_size'];

        $text_transform = $general['text_transform'] ?? $defaults['general']['text_transform'];
        $output['general']['text_transform'] = in_array( $text_transform, [ 'none', 'uppercase' ], true )
            ? $text_transform
            : 'none';

        $font_weight = $general['font_weight'] ?? $defaults['general']['font_weight'];
        $output['general']['font_weight'] = in_array( $font_weight, [ 'normal', 'bold' ], true )
            ? $font_weight
            : 'normal';

        $output['general']['letter_spacing'] = isset( $general['letter_spacing'] )
            ? (float) $general['letter_spacing']
            : $defaults['general']['letter_spacing'];

        $output['general']['shadow_enabled'] = ! empty( $general['shadow_enabled'] );

        // Style variant, outline, layout.
        $allowed_variants = [
            'pill',
            'square',
            'rounded',
            'circle',
            'ribbon-vertical',
            'ribbon-corner',
            'tag',
        ];

        $variant = $general['style_variant'] ?? $defaults['general']['style_variant'];

        $output['general']['style_variant'] = in_array( $variant, $allowed_variants, true )
            ? $variant
            : $defaults['general']['style_variant'];

        $output['general']['style_outline'] = ! empty( $general['style_outline'] );

        $layout = $general['layout'] ?? $defaults['general']['layout'];
        $output['general']['layout'] = in_array( $layout, [ 'inline', 'stacked' ], true ) ? $layout : 'inline';

        /*
         * AUTOMATIC BADGES SECTION
         * ---------------------------------------------------------------------
         */
        $automatic = $merged['automatic'] ?? [];

        // "New" badge (has "days").
        $output['automatic']['new'] = $this->sanitize_auto_badge(
            $automatic['new'] ?? [],
            $defaults['automatic']['new'],
            true
        );

        // Other badges (some may have "count").
        foreach ( [ 'featured', 'sale', 'outofstock', 'instock', 'backorder', 'bestseller' ] as $key ) {
            $output['automatic'][ $key ] = $this->sanitize_auto_badge(
                $automatic[ $key ] ?? [],
                $defaults['automatic'][ $key ],
                'bestseller' === $key
            );
        }

        // Sale display mode.
        $sale               = $automatic['sale'] ?? [];
        $sale_mode          = $sale['mode'] ?? $defaults['automatic']['sale']['mode'];
        $allowed_sale_modes = [ 'hidden', 'label', 'percent', 'amount' ];

        $output['automatic']['sale']['mode'] = in_array( $sale_mode, $allowed_sale_modes, true )
            ? $sale_mode
            : $defaults['automatic']['sale']['mode'];

        // New badge visibility duration.
        $new_days = $automatic['new']['days'] ?? $output['automatic']['new']['days'] ?? $defaults['automatic']['new']['days'];
        $output['automatic']['new']['days'] = max( 1, (int) $new_days );

        // Bestseller count.
        $bestseller_count = $automatic['bestseller']['count'] ?? $output['automatic']['bestseller']['count'] ?? $defaults['automatic']['bestseller']['count'];
        $output['automatic']['bestseller']['count'] = max( 1, (int) $bestseller_count );

        /*
         * CSS SECTION
         * ---------------------------------------------------------------------
         */
        $css = $merged['css'] ?? [];

        $output['css']['custom'] = isset( $css['custom'] )
            ? (string) $css['custom']
            : $defaults['css']['custom'];

        return $output;
    }

    /**
     * Sanitize configuration for a single automatic badge type.
     *
     * @param array<string,mixed> $src       Source values (possibly partial).
     * @param array<string,mixed> $defaults  Default values for this badge.
     * @param bool                $has_extra Whether badge has extra numeric field (days or count).
     *
     * @return array<string,mixed> Sanitized badge configuration.
     *
     * @link https://developer.wordpress.org/reference/functions/sanitize_hex_color/
     * @link https://developer.wordpress.org/reference/functions/sanitize_text_field/
     */
    private function sanitize_auto_badge( array $src, array $defaults, bool $has_extra = false ): array {
        $target            = $defaults;
        $target['enabled'] = ! empty( $src['enabled'] );

        $target['label'] = isset( $src['label'] )
            ? sanitize_text_field( (string) $src['label'] )
            : $defaults['label'];

        // Colors: use sanitize_hex_color() and fall back to defaults on invalid values.
        $bg_color = isset( $src['bg_color'] ) ? sanitize_hex_color( (string) $src['bg_color'] ) : null;
        if ( ! $bg_color ) {
            $bg_color = $defaults['bg_color'];
        }

        $text_color = isset( $src['text_color'] ) ? sanitize_hex_color( (string) $src['text_color'] ) : null;
        if ( ! $text_color ) {
            $text_color = $defaults['text_color'];
        }

        $target['bg_color']   = $bg_color;
        $target['text_color'] = $text_color;

        // Extra numeric fields: "days" (for "new") or "count" (for "bestseller").
        if ( $has_extra && isset( $defaults['days'] ) ) {
            $target['days'] = isset( $src['days'] ) ? max( 1, (int) $src['days'] ) : $defaults['days'];
        }

        if ( $has_extra && isset( $defaults['count'] ) ) {
            $target['count'] = isset( $src['count'] ) ? max( 1, (int) $src['count'] ) : $defaults['count'];
        }

        return $target;
    }

    /**
     * Get default plugin settings.
     *
     * @return array<string,mixed>
     */
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
                // NEW defaults for style picker.
                'style_variant'  => 'pill',
                'style_outline'  => false,
                'layout'         => 'inline',
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