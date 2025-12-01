<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product_Meta {

    private string $meta_key = '_merineo_product_badges';
    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function hooks(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'add_meta_boxes_product', [ $this, 'add_meta_box' ] );
        add_action( 'save_post_product', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_meta(): void {
        register_post_meta(
            'product',
            $this->meta_key,
            [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => [ $this, 'sanitize_meta_value' ],
                'auth_callback'     => static function (): bool {
                    return current_user_can( 'edit_products' );
                },
            ]
        );
    }

    public function add_meta_box(): void {
        add_meta_box(
            'merineo_product_badges',
            __( 'Merineo Badges', 'merineo-product-badges' ),
            [ $this, 'render_meta_box' ],
            'product',
            'side',
            'default'
        );
    }

    public function enqueue_assets( string $hook_suffix ): void {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_script(
            'merineo-product-badges-admin-product',
            MERINEO_PB_URL . 'assets/admin/js/product-badges.js',
            [ 'wp-color-picker' ],
            MERINEO_PB_VERSION,
            true
        );
        wp_localize_script(
            'merineo-product-badges-admin-product',
            'merineoProductBadgesL10n',
            [
                'noBadges'         => __( 'No badges yet. Click "Add badge" to create one.', 'merineo-product-badges' ),
                'badge'            => __( 'Badge', 'merineo-product-badges' ),
                'labelPlaceholder' => __( 'Text for badge', 'merineo-product-badges' ),
            ]
        );
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'merineo_pb_product_meta', 'merineo_pb_product_meta_nonce' );
        $value = get_post_meta( $post->ID, $this->meta_key, true );
        $json  = is_string( $value ) ? $value : '[]';
        ?>
        <div class="merineo-pb-badges-box" data-merineo-pb-target="#merineo_pb_product_badges_input">
            <p class="description"><?php esc_html_e( 'Custom badges for this product. Product also inherits global and category badges.', 'merineo-product-badges' ); ?></p>
            <div class="merineo-pb-badges-list"></div>
            <p><button type="button" class="button button-primary merineo-pb-add-badge"><?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?></button></p>
            <input type="hidden" id="merineo_pb_product_badges_input" name="merineo_product_badges_json" value="<?php echo esc_attr( $json ); ?>" />
        </div>
        <?php
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( 'product' !== $post->post_type ) {
            return;
        }
        if ( ! isset( $_POST['merineo_pb_product_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['merineo_pb_product_meta_nonce'] ), 'merineo_pb_product_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }
        $json = isset( $_POST['merineo_product_badges_json'] ) ? wp_unslash( (string) $_POST['merineo_product_badges_json'] ) : '';
        if ( '' === $json ) {
            delete_post_meta( $post_id, $this->meta_key );
        } else {
            update_post_meta( $post_id, $this->meta_key, $this->sanitize_meta_value( $json ) );
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
