<?php
/**
 * Plugin Name:       Merineo Product Badges
 * Plugin URI:        https://merineo.sk/
 * Description:       Flexible WooCommerce product badges with global, category, and per-product configuration.
 * Version:           1.0.3
 * Author:            Merineo s.r.o. (PeterB)
 * Author URI:        https://merineo.sk/
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

define( 'MERINEO_PB_VERSION', '1.0.3' );
define( 'MERINEO_PB_FILE', __FILE__ );
define( 'MERINEO_PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MERINEO_PB_URL', plugin_dir_url( __FILE__ ) );
define( 'MERINEO_PB_OPTION_NAME', 'merineo_product_badges_settings' );
define( 'MERINEO_PB_TRANSIENT_BESTSELLERS', 'merineo_pb_bestsellers' );

// Add "Settings" link under plugin name on Plugins screen.
// Documentation: https://developer.wordpress.org/reference/hooks/plugin_action_links_plugin_file/
// Documentation: https://developer.wordpress.org/reference/functions/admin_url/
add_filter(
    'plugin_action_links_' . plugin_basename( __FILE__ ),
    static function ( array $links ): array {
        $url = admin_url( 'admin.php?page=merineo-product-badges' );

        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $url ),
            esc_html__( 'Settings', 'merineo-product-badges' )
        );

        // Put our link first.
        array_unshift( $links, $settings_link );

        return $links;
    }
);

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
