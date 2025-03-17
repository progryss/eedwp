<?php
/**
 * Plugin Name: Hide Prices for Guests
 * Plugin URI: https://yourwebsite.com/hide-prices-for-guests
 * Description: Hide product prices and disable purchasing for non-logged-in users in WooCommerce.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: hide-prices-for-guests
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * @package Hide_Prices_For_Guests
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'HPFG_VERSION', '1.0.0' );
define( 'HPFG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HPFG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HPFG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Hide_Prices_For_Guests {

    /**
     * Instance of this class
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Return an instance of this class
     *
     * @return object A single instance of this class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Load plugin text domain
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Check if WooCommerce is active
        if ( $this->is_woocommerce_active() ) {
            $this->includes();
            $this->init_hooks();
        } else {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
        }
        
        // Register activation hook
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if they don't exist
        if ( ! get_option( 'hpfg_options' ) ) {
            $defaults = array(
                'enable' => true,
                'message' => __( 'Please <a href="%login_url%">login</a> to view prices.', 'hide-prices-for-guests' ),
                'excluded_products' => '',
                'excluded_categories' => '',
                'enable_debug' => false,
            );
            
            update_option( 'hpfg_options', $defaults );
        }
        
        // Set activation notice
        if ( class_exists( 'HPFG_Admin_Notices' ) ) {
            HPFG_Admin_Notices::set_activation_notice();
        }
    }

    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'hide-prices-for-guests',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public function is_woocommerce_active() {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
            true
        );
    }

    /**
     * Include required files
     */
    public function includes() {
        require_once HPFG_PLUGIN_DIR . 'includes/class-settings.php';
        require_once HPFG_PLUGIN_DIR . 'includes/class-admin-notices.php';
        
        // Initialize admin notices
        new HPFG_Admin_Notices();
    }

    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Initialize settings
        new HPFG_Settings();

        // Enqueue styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

        // Filter price HTML
        add_filter( 'woocommerce_get_price_html', array( $this, 'hide_price_for_guests' ), 100, 2 );
        add_filter( 'woocommerce_cart_item_price', array( $this, 'hide_cart_price_for_guests' ), 100, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'hide_cart_price_for_guests' ), 100, 3 );
        add_filter( 'woocommerce_get_variation_price_html', array( $this, 'hide_price_for_guests' ), 100, 2 );

        // Disable purchase - "Add to Cart" buttons are always hidden when prices are hidden
        add_filter( 'woocommerce_is_purchasable', array( $this, 'disable_purchase_for_guests' ), 100, 2 );
        add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'disable_purchase_for_guests' ), 100, 2 );
        
        // Fix "Add to Cart" button text for all product types
        add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'fix_add_to_cart_text' ), 100, 2 );
        add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'fix_add_to_cart_text' ), 100, 2 );
        
        // Specific filters for variable products
        add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'fix_variable_add_to_cart_text' ), 110, 2 );
        
        // Change button URL for non-excluded products to link to the product page
        add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'fix_add_to_cart_url' ), 100, 2 );
        
        // Note: We no longer use the body class approach for hiding add to cart buttons
        // We now use selective CSS instead
        
        // Add plugin action links
        add_filter( 'plugin_action_links_' . HPFG_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
    }
    
    /**
     * Add plugin action links
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=hide-prices-for-guests' ) . '">' . __( 'Settings', 'hide-prices-for-guests' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'hide-prices-for-guests',
            HPFG_PLUGIN_URL . 'assets/css/hide-prices.css',
            array(),
            HPFG_VERSION
        );
        
        // Only add inline CSS if the plugin is enabled
        $options = HPFG_Settings::get_options();
        if ($options['enable'] && !is_user_logged_in()) {
            $this->add_selective_css();
        }
    }
    
    /**
     * Add selective CSS to hide add to cart buttons only for non-excluded products
     */
    private function add_selective_css() {
        $options = HPFG_Settings::get_options();
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        // Get excluded product IDs
        $excluded_product_ids = array();
        if (!empty($options['excluded_products'])) {
            $excluded_product_ids = array_map('intval', array_map('trim', explode(',', $options['excluded_products'])));
        }
        
        // Get excluded category IDs
        $excluded_category_ids = array();
        if (!empty($options['excluded_categories'])) {
            $excluded_category_ids = array_map('intval', array_map('trim', explode(',', $options['excluded_categories'])));
        }
        
        if ($debug) {
            error_log('HPFG Debug: Generating selective CSS for excluded products: ' . implode(',', $excluded_product_ids));
            error_log('HPFG Debug: Generating selective CSS for excluded categories: ' . implode(',', $excluded_category_ids));
        }
        
        // Instead of hiding buttons completely, we'll make them non-functional but still visible for non-excluded products
        // But we'll keep them visible so the "Read more" text can be shown
        $css = "
        /* Make add to cart buttons non-functional but still visible for non-excluded products */
        .add_to_cart_button.ajax_add_to_cart,
        .single_add_to_cart_button {
            pointer-events: none;
            cursor: default;
        }
        
        /* Hide quantity inputs and variation dropdowns for non-excluded products */
        .quantity,
        .variations select,
        .reset_variations,
        .woocommerce-variation-description,
        .woocommerce-variation-price,
        .woocommerce-variation-availability {
            display: none !important;
        }";
        
        // Then add exceptions for excluded products to make their buttons functional
        foreach ($excluded_product_ids as $product_id) {
            $css .= "
            /* Make buttons functional for excluded products */
            .post-{$product_id} .add_to_cart_button,
            .product-{$product_id} .add_to_cart_button,
            body.postid-{$product_id} .single_add_to_cart_button,
            [data-product_id='{$product_id}'] .add_to_cart_button {
                pointer-events: auto !important;
                cursor: pointer !important;
            }
            
            /* Show quantity inputs and variation dropdowns for excluded products */
            body.postid-{$product_id} .quantity,
            body.postid-{$product_id} .variations select,
            body.postid-{$product_id} .reset_variations,
            body.postid-{$product_id} .woocommerce-variation-description,
            body.postid-{$product_id} .woocommerce-variation-price,
            body.postid-{$product_id} .woocommerce-variation-availability {
                display: block !important;
            }";
            
            // Also get all variations of this product if it's a variable product
            $product = wc_get_product($product_id);
            if ($product && $product->is_type('variable')) {
                $variations = $product->get_children();
                if ($debug) {
                    error_log('HPFG Debug: Variable product ' . $product_id . ' has variations: ' . implode(',', $variations));
                }
                
                foreach ($variations as $variation_id) {
                    $css .= "
                    /* Make variation buttons functional */
                    [data-product_id='{$variation_id}'] .add_to_cart_button,
                    [data-variation_id='{$variation_id}'] .single_add_to_cart_button {
                        pointer-events: auto !important;
                        cursor: pointer !important;
                    }";
                }
            }
        }
        
        // Add exceptions for products in excluded categories
        if (!empty($excluded_category_ids)) {
            // Get all products in excluded categories
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $excluded_category_ids,
                    ),
                ),
            );
            
            $products_in_excluded_categories = get_posts($args);
            
            if ($debug) {
                error_log('HPFG Debug: Products in excluded categories: ' . implode(',', $products_in_excluded_categories));
            }
            
            foreach ($products_in_excluded_categories as $product_id) {
                $css .= "
                /* Make buttons functional for products in excluded categories */
                .post-{$product_id} .add_to_cart_button,
                .product-{$product_id} .add_to_cart_button,
                body.postid-{$product_id} .single_add_to_cart_button,
                [data-product_id='{$product_id}'] .add_to_cart_button {
                    pointer-events: auto !important;
                    cursor: pointer !important;
                }
                
                /* Show quantity inputs and variation dropdowns for products in excluded categories */
                body.postid-{$product_id} .quantity,
                body.postid-{$product_id} .variations select,
                body.postid-{$product_id} .reset_variations,
                body.postid-{$product_id} .woocommerce-variation-description,
                body.postid-{$product_id} .woocommerce-variation-price,
                body.postid-{$product_id} .woocommerce-variation-availability {
                    display: block !important;
                }";
                
                // Also get all variations of this product if it's a variable product
                $product = wc_get_product($product_id);
                if ($product && $product->is_type('variable')) {
                    $variations = $product->get_children();
                    if ($debug) {
                        error_log('HPFG Debug: Variable product ' . $product_id . ' in excluded category has variations: ' . implode(',', $variations));
                    }
                    
                    foreach ($variations as $variation_id) {
                        $css .= "
                        /* Make variation buttons functional */
                        [data-product_id='{$variation_id}'] .add_to_cart_button,
                        [data-variation_id='{$variation_id}'] .single_add_to_cart_button {
                            pointer-events: auto !important;
                            cursor: pointer !important;
                        }";
                    }
                }
            }
            
            // Also add general category selectors
            foreach ($excluded_category_ids as $category_id) {
                $css .= "
                /* Make buttons functional for products in excluded categories */
                .product_cat-{$category_id} .add_to_cart_button,
                .product_cat-{$category_id} .single_add_to_cart_button {
                    pointer-events: auto !important;
                    cursor: pointer !important;
                }
                
                /* Show quantity inputs and variation dropdowns for products in excluded categories */
                .product_cat-{$category_id} .quantity,
                .product_cat-{$category_id} .variations select,
                .product_cat-{$category_id} .reset_variations,
                .product_cat-{$category_id} .woocommerce-variation-description,
                .product_cat-{$category_id} .woocommerce-variation-price,
                .product_cat-{$category_id} .woocommerce-variation-availability {
                    display: block !important;
                }";
            }
        }
        
        // Add the CSS to the page
        wp_add_inline_style('hide-prices-for-guests', $css);
    }

    /**
     * Hide price for guests
     *
     * @param string $price_html The price HTML.
     * @param object $product The product object.
     * @return string
     */
    public function hide_price_for_guests( $price_html, $product ) {
        // Get plugin options
        $options = HPFG_Settings::get_options();

        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $price_html;
        }

        // If user is logged in, show the price
        if ( is_user_logged_in() ) {
            return $price_html;
        }
        
        // Check if product is excluded
        if ( $this->is_product_excluded( $product->get_id() ) ) {
            return $price_html;
        }

        // Replace price with custom message
        $message = $options['message'];
        $login_url = wp_login_url( get_permalink() );
        $message = str_replace( '%login_url%', esc_url( $login_url ), $message );

        return '<div class="hpfg-hidden-price-message">' . wp_kses_post( $message ) . '</div>';
    }

    /**
     * Hide cart price for guests
     *
     * @param string $price_html The price HTML.
     * @param array  $cart_item The cart item.
     * @param string $cart_item_key The cart item key.
     * @return string
     */
    public function hide_cart_price_for_guests( $price_html, $cart_item, $cart_item_key ) {
        // Get plugin options
        $options = HPFG_Settings::get_options();

        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $price_html;
        }

        // If user is logged in, show the price
        if ( is_user_logged_in() ) {
            return $price_html;
        }
        
        // Check if product is excluded
        if ( isset( $cart_item['product_id'] ) && $this->is_product_excluded( $cart_item['product_id'] ) ) {
            return $price_html;
        }

        // Replace price with custom message
        $message = $options['message'];
        $login_url = wp_login_url( wc_get_cart_url() );
        $message = str_replace( '%login_url%', esc_url( $login_url ), $message );

        return '<div class="hpfg-hidden-price-message">' . wp_kses_post( $message ) . '</div>';
    }

    /**
     * Disable purchase for guests
     *
     * @param bool   $purchasable Whether the product is purchasable.
     * @param object $product The product object.
     * @return bool
     */
    public function disable_purchase_for_guests( $purchasable, $product ) {
        // Get plugin options
        $options = HPFG_Settings::get_options();

        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];

        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $purchasable;
        }

        // If user is logged in, allow purchase
        if ( is_user_logged_in() ) {
            return $purchasable;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // For variations, we need to check both the variation ID and the parent product ID
        $parent_id = $product->get_parent_id();
        
        if ($debug) {
            error_log('HPFG Debug: Checking purchasable status for product ID: ' . $product_id . ', parent ID: ' . $parent_id);
        }
        
        // Check if product is excluded
        if ($this->is_product_excluded($product_id)) {
            if ($debug) {
                error_log('HPFG Debug: Product ' . $product_id . ' is excluded, allowing purchase');
            }
            return $purchasable;
        }
        
        // If this is a variation, also check if the parent product is excluded
        if ($parent_id && $this->is_product_excluded($parent_id)) {
            if ($debug) {
                error_log('HPFG Debug: Parent product ' . $parent_id . ' is excluded, allowing purchase for variation ' . $product_id);
            }
            return $purchasable;
        }
        
        // Check if product belongs to an excluded category
        if ($this->product_in_excluded_category($product_id) || ($parent_id && $this->product_in_excluded_category($parent_id))) {
            if ($debug) {
                error_log('HPFG Debug: Product ' . $product_id . ' or its parent is in an excluded category, allowing purchase');
            }
            return $purchasable;
        }

        // Disable purchase for guests
        if ($debug) {
            error_log('HPFG Debug: Product ' . $product_id . ' is not excluded, disabling purchase');
        }
        return false;
    }
    
    /**
     * Check if product belongs to an excluded category
     *
     * @param int $product_id The product ID.
     * @return bool
     */
    public function product_in_excluded_category($product_id) {
        // Get plugin options
        $options = HPFG_Settings::get_options();

        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        // Check excluded categories
        if (!empty($options['excluded_categories'])) {
            $excluded_categories = array_map('intval', array_map('trim', explode(',', $options['excluded_categories'])));
            $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            
            if (is_array($product_categories) && !empty($product_categories)) {
                foreach ($excluded_categories as $category_id) {
                    if (in_array($category_id, $product_categories, true)) {
                        if ($debug) {
                            error_log('HPFG Debug: Product ' . $product_id . ' is in excluded category ' . $category_id);
                        }
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if product is excluded
     *
     * @param int $product_id The product ID.
     * @return bool
     */
    public function is_product_excluded( $product_id ) {
        // Get plugin options
        $options = HPFG_Settings::get_options();
        
        // Ensure product_id is an integer
        $product_id = intval($product_id);
        
        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        if ($debug) {
            error_log('HPFG Debug: Checking if product ' . $product_id . ' is excluded');
            error_log('HPFG Debug: Excluded products: ' . $options['excluded_products']);
        }
        
        // Check excluded products
        if ( ! empty( $options['excluded_products'] ) ) {
            $excluded_products = array_map( 'intval', array_map( 'trim', explode( ',', $options['excluded_products'] ) ) );
            
            if ($debug) {
                error_log('HPFG Debug: Excluded product IDs after processing: ' . implode(',', $excluded_products));
            }
            
            if ( in_array( $product_id, $excluded_products, true ) ) {
                if ($debug) {
                    error_log( 'HPFG Debug: Product ' . $product_id . ' is excluded by product ID' );
                }
                return true;
            }
        }
        
        // Check if product belongs to an excluded category
        if ($this->product_in_excluded_category($product_id)) {
                        return true;
                    }
        
        if ($debug) {
            error_log('HPFG Debug: Product ' . $product_id . ' is NOT excluded');
        }
        
        return false;
    }

    /**
     * Fix "Add to Cart" button text for excluded products
     *
     * @param string $text The button text.
     * @param object $product The product object.
     * @return string
     */
    public function fix_add_to_cart_text( $text, $product = null ) {
        // If no product is provided, return the original text
        if ( ! $product ) {
            return $text;
        }
        
        // Get plugin options
        $options = HPFG_Settings::get_options();
        
        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $text;
        }
        
        // If user is logged in, return the original text
        if ( is_user_logged_in() ) {
            return $text;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();
        
        // Check if product is excluded
        $is_excluded = $this->is_product_excluded($product_id) || 
                      ($parent_id && $this->is_product_excluded($parent_id)) ||
                      $this->product_in_excluded_category($product_id) ||
                      ($parent_id && $this->product_in_excluded_category($parent_id));
        
        if ($is_excluded) {
            if ($debug) {
                error_log('HPFG Debug: Product ' . $product_id . ' is excluded, keeping original add to cart text: ' . $text);
            }
            return $text;
        }
        
        // For non-excluded products, we'll change the text to "Read more"
        // This ensures the button is still visible but indicates it's for viewing the product
        if ($debug) {
            error_log('HPFG Debug: Product ' . $product_id . ' is not excluded, changing button text to "Read more"');
        }
        
        return __('Read more', 'hide-prices-for-guests');
    }

    /**
     * Fix "Add to Cart" button text for variable products
     *
     * @param string $text The button text.
     * @param object $product The product object.
     * @return string
     */
    public function fix_variable_add_to_cart_text( $text, $product ) {
        // Only process variable products
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return $text;
        }
        
        // Get plugin options
        $options = HPFG_Settings::get_options();
        
        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $text;
        }
        
        // If user is logged in, return the original text
        if ( is_user_logged_in() ) {
            return $text;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        
        // Check if product is excluded
        $is_excluded = $this->is_product_excluded($product_id) || $this->product_in_excluded_category($product_id);
        
        if ($is_excluded) {
            if ($debug) {
                error_log('HPFG Debug: Variable product ' . $product_id . ' is excluded, keeping original text: ' . $text);
            }
            return $text; // Keep "Select options" for excluded variable products
        }
        
        // For non-excluded variable products, change to "Read more"
        if ($debug) {
            error_log('HPFG Debug: Variable product ' . $product_id . ' is not excluded, changing text to "Read more"');
        }
        
        return __('Read more', 'hide-prices-for-guests');
    }

    /**
     * Fix "Add to Cart" button URL for non-excluded products
     *
     * @param string $url The button URL.
     * @param object $product The product object.
     * @return string
     */
    public function fix_add_to_cart_url( $url, $product ) {
        // If no product is provided, return the original URL
        if ( ! $product ) {
            return $url;
        }
        
        // Get plugin options
        $options = HPFG_Settings::get_options();
        
        // Debug mode
        $debug = isset($options['enable_debug']) && $options['enable_debug'];
        
        // Check if hiding prices is enabled
        if ( ! $options['enable'] ) {
            return $url;
        }
        
        // If user is logged in, return the original URL
        if ( is_user_logged_in() ) {
            return $url;
        }
        
        // Get product ID
        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();
        
        // Check if product is excluded
        $is_excluded = $this->is_product_excluded($product_id) || 
                      ($parent_id && $this->is_product_excluded($parent_id)) ||
                      $this->product_in_excluded_category($product_id) ||
                      ($parent_id && $this->product_in_excluded_category($parent_id));
        
        if ($is_excluded) {
            if ($debug) {
                error_log('HPFG Debug: Product ' . $product_id . ' is excluded, keeping original add to cart URL');
            }
            return $url; // Keep original URL for excluded products
        }
        
        // For non-excluded products, change URL to product permalink
        $permalink = get_permalink($product_id);
        
        if ($debug) {
            error_log('HPFG Debug: Product ' . $product_id . ' is not excluded, changing URL to: ' . $permalink);
        }
        
        return $permalink;
    }
}

/**
 * Initialize the plugin
 */
function hide_prices_for_guests() {
    return Hide_Prices_For_Guests::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'hide_prices_for_guests' ); 