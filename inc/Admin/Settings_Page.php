<?php
/**
 * Settings page.
 *
 * @package Merineo_Product_Badges
 */

declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders plugin settings page and wires Settings API.
 */
class Settings_Page {

    /**
     * Settings service.
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
     * Register admin hooks.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/hooks/admin_init/
     * @link https://developer.wordpress.org/reference/hooks/admin_menu/
     * @link https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
     */
    public function hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Run after Menu::register_menu (priority 99), so the submenu page exists
        // and get_plugin_page_hookname() can find it.
        add_action( 'admin_menu', [ $this, 'connect_page_callback' ], 120 );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register settings with Settings API.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/register_setting/
     */
    public function register_settings(): void {
        $this->settings->register();
    }

    /**
     * Attach our render callback to the submenu page created in Menu.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/get_plugin_page_hookname/
     */
    public function connect_page_callback(): void {
        $hookname = get_plugin_page_hookname( 'merineo-product-badges', 'merineo-settings-page' );
        if ( ! empty( $hookname ) ) {
            // Render whole settings UI when this admin page is loaded.
            add_action( $hookname, [ $this, 'render_page' ] );
        }
    }

    /**
     * Enqueue admin assets on our settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_code_editor/
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_style/
     * @link https://developer.wordpress.org/reference/functions/wp_enqueue_script/
     */
    public function enqueue_assets( string $hook_suffix ): void {
        // Load assets only on our plugin settings page: admin.php?page=merineo-product-badges.
        // Documentation: https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
        $page = isset( $_GET['page'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['page'] ) ) : '';
        if ( 'merineo-product-badges' !== $page ) {
            return;
        }

        // Code editor for CSS textarea.
        $editor_settings = wp_enqueue_code_editor(
                [
                        'type' => 'text/css',
                ]
        );

        if ( ! empty( $editor_settings ) ) {
            // Enqueue CodeMirror assets.
            wp_enqueue_script( 'code-editor' );
            wp_enqueue_style( 'wp-codemirror' );

            wp_add_inline_script(
                    'code-editor',
                    'jQuery( function() { wp.codeEditor.initialize( "merineo_pb_custom_css", ' . wp_json_encode( $editor_settings ) . ' ); } );'
            );
        }

        // Color picker for badge colors.
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Our compact admin styles.
        wp_enqueue_style(
                'merineo-product-badges-settings',
                MERINEO_PB_URL . 'assets/admin/css/settings.css',
                [],
                MERINEO_PB_VERSION
        );

        // Small JS for accordions & copy-to-clipboard.
        wp_enqueue_script(
                'merineo-product-badges-settings',
                MERINEO_PB_URL . 'assets/admin/js/settings.js',
                [ 'jquery', 'wp-color-picker' ],
                MERINEO_PB_VERSION,
                true
        );

        wp_localize_script(
                'merineo-product-badges-settings',
                'merineoProductBadgesSettings',
                [
                        'copied' => __( 'Copied', 'merineo-product-badges' ),
                ]
        );
    }

