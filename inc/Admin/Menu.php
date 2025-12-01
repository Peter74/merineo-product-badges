<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menu {

    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    public function register_menu(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        global $menu;
        $merineo_slug     = 'merineo-settings-page';
        $top_level_exists = false;

        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && $merineo_slug === $item[2] ) {
                    $top_level_exists = true;
                    break;
                }
            }
        }

        if ( ! $top_level_exists ) {
            add_menu_page(
                __( 'Merineo', 'merineo-product-badges' ),
                __( 'Merineo', 'merineo-product-badges' ),
                'manage_woocommerce',
                $merineo_slug,
                [ $this, 'render_placeholder_page' ],
                'dashicons-awards',
                83
            );
        }

        add_submenu_page(
            $merineo_slug,
            __( 'Product Badges', 'merineo-product-badges' ),
            __( 'Product Badges', 'merineo-product-badges' ),
            'manage_woocommerce',
            'merineo-product-badges',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_placeholder_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'merineo-product-badges' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Merineo', 'merineo-product-badges' ) . '</h1></div>';
    }

    public function render_settings_page(): void {
        // actual callback is attached by Settings_Page.
    }
}
