<?php
/**
 * Uninstall handler.
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$option_name = 'merineo_product_badges_settings';

delete_option( $option_name );
delete_site_option( $option_name );
delete_transient( 'merineo_pb_bestsellers' );
