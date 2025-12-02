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
        // Run late so other Merineo plugins can register the top-level menu first.
        add_action( 'admin_menu', [ $this, 'register_menu' ], 99 );
    }

    /**
     * Register Merineo top-level menu (if needed) and Product Badges submenu.
     *
     * - If a Merineo menu with slug "merineo-settings-page" already exists,
     *   we only register our submenu.
     * - If it does not exist yet, we create it with the shared Merineo icon
     *   and position 83, as per scaffold.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_menu/
     * @link https://developer.wordpress.org/reference/functions/add_menu_page/
     * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
     */
    public function register_menu(): void {
        // Allow both WooCommerce managers and full admins to see the menu.
        $capability = 'manage_woocommerce';

        if ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $menu;

        $merineo_slug     = 'merineo-settings-page';
        $top_level_exists = false;

        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                // Index [2] je slug top-level položky.
                if ( isset( $item[2] ) && $merineo_slug === $item[2] ) {
                    $top_level_exists = true;
                    break;
                }
            }
        }

        // Ak Merineo menu ešte neexistuje, vytvoríme ho s Merineo SVG ikonou.
        if ( ! $top_level_exists ) {
            add_menu_page(
                __( 'Merineo', 'merineo-product-badges' ),
                __( 'Merineo', 'merineo-product-badges' ),
                'manage_woocommerce',
                $merineo_slug,
                [ $this, 'render_placeholder_page' ],
                MERINEO_PB_URL . 'assets/admin/img/settings-icon.svg',
                83
            );
        }

        // Vždy pridáme naše submenu pod Merineo.
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
