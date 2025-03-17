<?php
/**
 * Plugin Name: Company Accounts Manager
 * Plugin URI: https://yourwebsite.com/company-accounts-manager
 * Description: Manages company accounts with child account creation, order tracking, and admin approval system.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: company-accounts-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CAM_VERSION', '1.0.0');
define('CAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files for activation
require_once CAM_PLUGIN_DIR . 'includes/class-cam-install.php';
require_once CAM_PLUGIN_DIR . 'includes/class-cam-roles.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('CAM_Install', 'activate'));
register_deactivation_hook(__FILE__, array('CAM_Install', 'deactivate'));

// Main plugin class
class CompanyAccountsManager {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin files
        $this->includes();

        // Initialize features
        $this->init_features();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('Company Accounts Manager requires WooCommerce to be installed and active.', 'company-accounts-manager'); ?></p>
        </div>
        <?php
    }

    private function includes() {
        // Include required files (class-cam-install.php and class-cam-roles.php are already included)
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-company-admin.php';
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-child-account.php';
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-order-manager.php';
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-admin.php';
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-tiers.php';
        require_once CAM_PLUGIN_DIR . 'includes/class-cam-pricing.php';
    }

    private function init_features() {
        // Initialize plugin features
        add_action('init', array($this, 'load_textdomain'));
        
        // Initialize admin functionality
        if (is_admin()) {
            CAM_Admin::init();
        }
        
        // Initialize company admin functionality
        CAM_Company_Admin::init();
        
        // Initialize roles
        CAM_Roles::init();
        
        // Initialize tiers
        CAM_Tiers::init();
        
        // Initialize child account functionality
        CAM_Child_Account::init();
        
        // Initialize order manager
        CAM_Order_Manager::init();
        
        // Initialize pricing
        CAM_Pricing::init();
    }

    public function load_textdomain() {
        load_plugin_textdomain('company-accounts-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Initialize the plugin
function CAM() {
    return CompanyAccountsManager::instance();
}

// Start the plugin
CAM();

// Register shortcode directly
add_shortcode('company_registration_form', array('CAM_Company_Admin', 'registration_form_shortcode')); 