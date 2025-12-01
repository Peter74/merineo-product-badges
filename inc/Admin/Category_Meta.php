<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Category_Meta {

    private string $meta_key = 'merineo_product_badges';
    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function hooks(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'product_cat_add_form_fields', [ $this, 'render_add_form' ] );
        add_action( 'product_cat_edit_form_fields', [ $this, 'render_edit_form' ], 10, 2 );
        add_action( 'created_product_cat', [ $this, 'save_term' ] );
        add_action( 'edited_product_cat', [ $this, 'save_term' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_meta(): void {
        register_term_meta(
            'product_cat',
            $this->meta_key,
            [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => [ $this, 'sanitize_meta_value' ],
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'manage_product_terms' );
                },
            ]
        );
    }

    public function enqueue_assets( string $hook_suffix ): void {
        if ( 'edit-tags.php' !== $hook_suffix ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script(
            'merineo-product-badges-admin-category',
            MERINEO_PB_URL . 'assets/admin/js/category-badges.js',
            [ 'wp-color-picker' ],
            MERINEO_PB_VERSION,
            true
        );
        wp_localize_script(
            'merineo-product-badges-admin-category',
            'merineoProductBadgesL10n',
            [
                'noBadges'         => __( 'No badges yet. Click "Add badge" to create one.', 'merineo-product-badges' ),
                'badge'            => __( 'Badge', 'merineo-product-badges' ),
                'labelPlaceholder' => __( 'Text for badge', 'merineo-product-badges' ),
            ]
        );
    }

    public function render_add_form( string $taxonomy ): void {
        ?>
        <div class="form-field merineo-pb-term-badges" data-merineo-pb-target="#merineo_pb_term_badges_input">
            <label><?php esc_html_e( 'Merineo Badges', 'merineo-product-badges' ); ?></label>
            <p class="description"><?php esc_html_e( 'Badges configured here will be applied to all products assigned to this category.', 'merineo-product-badges' ); ?></p>
            <div class="merineo-pb-badges-list"></div>
            <p><button type="button" class="button button-secondary merineo-pb-add-badge"><?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?></button></p>
            <input type="hidden" id="merineo_pb_term_badges_input" name="merineo_product_badges_json" value="[]" />
        </div>
        <?php
    }

    public function render_edit_form( \WP_Term $term, string $taxonomy ): void {
        $value = get_term_meta( $term->term_id, $this->meta_key, true );
        $json  = is_string( $value ) ? $value : '[]';
        ?>
        <tr class="form-field merineo-pb-term-badges" data-merineo-pb-target="#merineo_pb_term_badges_input">
            <th scope="row"><label for="merineo_pb_term_badges_input"><?php esc_html_e( 'Merineo Badges', 'merineo-product-badges' ); ?></label></th>
            <td>
                <p class="description"><?php esc_html_e( 'Badges configured here will be applied to all products assigned to this category. Products also inherit global and product-specific badges.', 'merineo-product-badges' ); ?></p>
                <div class="merineo-pb-badges-list"></div>
                <p><button type="button" class="button button-secondary merineo-pb-add-badge"><?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?></button></p>
                <input type="hidden" id="merineo_pb_term_badges_input" name="merineo_product_badges_json" value="<?php echo esc_attr( $json ); ?>" />
            </td>
        </tr>
        <?php
    }

    public function save_term( int $term_id ): void {
        if ( ! current_user_can( 'manage_product_terms' ) ) {
            return;
        }
        $json = isset( $_POST['merineo_product_badges_json'] ) ? wp_unslash( (string) $_POST['merineo_product_badges_json'] ) : '';
        if ( '' === $json ) {
            delete_term_meta( $term_id, $this->meta_key );
        } else {
            update_term_meta( $term_id, $this->meta_key, $this->sanitize_meta_value( $json ) );
        }
    }

    public function sanitize_meta_value( string $value ): string {
        $data = json_decode( $value, true );
        if ( ! is_array( $data ) ) {
            return '[]';
        }
        $sanitized = [];
        foreach ( $data as $badge ) {
            if ( ! is_array( $badge ) ) {
                continue;
            }
            $label      = isset( $badge['label'] ) ? sanitize_text_field( (string) $badge['label'] ) : '';
            $bg         = isset( $badge['bg_color'] ) ? sanitize_hex_color( (string) $badge['bg_color'] ) : '';
            $text_color = isset( $badge['text_color'] ) ? sanitize_hex_color( (string) $badge['text_color'] ) : '';
            if ( '' === $label ) {
                continue;
            }
            $sanitized[] = [
                'label'      => $label,
                'bg_color'   => $bg,
                'text_color' => $text_color,
            ];
        }
        return wp_json_encode( $sanitized );
    }
}
