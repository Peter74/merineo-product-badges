<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Term meta for product categories (category-level badges).
 */
class Category_Meta {

    /**
     * Term meta key.
     *
     * @var string
     */
    private string $meta_key = 'merineo_product_badges';

    /**
     * Settings service (not used directly here, but injected for future use).
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
        add_action( 'product_cat_add_form_fields', [ $this, 'render_add_form' ] );
        add_action( 'product_cat_edit_form_fields', [ $this, 'render_edit_form' ], 10, 2 );
        add_action( 'created_product_cat', [ $this, 'save_term' ] );
        add_action( 'edited_product_cat', [ $this, 'save_term' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register term meta with sanitize callback.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/register_term_meta/
     */
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

    /**
     * Enqueue admin assets on product category screens.
     *
     * @param string $hook_suffix Current admin hook.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
     */
    public function enqueue_assets( string $hook_suffix ): void {
        // Be explicit: only when editing product categories.
        // URL: /wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product.
        $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( (string) wp_unslash( $_GET['taxonomy'] ) ) : '';
        if ( 'product_cat' !== $taxonomy ) {
            return;
        }

        // Color picker (styles + script).
        // @link https://developer.wordpress.org/reference/functions/wp_enqueue_style/
        // @link https://developer.wordpress.org/reference/functions/wp_enqueue_script/
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Our JS for category badge UI.
        wp_enqueue_script(
                'merineo-product-badges-admin-category',
                MERINEO_PB_URL . 'assets/admin/js/category-badges.js',
                [ 'jquery', 'wp-color-picker' ],
                MERINEO_PB_VERSION,
                true
        );

        // Localized strings for JS.
        // @link https://developer.wordpress.org/reference/functions/wp_localize_script/
        wp_localize_script(
                'merineo-product-badges-admin-category',
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
     * Render add form fields (create category).
     *
     * @param string $taxonomy Taxonomy slug.
     *
     * @return void
     */
    public function render_add_form( string $taxonomy ): void {
        ?>
        <div class="form-field merineo-pb-term-badges" data-merineo-pb-target="#merineo_pb_term_badges_input">
            <label><?php esc_html_e( 'Merineo Badges', 'merineo-product-badges' ); ?></label>
            <p class="description">
                <?php esc_html_e( 'Badges configured here will be applied to all products assigned to this category.', 'merineo-product-badges' ); ?>
            </p>
            <div class="merineo-pb-badges-list"></div>
            <p>
                <button type="button" class="button button-primary merineo-pb-add-badge">
                    <?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?>
                </button>
            </p>
            <input type="hidden" id="merineo_pb_term_badges_input" name="merineo_product_badges_json" value="[]" />
        </div>
        <?php
    }

    /**
     * Render edit form fields (edit category).
     *
     * @param \WP_Term $term     Term object.
     * @param string   $taxonomy Taxonomy slug.
     *
     * @return void
     */
    public function render_edit_form( \WP_Term $term, string $taxonomy ): void {
        $value = get_term_meta( $term->term_id, $this->meta_key, true );
        $json  = is_string( $value ) ? $value : '[]';
        ?>
        <tr class="form-field merineo-pb-term-badges" data-merineo-pb-target="#merineo_pb_term_badges_input">
            <th scope="row">
                <label for="merineo_pb_term_badges_input">
                    <?php esc_html_e( 'Merineo Badges', 'merineo-product-badges' ); ?>
                </label>
            </th>
            <td>
                <p class="description">
                    <?php esc_html_e( 'Badges configured here will be applied to all products assigned to this category. Products also inherit global and product-specific badges.', 'merineo-product-badges' ); ?>
                </p>
                <div class="merineo-pb-badges-list"></div>
                <p>
                    <button type="button" class="button button-primary merineo-pb-add-badge">
                        <?php esc_html_e( 'Add badge', 'merineo-product-badges' ); ?>
                    </button>
                </p>
                <input
                        type="hidden"
                        id="merineo_pb_term_badges_input"
                        name="merineo_product_badges_json"
                        value="<?php echo esc_attr( $json ); ?>"
                />
            </td>
        </tr>
        <?php
    }

    /**
     * Save term meta when category is created/edited.
     *
     * @param int $term_id Term ID.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/update_term_meta/
     */
    public function save_term( int $term_id ): void {
        if ( ! current_user_can( 'manage_product_terms' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- trusted admin screen.
        $json = isset( $_POST['merineo_product_badges_json'] )
                ? wp_unslash( (string) $_POST['merineo_product_badges_json'] )
                : '';

        if ( '' === $json ) {
            delete_term_meta( $term_id, $this->meta_key );
            return;
        }

        // Pass raw JSON to update_term_meta() – value will be sanitized
        // via register_term_meta() -> sanitize_meta_value().
        update_term_meta( $term_id, $this->meta_key, $json );
    }

    /**
     * Sanitize meta value (JSON string) for this term meta.
     *
     * @param string $value Raw JSON string.
     *
     * @return string Sanitized JSON string.
     *
     * @link https://developer.wordpress.org/reference/functions/sanitize_hex_color/
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
                // Skip empty badges completely.
                continue;
            }

            $bg_raw   = isset( $badge['bg_color'] ) ? (string) $badge['bg_color'] : '';
            $txt_raw  = isset( $badge['text_color'] ) ? (string) $badge['text_color'] : '';

            $bg_color   = $bg_raw ? sanitize_hex_color( $bg_raw ) : '';
            $text_color = $txt_raw ? sanitize_hex_color( $txt_raw ) : '';

            $sanitized[] = [
                    'label'      => $label,
                    'bg_color'   => $bg_color ? $bg_color : '',
                    'text_color' => $text_color ? $text_color : '',
            ];
        }

        return wp_json_encode( $sanitized );
    }
}