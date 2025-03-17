<?php
/**
 * Settings class for Hide Prices for Guests plugin
 *
 * @package Hide_Prices_For_Guests
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HPFG_Settings
 * 
 * Handles the settings page and options for the Hide Prices for Guests plugin.
 */
class HPFG_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        // Add settings page to menu
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings page to WooCommerce submenu
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Hide Prices for Guests', 'hide-prices-for-guests' ),
            __( 'Hide Prices for Guests', 'hide-prices-for-guests' ),
            'manage_options',
            'hide-prices-for-guests',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'hpfg_settings',
            'hpfg_options',
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'hpfg_main_section',
            __( 'General Settings', 'hide-prices-for-guests' ),
            array( $this, 'render_section_info' ),
            'hide-prices-for-guests'
        );

        add_settings_field(
            'hpfg_enable',
            __( 'Enable Hide Prices', 'hide-prices-for-guests' ),
            array( $this, 'render_enable_field' ),
            'hide-prices-for-guests',
            'hpfg_main_section'
        );

        add_settings_field(
            'hpfg_message',
            __( 'Message for Guests', 'hide-prices-for-guests' ),
            array( $this, 'render_message_field' ),
            'hide-prices-for-guests',
            'hpfg_main_section'
        );
        
        add_settings_field(
            'hpfg_excluded_products',
            __( 'Excluded Products', 'hide-prices-for-guests' ),
            array( $this, 'render_excluded_products_field' ),
            'hide-prices-for-guests',
            'hpfg_main_section'
        );
        
        add_settings_field(
            'hpfg_excluded_categories',
            __( 'Excluded Categories', 'hide-prices-for-guests' ),
            array( $this, 'render_excluded_categories_field' ),
            'hide-prices-for-guests',
            'hpfg_main_section'
        );
        
        add_settings_field(
            'hpfg_enable_debug',
            __( 'Enable Debug Mode', 'hide-prices-for-guests' ),
            array( $this, 'render_enable_debug_field' ),
            'hide-prices-for-guests',
            'hpfg_main_section'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input The input array.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();

        if ( isset( $input['enable'] ) ) {
            $sanitized_input['enable'] = (bool) $input['enable'];
        } else {
            $sanitized_input['enable'] = false;
        }

        if ( isset( $input['message'] ) ) {
            $sanitized_input['message'] = wp_kses_post( $input['message'] );
        } else {
            $sanitized_input['message'] = __( 'Please <a href="%login_url%">login</a> to view prices.', 'hide-prices-for-guests' );
        }
        
        // Always set hide_add_to_cart to true as we're removing the option
        $sanitized_input['hide_add_to_cart'] = true;
        
        if ( isset( $input['excluded_products'] ) ) {
            $sanitized_input['excluded_products'] = sanitize_text_field( $input['excluded_products'] );
        } else {
            $sanitized_input['excluded_products'] = '';
        }
        
        if ( isset( $input['excluded_categories'] ) ) {
            $sanitized_input['excluded_categories'] = sanitize_text_field( $input['excluded_categories'] );
        } else {
            $sanitized_input['excluded_categories'] = '';
        }
        
        if ( isset( $input['enable_debug'] ) ) {
            $sanitized_input['enable_debug'] = (bool) $input['enable_debug'];
        } else {
            $sanitized_input['enable_debug'] = false;
        }

        return $sanitized_input;
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'hpfg_settings' );
                do_settings_sections( 'hide-prices-for-guests' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section info
     */
    public function render_section_info() {
        echo '<p>' . esc_html__( 'Configure how prices are hidden for non-logged in users.', 'hide-prices-for-guests' ) . '</p>';
    }

    /**
     * Render enable field
     */
    public function render_enable_field() {
        $options = get_option( 'hpfg_options', array(
            'enable' => true,
        ) );
        ?>
        <label>
            <input type="checkbox" name="hpfg_options[enable]" value="1" <?php checked( isset( $options['enable'] ) ? $options['enable'] : false ); ?> />
            <?php esc_html_e( 'Enable hiding prices for non-logged in users', 'hide-prices-for-guests' ); ?>
        </label>
        <?php
    }

    /**
     * Render message field
     */
    public function render_message_field() {
        $options = get_option( 'hpfg_options', array(
            'message' => __( 'Please <a href="%login_url%">login</a> to view prices.', 'hide-prices-for-guests' ),
        ) );
        ?>
        <textarea name="hpfg_options[message]" rows="3" cols="50" class="large-text"><?php echo esc_textarea( isset( $options['message'] ) ? $options['message'] : '' ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Message to display instead of the price. Use %login_url% as a placeholder for the login URL.', 'hide-prices-for-guests' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render excluded products field
     */
    public function render_excluded_products_field() {
        $options = get_option( 'hpfg_options', array(
            'excluded_products' => '',
        ) );
        ?>
        <input type="text" name="hpfg_options[excluded_products]" value="<?php echo esc_attr( isset( $options['excluded_products'] ) ? $options['excluded_products'] : '' ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter product IDs separated by commas (e.g., "123,456,789") to exclude them from price hiding. You can find the product ID in the URL when editing a product (e.g., post.php?post=123&action=edit).', 'hide-prices-for-guests' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render excluded categories field
     */
    public function render_excluded_categories_field() {
        $options = get_option( 'hpfg_options', array(
            'excluded_categories' => '',
        ) );
        ?>
        <input type="text" name="hpfg_options[excluded_categories]" value="<?php echo esc_attr( isset( $options['excluded_categories'] ) ? $options['excluded_categories'] : '' ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter category IDs separated by commas (e.g., "10,15,20") to exclude all products in these categories from price hiding. You can find the category ID in the URL when editing a category (e.g., term.php?taxonomy=product_cat&tag_ID=10).', 'hide-prices-for-guests' ); ?>
        </p>
        <?php
    }

    /**
     * Render enable debug field
     */
    public function render_enable_debug_field() {
        $options = get_option( 'hpfg_options', array(
            'enable_debug' => false,
        ) );
        ?>
        <label>
            <input type="checkbox" name="hpfg_options[enable_debug]" value="1" <?php checked( isset( $options['enable_debug'] ) ? $options['enable_debug'] : false ); ?> />
            <?php esc_html_e( 'Enable debug mode (logs exclusion information to WordPress error log)', 'hide-prices-for-guests' ); ?>
        </label>
        <?php
    }

    /**
     * Get plugin options
     *
     * @return array
     */
    public static function get_options() {
        $defaults = array(
            'enable' => true,
            'message' => __( 'Please <a href="%login_url%">login</a> to view prices.', 'hide-prices-for-guests' ),
            'hide_add_to_cart' => true, // Always true now
            'excluded_products' => '',
            'excluded_categories' => '',
            'enable_debug' => false,
        );

        $options = get_option( 'hpfg_options', $defaults );
        
        // Always set hide_add_to_cart to true
        $options['hide_add_to_cart'] = true;
        
        return wp_parse_args( $options, $defaults );
    }
} 