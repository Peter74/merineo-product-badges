<?php
/**
 * Uninstall handler.
 *
 * Removes plugin settings, caches and badge data (product + category meta).
 * On multisite every site is cleaned, because each site stores its own
 * option "merineo_product_badges_settings_{blog_id}" and category meta
 * "merineo_product_badges_{blog_id}".
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove plugin data for the current site.
 *
 * Key names must match Settings::get_option_name_for_current_site(),
 * Category_Meta and Product_Meta.
 *
 * @return void
 *
 * @link https://developer.wordpress.org/reference/functions/delete_metadata/
 */
function merineo_pb_uninstall_site(): void {
    $option_name   = 'merineo_product_badges_settings';
    $term_meta_key = 'merineo_product_badges';

    // Legacy / single-site keys.
    delete_option( $option_name );
    delete_metadata( 'term', 0, $term_meta_key, '', true );

    if ( is_multisite() ) {
        $blog_id = (int) get_current_blog_id();

        delete_option( $option_name . '_' . $blog_id );
        delete_metadata( 'term', 0, $term_meta_key . '_' . $blog_id, '', true );
    }

    delete_metadata( 'post', 0, '_merineo_product_badges', '', true );
    delete_transient( 'merineo_pb_bestsellers' );
}

if ( is_multisite() ) {
    $merineo_pb_site_ids = get_sites(
        [
            'fields' => 'ids',
            'number' => 0,
        ]
    );

    foreach ( $merineo_pb_site_ids as $merineo_pb_site_id ) {
        switch_to_blog( (int) $merineo_pb_site_id );
        merineo_pb_uninstall_site();
        restore_current_blog();
    }

    delete_site_option( 'merineo_product_badges_settings' );
} else {
    merineo_pb_uninstall_site();
}
