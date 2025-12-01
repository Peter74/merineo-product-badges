<?php
/**
 * Plugin bootstrap.
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

namespace Merineo\ProductBadges;

use Merineo\ProductBadges\Admin\Menu;
use Merineo\ProductBadges\Admin\Settings_Page;
use Merineo\ProductBadges\Admin\Product_Meta;
use Merineo\ProductBadges\Admin\Category_Meta;
use Merineo\ProductBadges\Common\Settings;
use Merineo\ProductBadges\Frontend\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Bootstrap {

    public static function run(): void {
        add_action( 'plugins_loaded', [ static::class, 'init' ] );
    }

    public static function activate(): void {
        require_once __DIR__ . '/Common/Settings.php';
        $settings = new Settings();
        $settings->bootstrap_defaults();
    }

    public static function deactivate(): void {
        delete_transient( MERINEO_PB_TRANSIENT_BESTSELLERS );
    }

    public static function init(): void {
        if ( ! class_exists( '\\WooCommerce' ) ) {
            return;
        }

        static::declare_hpos_compatibility();

        require_once __DIR__ . '/Common/Settings.php';
        require_once __DIR__ . '/Admin/Menu.php';
        require_once __DIR__ . '/Admin/Settings_Page.php';
        require_once __DIR__ . '/Admin/Product_Meta.php';
        require_once __DIR__ . '/Admin/Category_Meta.php';
        require_once __DIR__ . '/Frontend/Renderer.php';

        $settings = new Settings();

        ( new Menu( $settings ) )->hooks();
        ( new Settings_Page( $settings ) )->hooks();
        ( new Product_Meta( $settings ) )->hooks();
        ( new Category_Meta( $settings ) )->hooks();
        ( new Renderer( $settings ) )->hooks();

        load_plugin_textdomain(
            'merineo-product-badges',
            false,
            basename( MERINEO_PB_DIR ) . '/languages/'
        );
    }

    private static function declare_hpos_compatibility(): void {
        add_action(
            'before_woocommerce_init',
            static function (): void {
                if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\Features' ) ) {
                    \\Automattic\\WooCommerce\\Utilities\\Features::declare_compatibility(
                        'custom_order_tables',
                        MERINEO_PB_FILE,
                        true
                    );
                }
            }
        );
    }
}
