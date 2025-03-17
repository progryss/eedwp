<?php
/**
 * Installation related functions and actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Install {
    /**
     * Plugin activation
     */
    public static function activate() {
        error_log('CAM: Plugin activation started');
        
        // Create database tables
        self::create_tables();
        
        // Add roles and capabilities
        CAM_Roles::add_roles();
        
        // Create registration page
        self::create_registration_page();
        
        // Set default options
        self::create_options();
        
        // Set version
        update_option('cam_version', CAM_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('CAM: Plugin activation completed');
    }

    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        error_log('CAM: Starting table creation');
        
        $charset_collate = $wpdb->get_charset_collate();

        // Company table
        $sql_company = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_companies (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            company_name varchar(255) NOT NULL,
            industry varchar(255),
            company_info text,
            registration_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL DEFAULT 'pending',
            tier_id bigint(20) DEFAULT NULL,
            admin_status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Child accounts table
        $sql_child = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_child_accounts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            company_id bigint(20) NOT NULL,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY company_id (company_id)
        ) $charset_collate;";

        // Company orders table
        $sql_orders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_company_orders (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            company_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            order_total decimal(10,2) NOT NULL DEFAULT 0,
            order_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY company_id (company_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Discount tiers table
        $sql_tiers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_discount_tiers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tier_name varchar(255) NOT NULL,
            discount_percentage decimal(5,2) NOT NULL DEFAULT 0,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Try to create tables using dbDelta first
        error_log('CAM: Attempting to create tables using dbDelta');
        
        // Execute the SQL queries to create tables
        $result_company = dbDelta($sql_company);
        $result_child = dbDelta($sql_child);
        $result_orders = dbDelta($sql_orders);
        $result_tiers = dbDelta($sql_tiers);
        
        error_log('CAM: dbDelta results - Company: ' . print_r($result_company, true));
        error_log('CAM: dbDelta results - Child: ' . print_r($result_child, true));
        error_log('CAM: dbDelta results - Orders: ' . print_r($result_orders, true));
        error_log('CAM: dbDelta results - Tiers: ' . print_r($result_tiers, true));
        
        // Verify tables were created
        $tables_created = array();
        $tables_to_check = array(
            $wpdb->prefix . 'cam_companies',
            $wpdb->prefix . 'cam_child_accounts',
            $wpdb->prefix . 'cam_company_orders',
            $wpdb->prefix . 'cam_discount_tiers'
        );
        
        foreach ($tables_to_check as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            $tables_created[$table] = $table_exists ? 'Created' : 'Failed';
            error_log("CAM: Table $table - " . ($table_exists ? 'Created' : 'Failed'));
        }
        
        // If any tables failed to create, try direct SQL
        foreach ($tables_created as $table => $status) {
            if ($status === 'Failed') {
                error_log("CAM: Attempting direct SQL creation for $table");
                
                // Remove prefix for comparison
                $table_name = str_replace($wpdb->prefix, '', $table);
                
                // Execute direct SQL based on table name
                switch ($table_name) {
                    case 'cam_companies':
                        $wpdb->query($sql_company);
                        break;
                    case 'cam_child_accounts':
                        $wpdb->query($sql_child);
                        break;
                    case 'cam_company_orders':
                        $wpdb->query($sql_orders);
                        break;
                    case 'cam_discount_tiers':
                        $wpdb->query($sql_tiers);
                        break;
                }
                
                // Verify again
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                error_log("CAM: Table $table after direct SQL - " . ($table_exists ? 'Created' : 'Still Failed'));
                
                if (!$table_exists) {
                    // Last resort - try a simpler CREATE TABLE statement
                    error_log("CAM: Attempting simplified SQL for $table");
                    
                    switch ($table_name) {
                        case 'cam_companies':
                            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_companies (
                                id bigint(20) NOT NULL AUTO_INCREMENT,
                                user_id bigint(20) NOT NULL,
                                company_name varchar(255) NOT NULL,
                                industry varchar(255),
                                company_info text,
                                registration_date datetime NOT NULL,
                                status varchar(20) NOT NULL,
                                tier_id bigint(20) DEFAULT NULL,
                                admin_status varchar(20) NOT NULL,
                                PRIMARY KEY (id)
                            ) $charset_collate");
                            break;
                        case 'cam_child_accounts':
                            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_child_accounts (
                                id bigint(20) NOT NULL AUTO_INCREMENT,
                                user_id bigint(20) NOT NULL,
                                company_id bigint(20) NOT NULL,
                                created_date datetime NOT NULL,
                                status varchar(20) NOT NULL,
                                PRIMARY KEY (id)
                            ) $charset_collate");
                            break;
                        case 'cam_company_orders':
                            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_company_orders (
                                id bigint(20) NOT NULL AUTO_INCREMENT,
                                order_id bigint(20) NOT NULL,
                                company_id bigint(20) NOT NULL,
                                user_id bigint(20) NOT NULL,
                                order_total decimal(10,2) NOT NULL,
                                order_date datetime NOT NULL,
                                PRIMARY KEY (id)
                            ) $charset_collate");
                            break;
                        case 'cam_discount_tiers':
                            $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_discount_tiers (
                                id bigint(20) NOT NULL AUTO_INCREMENT,
                                tier_name varchar(255) NOT NULL,
                                discount_percentage decimal(5,2) NOT NULL,
                                created_date datetime NOT NULL,
                                PRIMARY KEY (id)
                            ) $charset_collate");
                            break;
                    }
                    
                    // Final verification
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                    error_log("CAM: Table $table after simplified SQL - " . ($table_exists ? 'Created' : 'Still Failed'));
                }
            }
        }
        
        error_log('CAM: Table creation completed');
    }

    /**
     * Set default options
     */
    private static function create_options() {
        add_option('cam_require_approval', 'yes');
        add_option('cam_notification_email', get_option('admin_email'));
        add_option('cam_version', CAM_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Remove roles and capabilities
        CAM_Roles::remove_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall hook
     */
    public static function uninstall() {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            exit;
        }

        // Remove roles
        remove_role('company_admin');
        remove_role('company_child');

        // Remove capabilities from administrator
        $admin = get_role('administrator');
        $admin->remove_cap('manage_company_accounts');
        $admin->remove_cap('approve_company_registrations');
        $admin->remove_cap('view_all_company_reports');

        // Remove options
        delete_option('cam_require_approval');
        delete_option('cam_notification_email');
        delete_option('cam_version');

        // Optional: Remove database tables
        // Uncomment these lines if you want to remove tables on uninstall
        /*
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cam_companies");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cam_child_accounts");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cam_company_orders");
        */
    }

    /**
     * Create company registration page
     */
    public static function create_registration_page() {
        // Check if the page already exists
        $page_id = get_option('cam_registration_page_id');
        if ($page_id && get_post($page_id)) {
            return;
        }
        
        // Create the page
        $page_id = wp_insert_post(array(
            'post_title'     => __('Company Registration', 'company-accounts-manager'),
            'post_content'   => '[company_registration_form]',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'comment_status' => 'closed'
        ));
        
        // Save the page ID
        if ($page_id) {
            update_option('cam_registration_page_id', $page_id);
        }
    }
} 