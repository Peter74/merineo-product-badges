<?php
/**
 * Plugin Name:       Merineo Product Badges
 * Plugin URI:        https://example.com/
 * Description:       Flexible WooCommerce product badges with global, category, and per-product configuration.
 * Version:           1.0.0
 * Author:            Merineo
 * Author URI:        https://example.com/
 * Text Domain:       merineo-product-badges
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * WC requires at least: 9.0
 * WC tested up to:   9.0
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MERINEO_PB_VERSION', '1.0.0' );
define( 'MERINEO_PB_FILE', __FILE__ );
define( 'MERINEO_PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MERINEO_PB_URL', plugin_dir_url( __FILE__ ) );
define( 'MERINEO_PB_OPTION_NAME', 'merineo_product_badges_settings' );
define( 'MERINEO_PB_TRANSIENT_BESTSELLERS', 'merineo_pb_bestsellers' );

register_activation_hook(
    __FILE__,
    static function (): void {
        require_once MERINEO_PB_DIR . 'inc/Bootstrap.php';
        Merineo\ProductBadges\Bootstrap::activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        require_once MERINEO_PB_DIR . 'inc/Bootstrap.php';
        Merineo\ProductBadges\Bootstrap::deactivate();
    }
);

require_once MERINEO_PB_DIR . 'inc/Bootstrap.php';
Merineo\ProductBadges\Bootstrap::run();
