<?php
declare(strict_types=1);

namespace Merineo\ProductBadges\Admin;

use Merineo\ProductBadges\Common\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Page {

    private Settings $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function hooks(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'connect_page_callback' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_settings(): void {
        $this->settings->register();
    }

    public function connect_page_callback(): void {
        global $_registered_pages;
        $hookname = get_plugin_page_hookname( 'merineo-product-badges', 'merineo-settings-page' );
        if ( ! empty( $hookname ) && isset( $_registered_pages[ $hookname ] ) ) {
            $_registered_pages[ $hookname ] = [ $this, 'render_page' ];
        }
    }

    public function enqueue_assets( string $hook_suffix ): void {
        if ( 'merineo-settings-page_page_merineo-product-badges' !== $hook_suffix ) {
            return;
        }
        $settings = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );
        if ( ! empty( $settings ) ) {
            wp_add_inline_script(
                'code-editor',
                'jQuery(function(){wp.codeEditor.initialize("merineo_pb_custom_css",' . wp_json_encode( $settings ) . ');});'
            );
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'merineo-product-badges' ) );
        }
        $options = $this->settings->all();
        ?>
        <div class="wrap merineo-pb-settings">
            <h1><?php esc_html_e( 'Merineo Product Badges', 'merineo-product-badges' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'merineo_product_badges' ); ?>
                <h2><?php esc_html_e( 'General settings', 'merineo-product-badges' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="merineo_pb_enabled"><?php esc_html_e( 'Enable product badges', 'merineo-product-badges' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="merineo_pb_enabled" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][enabled]" value="1" <?php checked( ! empty( $options['general']['enabled'] ) ); ?> />
                                <?php esc_html_e( 'Turn the entire feature on or off globally.', 'merineo-product-badges' ); ?>
                            </label>
                        </td>
                    </tr>
                    <?php
                    $this->placement_row( 'single', __( 'Single product page', 'merineo-product-badges' ), $options );
                    $this->placement_row( 'loop', __( 'Product archive / category', 'merineo-product-badges' ), $options );
                    $this->placement_row( 'custom', __( 'Custom hook', 'merineo-product-badges' ), $options );
                    ?>
                    <tr>
                        <th scope="row"><label for="merineo_pb_font_size"><?php esc_html_e( 'Font size (px)', 'merineo-product-badges' ); ?></label></th>
                        <td><input type="number" step="0.1" min="8" id="merineo_pb_font_size" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][font_size]" value="<?php echo esc_attr( (string) $options['general']['font_size'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Text transform', 'merineo-product-badges' ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][text_transform]">
                                <option value="none" <?php selected( $options['general']['text_transform'], 'none' ); ?>><?php esc_html_e( 'Normal', 'merineo-product-badges' ); ?></option>
                                <option value="uppercase" <?php selected( $options['general']['text_transform'], 'uppercase' ); ?>><?php esc_html_e( 'Uppercase', 'merineo-product-badges' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Font weight', 'merineo-product-badges' ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][font_weight]">
                                <option value="normal" <?php selected( $options['general']['font_weight'], 'normal' ); ?>><?php esc_html_e( 'Normal', 'merineo-product-badges' ); ?></option>
                                <option value="bold" <?php selected( $options['general']['font_weight'], 'bold' ); ?>><?php esc_html_e( 'Bold', 'merineo-product-badges' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="merineo_pb_letter_spacing"><?php esc_html_e( 'Letter spacing (px)', 'merineo-product-badges' ); ?></label></th>
                        <td><input type="number" step="0.1" id="merineo_pb_letter_spacing" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][letter_spacing]" value="<?php echo esc_attr( (string) $options['general']['letter_spacing'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Badge shadow', 'merineo-product-badges' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][shadow_enabled]" value="1" <?php checked( ! empty( $options['general']['shadow_enabled'] ) ); ?> />
                                <?php esc_html_e( 'Enable subtle shadow on badges.', 'merineo-product-badges' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Automatic badges', 'merineo-product-badges' ); ?></h2>
                <?php
                $this->auto_section( 'new', __( 'New', 'merineo-product-badges' ), $options['automatic']['new'], true, false );
                $this->auto_section( 'featured', __( 'Recommended', 'merineo-product-badges' ), $options['automatic']['featured'] );
                $this->auto_section( 'sale', __( 'Sale', 'merineo-product-badges' ), $options['automatic']['sale'], false, true );
                $this->auto_section( 'outofstock', __( 'Out of stock', 'merineo-product-badges' ), $options['automatic']['outofstock'] );
                $this->auto_section( 'instock', __( 'In stock', 'merineo-product-badges' ), $options['automatic']['instock'] );
                $this->auto_section( 'backorder', __( 'Backorder', 'merineo-product-badges' ), $options['automatic']['backorder'] );
                $this->auto_section( 'bestseller', __( 'Bestseller', 'merineo-product-badges' ), $options['automatic']['bestseller'], false, false, true );
                ?>

                <h2><?php esc_html_e( 'Custom CSS', 'merineo-product-badges' ); ?></h2>
                <p><?php esc_html_e( 'CSS is scoped under .merineo-badges-scope.', 'merineo-product-badges' ); ?></p>
                <textarea id="merineo_pb_custom_css" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[css][custom]" rows="10" cols="60" class="large-text code"><?php echo esc_textarea( (string) $options['css']['custom'] ); ?></textarea>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function placement_row( string $key, string $label, array $options ): void {
        $area = $options['general'][ $key ];
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <p>
                    <label>
                        <select name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][<?php echo esc_attr( $key ); ?>][hook]">
                            <option value=""><?php esc_html_e( 'Custom hook only', 'merineo-product-badges' ); ?></option>
                            <?php foreach ( $this->standard_hooks( $key ) as $hook => $hook_label ) : ?>
                                <option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $area['hook'], $hook ); ?>><?php echo esc_html( $hook_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
                <p>
                    <label>
                        <?php esc_html_e( 'Custom hook name', 'merineo-product-badges' ); ?><br/>
                        <input type="text" class="regular-text" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][<?php echo esc_attr( $key ); ?>][custom_hook]" value="<?php echo esc_attr( (string) $area['custom_hook'] ); ?>" />
                    </label>
                </p>
                <p>
                    <label>
                        <?php esc_html_e( 'Priority', 'merineo-product-badges' ); ?>
                        <input type="number" name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][<?php echo esc_attr( $key ); ?>][priority]" value="<?php echo esc_attr( (string) $area['priority'] ); ?>" min="1" />
                    </label>
                </p>
                <p>
                    <label>
                        <?php esc_html_e( 'Alignment', 'merineo-product-badges' ); ?>
                        <select name="<?php echo esc_attr( MERINEO_PB_OPTION_NAME ); ?>[general][<?php echo esc_attr( $key ); ?>][align]">
                            <option value="left" <?php selected( $area['align'], 'left' ); ?>><?php esc_html_e( 'Left', 'merineo-product-badges' ); ?></option>
                            <option value="right" <?php selected( $area['align'], 'right' ); ?>><?php esc_html_e( 'Right', 'merineo-product-badges' ); ?></option>
                        </select>
                    </label>
                </p>
            </td>
        </tr>
        <?php
    }

    private function auto_section( string $key, string $title, array $badge, bool $with_days = false, bool $with_sale = false, bool $with_count = false ): void {
        $name_prefix = MERINEO_PB_OPTION_NAME . '[automatic][' . $key . ']';
        ?>
        <h3><?php echo esc_html( $title ); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enabled', 'merineo-product-badges' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[enabled]" value="1" <?php checked( ! empty( $badge['enabled'] ) ); ?> />
                        <?php esc_html_e( 'Display this badge when conditions match.', 'merineo-product-badges' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Label', 'merineo-product-badges' ); ?></th>
                <td><input type="text" class="regular-text" name="<?php echo esc_attr( $name_prefix ); ?>[label]" value="<?php echo esc_attr( (string) $badge['label'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Background color', 'merineo-product-badges' ); ?></th>
                <td><input type="text" class="merineo-pb-color-field" name="<?php echo esc_attr( $name_prefix ); ?>[bg_color]" value="<?php echo esc_attr( (string) $badge['bg_color'] ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Text color', 'merineo-product-badges' ); ?></th>
                <td><input type="text" class="merineo-pb-color-field" name="<?php echo esc_attr( $name_prefix ); ?>[text_color]" value="<?php echo esc_attr( (string) $badge['text_color'] ); ?>" /></td>
            </tr>
            <?php if ( $with_days ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Days visible after product creation', 'merineo-product-badges' ); ?></th>
                    <td><input type="number" min="1" name="<?php echo esc_attr( $name_prefix ); ?>[days]" value="<?php echo esc_attr( (string) $badge['days'] ); ?>" /></td>
                </tr>
            <?php endif; ?>
            <?php if ( $with_sale ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Sale display mode', 'merineo-product-badges' ); ?></th>
                    <td>
                        <select name="<?php echo esc_attr( $name_prefix ); ?>[mode]">
                            <option value="hidden" <?php selected( $badge['mode'], 'hidden' ); ?>><?php esc_html_e( 'Do not display', 'merineo-product-badges' ); ?></option>
                            <option value="label" <?php selected( $badge['mode'], 'label' ); ?>><?php esc_html_e( 'Label only', 'merineo-product-badges' ); ?></option>
                            <option value="percent" <?php selected( $badge['mode'], 'percent' ); ?>><?php esc_html_e( 'Percent (e.g. -15 %)', 'merineo-product-badges' ); ?></option>
                            <option value="amount" <?php selected( $badge['mode'], 'amount' ); ?>><?php esc_html_e( 'Saved amount (e.g. -5€)', 'merineo-product-badges' ); ?></option>
                        </select>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ( $with_count ) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Top bestseller products', 'merineo-product-badges' ); ?></th>
                    <td><input type="number" min="1" name="<?php echo esc_attr( $name_prefix ); ?>[count]" value="<?php echo esc_attr( (string) $badge['count'] ); ?>" /></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    private function standard_hooks( string $area ): array {
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
        return [];
    }
}
