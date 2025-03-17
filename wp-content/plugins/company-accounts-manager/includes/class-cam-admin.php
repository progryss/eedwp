<?php
/**
 * Handles site administrator functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Admin {
    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_cam_approve_company', array(__CLASS__, 'ajax_approve_company'));
        add_action('wp_ajax_cam_reject_company', array(__CLASS__, 'ajax_reject_company'));
    }

    /**
     * Add admin menu items
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('Company Accounts', 'company-accounts-manager'),
            __('Company Accounts', 'company-accounts-manager'),
            'manage_options',
            'cam-dashboard',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'cam-dashboard',
            __('Pending Companies', 'company-accounts-manager'),
            __('Pending Companies', 'company-accounts-manager'),
            'manage_options',
            'cam-pending-companies',
            array(__CLASS__, 'render_pending_companies_page')
        );

        add_submenu_page(
            'cam-dashboard',
            __('All Companies', 'company-accounts-manager'),
            __('All Companies', 'company-accounts-manager'),
            'manage_options',
            'cam-all-companies',
            array(__CLASS__, 'render_all_companies_page')
        );

        add_submenu_page(
            'cam-dashboard',
            __('Manage Tiers', 'company-accounts-manager'),
            __('Manage Tiers', 'company-accounts-manager'),
            'manage_options',
            'cam-manage-tiers',
            array(__CLASS__, 'render_manage_tiers_page')
        );

        add_submenu_page(
            'cam-dashboard',
            __('Settings', 'company-accounts-manager'),
            __('Settings', 'company-accounts-manager'),
            'manage_options',
            'cam-settings',
            array(__CLASS__, 'render_settings_page')
        );
        
        add_submenu_page(
            'cam-dashboard',
            __('Clear Dashboard Data', 'company-accounts-manager'),
            __('Clear Dashboard Data', 'company-accounts-manager'),
            'manage_options',
            'cam-clear-data',
            array(__CLASS__, 'render_clear_data_page')
        );
        
        // Hidden page for company details
        add_submenu_page(
            null, // No parent
            __('Company Details', 'company-accounts-manager'),
            __('Company Details', 'company-accounts-manager'),
            'manage_options',
            'cam-company-details',
            array(__CLASS__, 'render_company_details_page')
        );
    }

    /**
     * Register plugin settings
     */
    public static function register_settings() {
        register_setting('cam_settings', 'cam_require_approval');
        register_setting('cam_settings', 'cam_notification_email');
        
        add_settings_section(
            'cam_general_settings',
            __('General Settings', 'company-accounts-manager'),
            array(__CLASS__, 'render_general_settings_section'),
            'cam_settings'
        );

        add_settings_field(
            'cam_require_approval',
            __('Require Approval', 'company-accounts-manager'),
            array(__CLASS__, 'render_require_approval_field'),
            'cam_settings',
            'cam_general_settings'
        );

        add_settings_field(
            'cam_notification_email',
            __('Notification Email', 'company-accounts-manager'),
            array(__CLASS__, 'render_notification_email_field'),
            'cam_settings',
            'cam_general_settings'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'cam-') !== false) {
            wp_enqueue_style(
                'cam-admin-style',
                CAM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CAM_VERSION
            );

            wp_enqueue_script(
                'cam-admin-script',
                CAM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'jquery-ui-datepicker'),
                CAM_VERSION,
                true
            );

            wp_localize_script('cam-admin-script', 'camAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cam-admin-nonce')
            ));
        }
    }

    /**
     * Render dashboard page
     */
    public static function render_dashboard_page() {
        // We don't need to get statistics here anymore
        // as we're getting them directly in the template
        // to ensure fresh data
        
        // Include dashboard template
        include CAM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render pending companies page
     */
    public static function render_pending_companies_page() {
        $pending_companies = self::get_pending_companies();
        include CAM_PLUGIN_DIR . 'templates/admin/pending-companies.php';
    }

    /**
     * Render all companies page
     */
    public static function render_all_companies_page() {
        // Handle manual population of orders
        if (isset($_POST['action']) && $_POST['action'] === 'cam_admin_populate_orders' && 
            isset($_POST['cam_admin_populate_nonce']) && wp_verify_nonce($_POST['cam_admin_populate_nonce'], 'cam_admin_populate_orders')) {
            
            // Force population of orders table
            if (class_exists('CAM_Order_Manager')) {
                // Remove the last run time to force it to run
                delete_option('cam_populate_orders_last_run');
                
                // Run the population
                CAM_Order_Manager::populate_company_orders_table();
                
                // Show success message
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                        __('Order statistics refreshed successfully.', 'company-accounts-manager') . 
                        '</p></div>';
                });
            }
        }
        
        $companies = self::get_all_companies();
        $tiers = CAM_Tiers::get_all_tiers();
        include CAM_PLUGIN_DIR . 'templates/admin/all-companies.php';
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Company Accounts Settings', 'company-accounts-manager'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cam_settings');
                do_settings_sections('cam_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings section
     */
    public static function render_general_settings_section() {
        echo '<p>' . __('Configure general settings for the Company Accounts Manager plugin.', 'company-accounts-manager') . '</p>';
    }

    /**
     * Render require approval field
     */
    public static function render_require_approval_field() {
        $value = get_option('cam_require_approval', 'yes');
        ?>
        <select name="cam_require_approval">
            <option value="yes" <?php selected($value, 'yes'); ?>><?php _e('Yes', 'company-accounts-manager'); ?></option>
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('No', 'company-accounts-manager'); ?></option>
        </select>
        <p class="description"><?php _e('Require admin approval for new company registrations.', 'company-accounts-manager'); ?></p>
        <?php
    }

    /**
     * Render notification email field
     */
    public static function render_notification_email_field() {
        $value = get_option('cam_notification_email', get_option('admin_email'));
        ?>
        <input type="email" name="cam_notification_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php _e('Email address for company registration notifications.', 'company-accounts-manager'); ?></p>
        <?php
    }

    /**
     * AJAX handler for approving company
     */
    public static function ajax_approve_company() {
        check_ajax_referer('cam-admin-nonce', 'nonce');

        if (!current_user_can('approve_company_registrations')) {
            wp_send_json_error(__('Permission denied.', 'company-accounts-manager'));
        }

        $company_id = intval($_POST['company_id']);
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'cam_companies',
            array('status' => 'active'),
            array('id' => $company_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to approve company.', 'company-accounts-manager'));
        }

        // Send approval notification
        $company = self::get_company_details($company_id);
        if ($company) {
            $admin_user = get_user_by('id', $company->user_id);
            if ($admin_user) {
                self::send_approval_notification($admin_user, $company);
            }
        }

        wp_send_json_success(array(
            'message' => __('Company approved successfully.', 'company-accounts-manager')
        ));
    }

    /**
     * AJAX handler for rejecting company
     */
    public static function ajax_reject_company() {
        check_ajax_referer('cam-admin-nonce', 'nonce');

        if (!current_user_can('approve_company_registrations')) {
            wp_send_json_error(__('Permission denied.', 'company-accounts-manager'));
        }

        $company_id = intval($_POST['company_id']);
        $reason = sanitize_textarea_field($_POST['reason']);

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'cam_companies',
            array('status' => 'rejected'),
            array('id' => $company_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(__('Failed to reject company.', 'company-accounts-manager'));
        }

        // Send rejection notification
        $company = self::get_company_details($company_id);
        if ($company) {
            $admin_user = get_user_by('id', $company->user_id);
            if ($admin_user) {
                self::send_rejection_notification($admin_user, $company, $reason);
            }
        }

        wp_send_json_success(array(
            'message' => __('Company rejected successfully.', 'company-accounts-manager')
        ));
    }

    /**
     * Get company details
     */
    private static function get_company_details($company_id) {
        global $wpdb;
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cam_companies WHERE id = %d",
            $company_id
        ));

        if (!$company) {
            wp_die(__('Company not found.', 'company-accounts-manager'));
        }

        // Get admin user
        $admin_user = get_user_by('id', $company->user_id);

        if (!$admin_user) {
            wp_die(__('Company admin not found.', 'company-accounts-manager'));
        }

        return $company;
    }

    /**
     * Get total companies count
     */
    private static function get_total_companies() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_companies WHERE status = 'active'"
        );
    }

    /**
     * Get total child accounts count
     */
    private static function get_total_child_accounts() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE status = 'active'"
        );
    }

    /**
     * Get total orders count
     */
    private static function get_total_orders() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders"
        );
    }

    /**
     * Get total revenue
     */
    private static function get_total_revenue() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT SUM(order_total) FROM {$wpdb->prefix}cam_company_orders"
        );
    }

    /**
     * Get pending companies
     */
    private static function get_pending_companies() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT c.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}cam_companies c 
            JOIN {$wpdb->users} u ON c.user_id = u.ID 
            WHERE c.status = 'pending' 
            ORDER BY c.registration_date DESC"
        );
    }

    /**
     * Get all companies with optional filtering and sorting
     */
    private static function get_all_companies() {
        global $wpdb;
        
        // Get search, filter, and sort parameters
        $search = isset($_GET['cam_search']) ? sanitize_text_field($_GET['cam_search']) : '';
        $tier_filter = isset($_GET['cam_tier']) ? intval($_GET['cam_tier']) : 0;
        $sort_by = isset($_GET['cam_sort']) ? sanitize_text_field($_GET['cam_sort']) : 'date_desc';
        
        // Base query
        $query = "SELECT c.*, u.display_name, u.user_email,
            (SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE company_id = c.id) as child_accounts,
            COALESCE((SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders WHERE company_id = c.id), 0) as total_orders,
            COALESCE((SELECT SUM(order_total) FROM {$wpdb->prefix}cam_company_orders WHERE company_id = c.id), 0) as total_spent
        FROM {$wpdb->prefix}cam_companies c 
        JOIN {$wpdb->users} u ON c.user_id = u.ID";
        
        // Add search condition
        if (!empty($search)) {
            $query .= $wpdb->prepare(
                " WHERE (c.company_name LIKE %s OR u.user_email LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Add tier filter
        if (!empty($tier_filter)) {
            $where_or_and = !empty($search) ? ' AND' : ' WHERE';
            
            if ($tier_filter == -1) {
                // Filter for unassigned tiers (NULL or 0)
                $query .= "{$where_or_and} (c.tier_id IS NULL OR c.tier_id = 0)";
            } else {
                // Filter for specific tier
                $query .= $wpdb->prepare(
                    "{$where_or_and} c.tier_id = %d",
                    $tier_filter
                );
            }
        }
        
        // Add sorting
        switch ($sort_by) {
            case 'spend_asc':
                $query .= " ORDER BY total_spent ASC";
                break;
            case 'spend_desc':
                $query .= " ORDER BY total_spent DESC";
                break;
            case 'date_asc':
                $query .= " ORDER BY c.registration_date ASC";
                break;
            case 'date_desc':
            default:
                $query .= " ORDER BY c.registration_date DESC";
                break;
        }
        
        // Get the companies
        $companies = $wpdb->get_results($query);
        
        // If we have no orders data, let's force a one-time population of the orders table
        $has_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders");
        
        if ($companies && !$has_orders) {
            // Call the populate method directly
            if (class_exists('CAM_Order_Manager')) {
                CAM_Order_Manager::populate_company_orders_table();
                
                // Refresh the data after population
                $companies = $wpdb->get_results($query);
            }
        }
        
        return $companies;
    }

    /**
     * Send approval notification
     */
    private static function send_approval_notification($user, $company) {
        $subject = sprintf(
            __('[%s] Company Registration Approved: %s', 'company-accounts-manager'),
            get_bloginfo('name'),
            $company->company_name
        );
        
        $message = sprintf(
            __("Congratulations! Your company registration has been approved.\n\nCompany: %s\n\nYou can now log in and start managing your company account at: %s", 'company-accounts-manager'),
            $company->company_name,
            wc_get_page_permalink('myaccount')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Send rejection notification
     */
    private static function send_rejection_notification($user, $company, $reason) {
        $subject = sprintf(
            __('[%s] Company Registration Rejected: %s', 'company-accounts-manager'),
            get_bloginfo('name'),
            $company->company_name
        );
        
        $message = sprintf(
            __("We regret to inform you that your company registration has been rejected.\n\nCompany: %s\n\nReason: %s\n\nIf you believe this is an error, please contact us.", 'company-accounts-manager'),
            $company->company_name,
            $reason
        );
        
        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Render company details page
     */
    public static function render_company_details_page() {
        // Process child account status toggle (suspend/activate)
        if (isset($_POST['cam_toggle_child_status']) && 
            isset($_POST['cam_toggle_child_status_nonce']) && wp_verify_nonce($_POST['cam_toggle_child_status_nonce'], 'cam_toggle_child_status')) {
            
            $user_id = intval($_POST['user_id']);
            $action = sanitize_text_field($_POST['action_type']);
            
            if ($user_id > 0) {
                global $wpdb;
                
                // Update the status
                $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                
                $wpdb->update(
                    $wpdb->prefix . 'cam_child_accounts',
                    array('status' => $new_status),
                    array('user_id' => $user_id),
                    array('%s'),
                    array('%d')
                );
                
                // Show success message
                $message = ($action === 'suspend') ? 
                    __('Child account has been suspended.', 'company-accounts-manager') : 
                    __('Child account has been activated.', 'company-accounts-manager');
                
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            }
        }
        
        // Process company admin status toggle (suspend/activate)
        if (isset($_POST['cam_toggle_admin_status']) && 
            isset($_POST['cam_toggle_admin_status_nonce']) && wp_verify_nonce($_POST['cam_toggle_admin_status_nonce'], 'cam_toggle_admin_status')) {
            
            $user_id = intval($_POST['user_id']);
            $action = sanitize_text_field($_POST['action_type']);
            
            if ($user_id > 0) {
                global $wpdb;
                
                // Get company ID for this admin
                $company_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                ));
                
                if ($company_id) {
                    // Update the admin status only, not the company status
                    $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                    
                    $wpdb->update(
                        $wpdb->prefix . 'cam_companies',
                        array('admin_status' => $new_status),
                        array('id' => $company_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    // Note: This action only affects the company admin, not child accounts or company status
                    
                    // Show success message
                    $message = ($action === 'suspend') ? 
                        __('Company admin has been suspended. The admin will not be able to log in until reactivated. The company status and child accounts are not affected.', 'company-accounts-manager') : 
                        __('Company admin has been activated. The admin can now log in.', 'company-accounts-manager');
                    
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                }
            }
        }
        
        // Process entire company status toggle (suspend/activate)
        if (isset($_POST['cam_toggle_entire_company_status']) && 
            isset($_POST['cam_toggle_company_status_nonce']) && wp_verify_nonce($_POST['cam_toggle_company_status_nonce'], 'cam_toggle_company_status')) {
            
            $company_id = intval($_POST['company_id']);
            $action = sanitize_text_field($_POST['action_type']);
            
            if ($company_id > 0) {
                global $wpdb;
                
                // Update the company status
                $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                
                $wpdb->update(
                    $wpdb->prefix . 'cam_companies',
                    array('status' => $new_status),
                    array('id' => $company_id),
                    array('%s'),
                    array('%d')
                );
                
                // If suspending, also update all child accounts to suspended
                if ($action === 'suspend') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}cam_child_accounts 
                        SET status = 'suspended' 
                        WHERE company_id = %d",
                        $company_id
                    ));
                }
                // If activating, also update all child accounts to active
                else if ($action === 'activate') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}cam_child_accounts 
                        SET status = 'active' 
                        WHERE company_id = %d",
                        $company_id
                    ));
                }
                
                // Show success message
                $message = ($action === 'suspend') ? 
                    __('The entire company has been suspended. The company admin and all child accounts will not be able to log in until reactivated.', 'company-accounts-manager') : 
                    __('The entire company has been activated.', 'company-accounts-manager');
                
                echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
            }
        }
        
        // Check if company ID is provided
        if (!isset($_GET['company_id'])) {
            echo '<div class="notice notice-error"><p>' . __('Company ID is required.', 'company-accounts-manager') . '</p></div>';
            return;
        }
        
        $company_id = intval($_GET['company_id']);
        $company = self::get_company_details($company_id);
        
        if (!$company) {
            echo '<div class="notice notice-error"><p>' . __('Company not found.', 'company-accounts-manager') . '</p></div>';
            return;
        }
        
        include CAM_PLUGIN_DIR . 'templates/admin/company-details.php';
    }

    /**
     * Render the manage tiers page
     */
    public static function render_manage_tiers_page() {
        include(CAM_PLUGIN_DIR . 'templates/admin/manage-tiers.php');
    }

    /**
     * Render clear data page
     */
    public static function render_clear_data_page() {
        include CAM_PLUGIN_DIR . 'clear-dashboard-data.php';
    }
} 