    /**
     * Render settings page with tabs & sections.
     *
     * @return void
     *
     * @link https://developer.wordpress.org/reference/functions/settings_fields/
     * @link https://developer.wordpress.org/reference/functions/submit_button/
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'merineo-product-badges' ) );
        }

        // Per-site option name (must match Settings::register()).
        $option_name = Settings::get_option_name_for_current_site();
        $options     = $this->settings->all();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
        $section = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : 'layout';
        $allowed = [ 'layout', 'automatic', 'design', 'advanced' ];
        if ( ! in_array( $section, $allowed, true ) ) {
            $section = 'layout';
        }

        $sections_labels = [
                'layout'    => __( 'Layout & Placement', 'merineo-product-badges' ),
                'automatic' => __( 'Automatic Badges', 'merineo-product-badges' ),
                'design'    => __( 'Design & Typography', 'merineo-product-badges' ),
                'advanced'  => __( 'Advanced & Custom CSS', 'merineo-product-badges' ),
        ];

        $base_url = admin_url( 'admin.php?page=merineo-product-badges' );
        ?>
        <div class="wrap merineo-pb-settings">
            <h1><?php esc_html_e( 'Merineo Product Badges', 'merineo-product-badges' ); ?></h1>

            <h2 class="nav-tab-wrapper merineo-pb-tabs">
                <?php foreach ( $sections_labels as $key => $label ) : ?>
                    <?php
                    $url = $base_url;
                    if ( 'layout' !== $key ) {
                        $url = add_query_arg( 'section', $key, $base_url );
                    }
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $section === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'merineo_product_badges' );
                ?>

                <div class="merineo-pb-section">
                    <?php
                    switch ( $section ) {
                        case 'automatic':
                            $this->render_section_automatic( $options, $option_name );
                            break;

                        case 'design':
                            $this->render_section_design( $options, $option_name );
                            break;

                        case 'advanced':
                            $this->render_section_advanced( $options, $option_name );
                            break;

                        case 'layout':
                        default:
                            $this->render_section_layout( $options, $option_name );
                            break;
                    }
                    ?>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Layout & Placement section.
     *
     * @param array<string,mixed> $options     Options.
     * @param string              $option_name Option name for current site.
     *
     * @return void
     */
    private function render_section_layout( array $options, string $option_name ): void {
        ?>
        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Global toggle', 'merineo-product-badges' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Turn the entire product badges feature on or off.', 'merineo-product-badges' ); ?>
            </p>
            <label class="merineo-pb-toggle">
                <input
                        type="hidden"
                        name="<?php echo esc_attr( $option_name ); ?>[general][enabled]"
                        value="0"
                />
                <input
                        type="checkbox"
                        name="<?php echo esc_attr( $option_name ); ?>[general][enabled]"
                        value="1"
                        <?php checked( ! empty( $options['general']['enabled'] ) ); ?>
                />
                <span><?php esc_html_e( 'Enable product badges', 'merineo-product-badges' ); ?></span>
            </label>
        </div>

        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Placement', 'merineo-product-badges' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Choose where badges should appear in single product view, product archives and custom locations.', 'merineo-product-badges' ); ?>
            </p>

            <table class="form-table merineo-pb-table">
                <tbody>
                <?php
                $this->render_placement_row( 'single', __( 'Single product page', 'merineo-product-badges' ), $options, $option_name );
                $this->render_placement_row( 'loop', __( 'Product archive / category', 'merineo-product-badges' ), $options, $option_name );
                $this->render_placement_row( 'custom', __( 'Custom hook', 'merineo-product-badges' ), $options, $option_name );
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Automatic Badges section (accordion).
     *
     * @param array<string,mixed> $options     Options.
     * @param string              $option_name Option name for current site.
     *
     * @return void
     */
    private function render_section_automatic( array $options, string $option_name ): void {
        ?>
        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Automatic badges', 'merineo-product-badges' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Configure rules-based badges like New, Recommended, Sale, stock statuses and Bestsellers.', 'merineo-product-badges' ); ?>
            </p>

            <?php
            $this->render_auto_badge_accordion( 'new', __( 'New', 'merineo-product-badges' ), $options['automatic']['new'], $option_name, true, false );
            $this->render_auto_badge_accordion( 'featured', __( 'Recommended', 'merineo-product-badges' ), $options['automatic']['featured'], $option_name );
            $this->render_auto_badge_accordion( 'sale', __( 'Sale', 'merineo-product-badges' ), $options['automatic']['sale'], $option_name, false, true );
            $this->render_auto_badge_accordion( 'outofstock', __( 'Out of stock', 'merineo-product-badges' ), $options['automatic']['outofstock'], $option_name );
            $this->render_auto_badge_accordion( 'instock', __( 'In stock', 'merineo-product-badges' ), $options['automatic']['instock'], $option_name );
            $this->render_auto_badge_accordion( 'backorder', __( 'Backorder', 'merineo-product-badges' ), $options['automatic']['backorder'], $option_name );
            $this->render_auto_badge_accordion( 'bestseller', __( 'Bestseller', 'merineo-product-badges' ), $options['automatic']['bestseller'], $option_name, false, false, true );
            if ( class_exists( '\Merineo\Multipack_Discount\Common\Multipack_Helper' ) ) {
                $this->render_auto_badge_accordion(
                        'multipack',
                        __( 'Multipack', 'merineo-product-badges' ),
                        $options['automatic']['multipack'],
                        $option_name
                );
            }
            ?>

            <p class="description merineo-pb-order-note">
                <?php
                esc_html_e(
                        'Badges are rendered in this order (when active): Recommended, Sale, Multipack, New, Category, Product-specific.',
                        'merineo-product-badges'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Design & Typography section.
     *
     * @param array<string,mixed> $options     Options.
     * @param string              $option_name Option name for current site.
     *
     * @return void
     */
    private function render_section_design( array $options, string $option_name ): void {
        $general = $options['general'];
        ?>
        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Typography', 'merineo-product-badges' ); ?></h2>
            <div class="merineo-pb-grid merineo-pb-grid--2">
                <div class="merineo-pb-field">
                    <label for="merineo_pb_font_size">
                        <?php esc_html_e( 'Font size (px)', 'merineo-product-badges' ); ?>
                    </label>
                    <input
                            type="number"
                            step="0.1"
                            min="8"
                            id="merineo_pb_font_size"
                            name="<?php echo esc_attr( $option_name ); ?>[general][font_size]"
                            value="<?php echo esc_attr( (string) $general['font_size'] ); ?>"
                    />
                </div>

                <div class="merineo-pb-field">
                    <label for="merineo_pb_letter_spacing">
                        <?php esc_html_e( 'Letter spacing (px)', 'merineo-product-badges' ); ?>
                    </label>
                    <input
                            type="number"
                            step="0.1"
                            id="merineo_pb_letter_spacing"
                            name="<?php echo esc_attr( $option_name ); ?>[general][letter_spacing]"
                            value="<?php echo esc_attr( (string) $general['letter_spacing'] ); ?>"
                    />
                </div>

                <div class="merineo-pb-field">
                    <label for="merineo_pb_text_transform">
                        <?php esc_html_e( 'Text transform', 'merineo-product-badges' ); ?>
                    </label>
                    <select
                            id="merineo_pb_text_transform"
                            name="<?php echo esc_attr( $option_name ); ?>[general][text_transform]"
                    >
                        <option value="none" <?php selected( $general['text_transform'], 'none' ); ?>>
                            <?php esc_html_e( 'Normal', 'merineo-product-badges' ); ?>
                        </option>
                        <option value="uppercase" <?php selected( $general['text_transform'], 'uppercase' ); ?>>
                            <?php esc_html_e( 'Uppercase', 'merineo-product-badges' ); ?>
                        </option>
                    </select>
                </div>

                <div class="merineo-pb-field">
                    <label for="merineo_pb_font_weight">
                        <?php esc_html_e( 'Font weight', 'merineo-product-badges' ); ?>
                    </label>
                    <select
                            id="merineo_pb_font_weight"
                            name="<?php echo esc_attr( $option_name ); ?>[general][font_weight]"
                    >
                        <option value="normal" <?php selected( $general['font_weight'], 'normal' ); ?>>
                            <?php esc_html_e( 'Normal', 'merineo-product-badges' ); ?>
                        </option>
                        <option value="bold" <?php selected( $general['font_weight'], 'bold' ); ?>>
                            <?php esc_html_e( 'Bold', 'merineo-product-badges' ); ?>
                        </option>
                    </select>
                </div>

                <div class="merineo-pb-field merineo-pb-field--full">
                    <label>
                        <input
                                type="hidden"
                                name="<?php echo esc_attr( $option_name ); ?>[general][shadow_enabled]"
                                value="0"
                        />
                        <input
                                type="checkbox"
                                name="<?php echo esc_attr( $option_name ); ?>[general][shadow_enabled]"
                                value="1"
                                <?php checked( ! empty( $general['shadow_enabled'] ) ); ?>
                        />
                        <?php esc_html_e( 'Enable subtle shadow on badges', 'merineo-product-badges' ); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Badge style & layout', 'merineo-product-badges' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Choose overall shape for badges, outline vs solid, and whether badges are displayed inline or stacked.', 'merineo-product-badges' ); ?>
            </p>
            <?php
            $current_style = $general['style_variant'] ?? 'pill';
            $layout        = $general['layout'] ?? 'inline';
            ?>
            <div class="merineo-pb-style-grid">
                <?php
                $style_options = [
                        'pill'            => __( 'Compact pill', 'merineo-product-badges' ),
                        'square'          => __( 'Square', 'merineo-product-badges' ),
                        'rounded'         => __( 'Rounded rectangle', 'merineo-product-badges' ),
                        'circle'          => __( 'Circle', 'merineo-product-badges' ),
                        'ribbon-vertical' => __( 'Vertical ribbon', 'merineo-product-badges' ),
                        'ribbon-corner'   => __( 'Corner ribbon', 'merineo-product-badges' ),
                        'tag'             => __( 'Tag', 'merineo-product-badges' ),
                ];

                foreach ( $style_options as $value => $label ) :
                    $id      = 'merineo_pb_style_' . $value;
                    $checked = ( $current_style === $value );
                    ?>
                    <label class="merineo-pb-style-option" for="<?php echo esc_attr( $id ); ?>">
                        <input
                                type="radio"
                                id="<?php echo esc_attr( $id ); ?>"
                                name="<?php echo esc_attr( $option_name ); ?>[general][style_variant]"
                                value="<?php echo esc_attr( $value ); ?>"
                                <?php checked( $checked ); ?>
                        />
                        <span class="merineo-pb-style-preview merineo-pb-style-preview--<?php echo esc_attr( $value ); ?>">
                            <span class="merineo-pb-style-preview-text">
                                <?php esc_html_e( 'Badge', 'merineo-product-badges' ); ?>
                            </span>
                        </span>
                        <span class="merineo-pb-style-option-label">
                            <?php echo esc_html( $label ); ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="merineo-pb-style-toggles">
                <label class="merineo-pb-toggle">
                    <input
                            type="hidden"
                            name="<?php echo esc_attr( $option_name ); ?>[general][style_outline]"
                            value="0"
                    />
                    <input
                            type="checkbox"
                            name="<?php echo esc_attr( $option_name ); ?>[general][style_outline]"
                            value="1"
                            <?php checked( ! empty( $general['style_outline'] ) ); ?>
                    />
                    <span><?php esc_html_e( 'Outline style', 'merineo-product-badges' ); ?></span>
                </label>

                <div class="merineo-pb-radio-group">
                    <span class="merineo-pb-radio-group-label">
                        <?php esc_html_e( 'Badge layout', 'merineo-product-badges' ); ?>
                    </span>

                    <label class="merineo-pb-radio-pill">
                        <input
                                type="radio"
                                name="<?php echo esc_attr( $option_name ); ?>[general][layout]"
                                value="inline"
                                <?php checked( 'inline' === $layout ); ?>
                        />
                        <span><?php esc_html_e( 'Inline', 'merineo-product-badges' ); ?></span>
                    </label>

                    <label class="merineo-pb-radio-pill">
                        <input
                                type="radio"
                                name="<?php echo esc_attr( $option_name ); ?>[general][layout]"
                                value="stacked"
                                <?php checked( 'stacked' === $layout ); ?>
                        />
                        <span><?php esc_html_e( 'Stacked', 'merineo-product-badges' ); ?></span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Advanced & Custom CSS section.
     *
     * @param array<string,mixed> $options     Options.
     * @param string              $option_name Option name for current site.
     *
     * @return void
     */
    private function render_section_advanced( array $options, string $option_name ): void {
        ?>
        <div class="merineo-pb-card">
            <h2><?php esc_html_e( 'Custom CSS (scoped)', 'merineo-product-badges' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'CSS is scoped under the .merineo-badges-scope wrapper to minimize the risk of breaking your theme.', 'merineo-product-badges' ); ?>
            </p>

            <div class="merineo-pb-selectors">
                <p><strong><?php esc_html_e( 'Useful selectors', 'merineo-product-badges' ); ?></strong></p>
                <div class="merineo-pb-selectors-list">
                    <?php
                    $selectors = [
                            '.merineo-badges-scope .merineo-badges',
                            '.merineo-badge',
                            '.merineo-badge--type-featured',
                            '.merineo-badge--type-sale',
                            '.merineo-badge--type-new',
                            '.merineo-badge--type-bestseller',
                            '.merineo-badge--type-outofstock',
                            '.merineo-badge--type-instock',
                            '.merineo-badge--type-backorder',
                            '.merineo-badge--source-category',
                            '.merineo-badge--source-product',
                            '.merineo-badges--align-right',
                    ];
                    foreach ( $selectors as $selector ) :
                        ?>
                        <button
                                type="button"
                                class="button button-secondary button-small merineo-pb-copy-selector"
                                data-copy-selector="<?php echo esc_attr( $selector ); ?>"
                        >
                            <?php echo esc_html( $selector ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <textarea
                    id="merineo_pb_custom_css"
                    name="<?php echo esc_attr( $option_name ); ?>[css][custom]"
                    rows="12"
                    class="large-text code"
            ><?php echo esc_textarea( (string) $options['css']['custom'] ); ?></textarea>
        </div>
        <?php
    }

    /**
     * Placement row (single, loop, custom).
     *
     * @param string              $key         Area key.
     * @param string              $label       Human label.
     * @param array<string,mixed> $options     Options.
     * @param string              $option_name Option name for current site.
     *
     * @return void
     */
    private function render_placement_row( string $key, string $label, array $options, string $option_name ): void {
        $area = $options['general'][ $key ];
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <div class="merineo-pb-placement-row">
                    <div class="merineo-pb-placement-col">
                        <label>
                            <span class="merineo-pb-label"><?php esc_html_e( 'Hook', 'merineo-product-badges' ); ?></span>
                            <select name="<?php echo esc_attr( $option_name ); ?>[general][<?php echo esc_attr( $key ); ?>][hook]">
                                <option value=""><?php esc_html_e( 'Custom hook only', 'merineo-product-badges' ); ?></option>
                                <?php foreach ( $this->get_standard_hooks( $key ) as $hook => $hook_label ) : ?>
                                    <option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $area['hook'], $hook ); ?>>
                                        <?php echo esc_html( $hook_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="merineo-pb-placement-col">
                        <label>
                            <span class="merineo-pb-label"><?php esc_html_e( 'Custom hook name', 'merineo-product-badges' ); ?></span>
                            <input
                                    type="text"
                                    class="regular-text"
                                    name="<?php echo esc_attr( $option_name ); ?>[general][<?php echo esc_attr( $key ); ?>][custom_hook]"
                                    value="<?php echo esc_attr( (string) $area['custom_hook'] ); ?>"
                            />
                        </label>
                    </div>
                    <div class="merineo-pb-placement-col merineo-pb-placement-col--small">
                        <label>
                            <span class="merineo-pb-label"><?php esc_html_e( 'Priority', 'merineo-product-badges' ); ?></span>
                            <input
                                    type="number"
                                    name="<?php echo esc_attr( $option_name ); ?>[general][<?php echo esc_attr( $key ); ?>][priority]"
                                    value="<?php echo esc_attr( (string) $area['priority'] ); ?>"
                                    min="1"
                            />
                        </label>
                    </div>
                    <div class="merineo-pb-placement-col merineo-pb-placement-col--small">
                        <label>
                            <span class="merineo-pb-label"><?php esc_html_e( 'Alignment', 'merineo-product-badges' ); ?></span>
                            <select name="<?php echo esc_attr( $option_name ); ?>[general][<?php echo esc_attr( $key ); ?>][align]">
                                <option value="left" <?php selected( $area['align'], 'left' ); ?>>
                                    <?php esc_html_e( 'Left', 'merineo-product-badges' ); ?>
                                </option>
                                <option value="right" <?php selected( $area['align'], 'right' ); ?>>
                                    <?php esc_html_e( 'Right', 'merineo-product-badges' ); ?>
                                </option>
                            </select>
                        </label>
                    </div>
                </div>

                <?php if ( 'single' === $key ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'Single product hooks control badges on the product detail page.', 'merineo-product-badges' ); ?>
                    </p>
                <?php elseif ( 'loop' === $key ) : ?>
                    <p class="description">
                        <?php esc_html_e( 'Loop hooks control badges on shop, category and archive listings.', 'merineo-product-badges' ); ?>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php esc_html_e( 'Use custom hooks for advanced placements in your theme or custom templates.', 'merineo-product-badges' ); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render automatic badge accordion item.
     *
     * @param string              $key         Badge key.
     * @param string              $title       Section title.
     * @param array<string,mixed> $badge       Badge data.
     * @param string              $option_name Option name for current site.
     * @param bool                $with_days   Whether to display "days" input (New).
     * @param bool                $with_sale   Whether to render sale mode selector.
     * @param bool                $with_count  Whether to display count (Bestseller).
     *
     * @return void
     */
    private function render_auto_badge_accordion(
            string $key,
            string $title,
            array $badge,
            string $option_name,
            bool $with_days = false,
            bool $with_sale = false,
            bool $with_count = false
    ): void {
        $name_prefix = $option_name . '[automatic][' . $key . ']';
        $is_enabled  = ! empty( $badge['enabled'] );
        ?>
        <div class="merineo-pb-accordion <?php echo $is_enabled ? 'is-open' : ''; ?>">
            <div class="merineo-pb-accordion-header">
                <div class="merineo-pb-accordion-title">
                    <span class="merineo-pb-accordion-label"><?php echo esc_html( $title ); ?></span>
                    <?php if ( ! empty( $badge['label'] ) ) : ?>
                        <span class="merineo-pb-accordion-preview">
                            <?php echo esc_html( (string) $badge['label'] ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="merineo-pb-accordion-actions">
                    <label class="merineo-pb-toggle">
                        <input
                                type="hidden"
                                name="<?php echo esc_attr( $name_prefix ); ?>[enabled]"
                                value="0"
                        />
                        <input
                                type="checkbox"
                                name="<?php echo esc_attr( $name_prefix ); ?>[enabled]"
                                value="1"
                                <?php checked( $is_enabled ); ?>
                        />
                        <span><?php esc_html_e( 'Enabled', 'merineo-product-badges' ); ?></span>
                    </label>
                    <button
                            type="button"
                            class="button-link merineo-pb-accordion-toggle"
                            aria-expanded="<?php echo $is_enabled ? 'true' : 'false'; ?>"
                    >
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                        <span class="screen-reader-text">
                            <?php esc_html_e( 'Toggle badge settings', 'merineo-product-badges' ); ?>
                        </span>
                    </button>
                </div>
            </div>

            <div class="merineo-pb-accordion-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Label', 'merineo-product-badges' ); ?></label>
                        </th>
                        <td>
                            <input
                                    type="text"
                                    class="regular-text"
                                    name="<?php echo esc_attr( $name_prefix ); ?>[label]"
                                    value="<?php echo esc_attr( (string) $badge['label'] ); ?>"
                            />
                            <?php if ( 'multipack' === $key ) : ?>
                                <p class="description">
                                    <?php
                                    esc_html_e(
                                            'You can use {max_discount} in the label to insert the maximum multipack discount (e.g. "-20%" or "10 €").',
                                            'merineo-product-badges'
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ( 'multipack' === $key ) : ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr( $name_prefix . '_min_discount' ); ?>">
                                    <?php esc_html_e( 'Minimum discount to show badge (%)', 'merineo-product-badges' ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        id="<?php echo esc_attr( $name_prefix . '_min_discount' ); ?>"
                                        name="<?php echo esc_attr( $name_prefix ); ?>[min_discount]"
                                        value="<?php echo esc_attr(
                                                isset( $badge['min_discount'] )
                                                        ? (string) $badge['min_discount']
                                                        : '0'
                                        ); ?>"
                                />
                                <p class="description">
                                    <?php
                                    esc_html_e(
                                            'Badge is only shown if the effective maximum multipack discount (in percent) is greater or equal to this value.',
                                            'merineo-product-badges'
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <th scope="row"><?php esc_html_e( 'Background color', 'merineo-product-badges' ); ?></th>
                        <td>
                            <input
                                    type="text"
                                    class="merineo-pb-color-field"
                                    name="<?php echo esc_attr( $name_prefix ); ?>[bg_color]"
                                    value="<?php echo esc_attr( (string) $badge['bg_color'] ); ?>"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Text color', 'merineo-product-badges' ); ?></th>
                        <td>
                            <input
                                    type="text"
                                    class="merineo-pb-color-field"
                                    name="<?php echo esc_attr( $name_prefix ); ?>[text_color]"
                                    value="<?php echo esc_attr( (string) $badge['text_color'] ); ?>"
                            />
                        </td>
                    </tr>

                    <?php if ( $with_days ) : ?>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Days visible after product creation', 'merineo-product-badges' ); ?></label>
                            </th>
                            <td>
                                <input
                                        type="number"
                                        min="1"
                                        name="<?php echo esc_attr( $name_prefix ); ?>[days]"
                                        value="<?php echo esc_attr( (string) $badge['days'] ); ?>"
                                />
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ( $with_sale ) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Sale display mode', 'merineo-product-badges' ); ?></th>
                            <td>
                                <select name="<?php echo esc_attr( $name_prefix ); ?>[mode]">
                                    <option value="hidden" <?php selected( $badge['mode'], 'hidden' ); ?>>
                                        <?php esc_html_e( 'Do not display', 'merineo-product-badges' ); ?>
                                    </option>
                                    <option value="label" <?php selected( $badge['mode'], 'label' ); ?>>
                                        <?php esc_html_e( 'Label only', 'merineo-product-badges' ); ?>
                                    </option>
                                    <option value="percent" <?php selected( $badge['mode'], 'percent' ); ?>>
                                        <?php esc_html_e( 'Discount percent (e.g. -15 %)', 'merineo-product-badges' ); ?>
                                    </option>
                                    <option value="amount" <?php selected( $badge['mode'], 'amount' ); ?>>
                                        <?php esc_html_e( 'Saved amount (e.g. -5€)', 'merineo-product-badges' ); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ( $with_count ) : ?>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Top bestseller products', 'merineo-product-badges' ); ?></label>
                            </th>
                            <td>
                                <input
                                        type="number"
                                        min="1"
                                        name="<?php echo esc_attr( $name_prefix ); ?>[count]"
                                        value="<?php echo esc_attr( (string) $badge['count'] ); ?>"
                                />
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Standard hooks for dropdowns based on area.
     *
     * @param string $area Area key.
     *
     * @return array<string,string>
     */
    private function get_standard_hooks( string $area ): array {
        if ( 'single' === $area ) {
            return [
                    'woocommerce_before_single_product_summary' => 'woocommerce_before_single_product_summary',
                    'woocommerce_single_product_summary'       => 'woocommerce_single_product_summary',
                    'woocommerce_after_single_product_summary' => 'woocommerce_after_single_product_summary',
                    'woocommerce_before_add_to_cart_form'      => 'woocommerce_before_add_to_cart_form',
            ];
        }

        if ( 'loop' === $area ) {
            return [
                    'woocommerce_before_shop_loop_item_title' => 'woocommerce_before_shop_loop_item_title',
                    'woocommerce_after_shop_loop_item_title'  => 'woocommerce_after_shop_loop_item_title',
                    'woocommerce_after_shop_loop_item'        => 'woocommerce_after_shop_loop_item',
            ];
        }

        // For "custom" we intentionally return an empty list to force custom hook usage.
        return [];
    }
}