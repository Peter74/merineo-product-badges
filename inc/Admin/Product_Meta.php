<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Per-product meta (product-specific badges).
 */
class Product_Meta {

    /**
     * Meta key for product badges.
     *
     * @var string
     */
    private string $meta_key = '_merineo_product_badges';

    /**
     * Settings service (injected for future use / consistency).
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Constructor.
     *
     * @param Settings $settings Settings service.
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function hooks(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'add_meta_boxes_product', [ $this, 'add_meta_box' ] );
        add_action( 'save_post_product', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register post meta with sanitize callback.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/register_post_meta/
     */
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

    /**
     * Add meta box to product edit screen.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/add_meta_box/
     */
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

    /**
     * Enqueue admin assets on product edit screens.
     *
     * @param string $hook_suffix Current admin hook.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_script/
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_style/
     */
    public function enqueue_assets( string $hook_suffix ): void {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        // Color picker.
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Our JS for product badge editor.
        wp_enqueue_script(
                'merineo-product-badges-admin-product',
                MERINEO_PB_URL . 'assets/admin/js/product-badges.js',
                [ 'jquery', 'wp-color-picker' ],
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
                        'titleLabel'       => __( 'Text for badge', 'merineo-product-badges' ),
                        'backgroundLabel'  => __( 'Background', 'merineo-product-badges' ),
                        'textLabel'        => __( 'Text', 'merineo-product-badges' ),
                ]
        );

        // Shared admin CSS for meta UI (product + category).
        wp_enqueue_style(
                'merineo-product-badges-admin-meta',
                MERINEO_PB_URL . 'assets/admin/css/meta.css',
                [],
                MERINEO_PB_VERSION
        );
    }

    /**
     * Render product meta box.
     *
     * @param \WP_Post $post Current post object.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/get_post_meta/
     */
    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'merineo_pb_product_meta', 'merineo_pb_product_meta_nonce' );

        $value = get_post_meta( $post->ID, $this->meta_key, true );
        $json  = is_string( $value ) ? $value : '[]';
        ?>
        <div class="merineo-pb-badges-box" data-merineo-pb-target="#merineo_pb_product_badges_input">
            <p class="description">
                <?php esc_html_e( 'Custom badges for this product. Product also inherits global and category badges.', 'merineo-product-badges' ); ?>
            </p>
            <div class="merineo-pb-badges-list"></div>
            <p>
                <button type="button" class="button button-primary merineo-pb-add-badge">
                    <?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?>
                </button>
            </p>
            <input
                    type="hidden"
                    id="merineo_pb_product_badges_input"
                    name="merineo_product_badges_json"
                    value="<?php echo esc_attr( $json ); ?>"
            />
        </div>
        <?php
    }

    /**
     * Save product meta when product is saved.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/hooks/save_post_post-post_type/
     * @link https://developer.wordpress.org/reference/functions/update_post_meta/
     * @link https://developer.wordpress.org/reference/functions/delete_post_meta/
     */
    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( 'product' !== $post->post_type ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
        if ( ! isset( $_POST['merineo_pb_product_meta_nonce'] ) ) {
            return;
        }

        $nonce = wp_unslash( (string) $_POST['merineo_pb_product_meta_nonce'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( ! wp_verify_nonce( $nonce, 'merineo_pb_product_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- already verified above.
        $json = isset( $_POST['merineo_product_badges_json'] )
                ? wp_unslash( (string) $_POST['merineo_product_badges_json'] )
                : '';

        if ( '' === $json ) {
            delete_post_meta( $post_id, $this->meta_key );
            return;
        }

        // Pass raw JSON to update_post_meta().
        // Sanitization is handled by register_post_meta() -> sanitize_meta_value().
        update_post_meta( $post_id, $this->meta_key, $json );
    }

    /**
     * Sanitize meta value (JSON string) for this post meta.
     *
     * @param string $value Raw JSON string.
     *
     * @return string Sanitized JSON string.
     *
     * @link https://developer.wordpress.org/reference/functions/sanitize_hex_color/
     * @link https://developer.wordpress.org/reference/functions/wp_json_encode/
     */
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

            $label = isset( $badge['label'] ) ? sanitize_text_field( (string) $badge['label'] ) : '';
            if ( '' === $label ) {
                // Skip completely empty badges.
                continue;
            }

            $bg_raw  = isset( $badge['bg_color'] ) ? (string) $badge['bg_color'] : '';
            $txt_raw = isset( $badge['text_color'] ) ? (string) $badge['text_color'] : '';

            $bg_color   = $bg_raw ? sanitize_text_field( $bg_raw ) : '';
            $text_color = $txt_raw ? sanitize_text_field( $txt_raw ) : '';

            $sanitized[] = [
                    'label'      => $label,
                    'bg_color'   => $bg_color,
                    'text_color' => $text_color,
            ];
        }

        return wp_json_encode( $sanitized );
    }
}