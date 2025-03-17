<?php
/**
 * Handles company admin registration and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Company_Admin {
    /**
     * Check if a company is approved before login
     */
    public static function check_company_approval($user, $username, $password) {
        if (is_wp_error($user) || !$user instanceof WP_User) {
            return $user;
        }
        
        if (in_array('company_admin', (array) $user->roles)) {
            $company_id = CAM_Roles::get_user_company_id($user->ID);
            $company = self::get_company_details($company_id);
            if ($company && $company->status === 'pending') {
                return new WP_Error('pending_approval', __('Your company registration is pending approval.', 'company-accounts-manager'));
            }
            if ($company && $company->status === 'suspended') {
                return new WP_Error('account_suspended', __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager'));
            }
            if ($company && $company->status === 'rejected') {
                return new WP_Error('account_rejected', __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager'));
            }
            if ($company && $company->admin_status === 'suspended') {
                return new WP_Error('admin_suspended', __('Your admin account has been suspended. Please contact the site administrator.', 'company-accounts-manager'));
            }
        }
        return $user;
    }

    /**
     * Initialize company admin functionality
     */
    public static function init() {
        // Add authentication filter to check company approval
        add_filter('authenticate', array(__CLASS__, 'check_company_approval'), 30, 3);
        
        // Add filter to allow company admins to view child account orders
        add_filter('map_meta_cap', array(__CLASS__, 'allow_company_admin_view_orders'), 10, 4);
        
        // Add filter to handle user capabilities for viewing orders
        add_filter('user_has_cap', array(__CLASS__, 'filter_company_admin_caps'), 10, 3);
        
        // Override WooCommerce order check
        add_filter('woocommerce_order_is_visible_to_user', array(__CLASS__, 'allow_company_admin_view_wc_order'), 10, 2);
        
        // Add billing and shipping addresses to order view for Company Admins
        add_action('woocommerce_view_order', array(__CLASS__, 'add_addresses_to_order_view'), 20);
        
        // Add filter to ensure addresses are visible in admin area
        add_filter('woocommerce_admin_order_data_after_billing_address', array(__CLASS__, 'ensure_admin_address_visibility'), 10, 1);
        add_filter('woocommerce_admin_order_data_after_shipping_address', array(__CLASS__, 'ensure_admin_address_visibility'), 10, 1);
        
        // Add filter to ensure all order details are visible
        add_filter('woocommerce_order_item_get_formatted_meta_data', array(__CLASS__, 'ensure_order_details_visibility'), 10, 2);
        
        // Add registration fields
        remove_action('woocommerce_register_form', array(__CLASS__, 'add_registration_fields'));
        add_action('woocommerce_register_form', array(__CLASS__, 'add_registration_fields'), 10);
        add_action('woocommerce_register_form_start', array(__CLASS__, 'add_registration_fields_debug'), 10);
        
        // Process registration
        add_action('woocommerce_created_customer', array(__CLASS__, 'process_registration'));
        
        // Add company dashboard to My Account
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_company_dashboard'), 20);
        add_action('woocommerce_account_company-dashboard_endpoint', array(__CLASS__, 'company_dashboard_content'));
        add_action('woocommerce_account_child-accounts_endpoint', array(__CLASS__, 'child_accounts_content'));
        
        // Register endpoints
        add_action('init', array(__CLASS__, 'register_endpoints'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_cam_create_child_account', array(__CLASS__, 'ajax_create_child_account'));
        add_action('wp_ajax_cam_get_company_details', array(__CLASS__, 'ajax_get_company_details'));
        add_action('wp_ajax_cam_update_company_details', array(__CLASS__, 'ajax_update_company_details'));
        
        // Register shortcodes - ensure this happens early
        add_action('init', array(__CLASS__, 'register_shortcodes'), 5);
        
        // Fix missing child accounts
        add_action('init', array(__CLASS__, 'fix_missing_child_accounts'), 5);
        
        // Fix child account roles (remove subscriber role)
        add_action('init', array(__CLASS__, 'fix_child_account_roles'), 6);
        
        // Check if company is suspended on every page load
        add_action('init', array(__CLASS__, 'check_company_suspension'), 1);
        
        // Add login error message for suspended companies
        add_action('login_message', array(__CLASS__, 'suspended_company_login_message'));
        
        // Add a JavaScript redirect for suspended companies
        add_action('wp_head', array(__CLASS__, 'add_suspended_company_redirect_script'), 1);
        add_action('admin_head', array(__CLASS__, 'add_suspended_company_redirect_script'), 1);
        
        // Check for suspended companies on AJAX requests
        add_action('wp_ajax_nopriv_*', array(__CLASS__, 'block_suspended_company_ajax'), 1);
        add_action('wp_ajax_*', array(__CLASS__, 'block_suspended_company_ajax'), 1);
        
        // Check for suspended companies on REST API requests
        add_filter('rest_authentication_errors', array(__CLASS__, 'block_suspended_company_rest_api'), 10);
    }

    /**
     * Register custom endpoints for My Account page
     */
    public static function register_endpoints() {
        add_rewrite_endpoint('company-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('child-accounts', EP_ROOT | EP_PAGES);
        
        // Force flush rewrite rules
        update_option('cam_flush_rewrite_rules', 'yes');
        
        // Flush rewrite rules if needed
        if (get_option('cam_flush_rewrite_rules', 'yes') === 'yes') {
            flush_rewrite_rules();
            update_option('cam_flush_rewrite_rules', 'no');
        }
    }

    /**
     * Add company registration fields
     */
    public static function add_registration_fields() {
        // Add debug comment
        echo '<!-- CAM: Adding registration fields -->';
        
        // Add fields directly without relying on JavaScript for initial display
        ?>
        <p class="form-row form-row-wide">
            <label for="cam_register_as_company">
                <input type="checkbox" name="cam_register_as_company" id="cam_register_as_company" value="1" />
                <?php _e('Register as Company Admin', 'company-accounts-manager'); ?>
            </label>
        </p>
        
        <div id="cam_company_fields">
            <h3><?php _e('Company Information', 'company-accounts-manager'); ?></h3>
            
            <p class="form-row form-row-wide">
                <label for="cam_company_name"><?php _e('Company Name', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                <input type="text" class="input-text" name="cam_company_name" id="cam_company_name" required />
            </p>
            
            <p class="form-row form-row-wide">
                <label for="cam_industry"><?php _e('Industry', 'company-accounts-manager'); ?></label>
                <input type="text" class="input-text" name="cam_industry" id="cam_industry" />
            </p>
            
            <p class="form-row form-row-wide">
                <label for="cam_company_info"><?php _e('Additional Information', 'company-accounts-manager'); ?></label>
                <textarea name="cam_company_info" id="cam_company_info" rows="4"></textarea>
            </p>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initially hide the company fields
                $('#cam_company_fields').hide();
                
                // Toggle company fields when checkbox is clicked
                $('#cam_register_as_company').change(function() {
                    $('#cam_company_fields').toggle(this.checked);
                });
                
                // Debug message
                console.log('CAM: Registration form JavaScript loaded');
            });
        </script>
        <?php
    }

    /**
     * Debug function to verify hook is being called
     */
    public static function add_registration_fields_debug() {
        echo '<!-- CAM Debug: Registration fields hook called -->';
        
        // Add a visible message for debugging
        echo '<div style="background-color: #ffeb3b; padding: 10px; margin-bottom: 10px;">
            <strong>Debug:</strong> Company Accounts Manager plugin is active. 
            If you don\'t see company registration fields below, please contact support.
        </div>';
    }

    /**
     * Process company admin registration
     */
    public static function process_registration($user_id) {
        // Check if registering as company admin
        if (!isset($_POST['cam_register_as_company']) || empty($_POST['cam_company_name'])) {
            return;
        }
        
        // Add user meta
        update_user_meta($user_id, 'cam_is_company_admin', 1);
        
        // Assign the company_admin role
        $user = new WP_User($user_id);
        $user->set_role('company_admin'); // Use set_role instead of add_role to replace the default subscriber role
        
        // Get company details
        $company_name = sanitize_text_field($_POST['cam_company_name']);
        $company_info = isset($_POST['cam_company_info']) ? sanitize_textarea_field($_POST['cam_company_info']) : '';
        $industry = isset($_POST['cam_industry']) ? sanitize_text_field($_POST['cam_industry']) : '';
        
        // Add company to database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cam_companies',
            array(
                'user_id' => $user_id,
                'company_name' => $company_name,
                'industry' => $industry,
                'company_info' => $company_info,
                'status' => 'pending',
                'registration_date' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Send notification to admin
        self::send_admin_notification($user_id, $company_name);
    }

    /**
     * Send notification to site admin about new company registration
     */
    private static function send_admin_notification($user_id, $company_name) {
        $admin_email = get_option('cam_notification_email', get_option('admin_email'));
        $user = get_userdata($user_id);
        
        $subject = sprintf(
            __('[%s] New Company Registration: %s', 'company-accounts-manager'),
            get_bloginfo('name'),
            $company_name
        );
        
        $message = sprintf(
            __("A new company has registered and requires approval:\n\nCompany: %s\nAdmin: %s\nEmail: %s\n\nApprove or reject this registration here: %s", 'company-accounts-manager'),
            $company_name,
            $user->display_name,
            $user->user_email,
            admin_url('admin.php?page=cam-pending-companies')
        );
        
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Add company dashboard to My Account page
     */
    public static function add_company_dashboard($menu_items) {
        if (CAM_Roles::is_company_admin()) {
            // Add company dashboard after the Dashboard item
            $new_items = array();
            foreach ($menu_items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'dashboard') {
                    $new_items['company-dashboard'] = __('Company Dashboard', 'company-accounts-manager');
                    $new_items['child-accounts'] = __('Child Accounts', 'company-accounts-manager');
                }
            }
            return $new_items;
        }
        return $menu_items;
    }
    
    /**
     * Company dashboard content
     */
    public static function company_dashboard_content() {
        // Process manual child account creation
        if (isset($_POST['action']) && $_POST['action'] === 'cam_manual_create_child' && isset($_POST['cam_manual_nonce']) && wp_verify_nonce($_POST['cam_manual_nonce'], 'cam_manual_create_child')) {
            $email = sanitize_email($_POST['manual_email']);
            $first_name = sanitize_text_field($_POST['manual_first_name']);
            $last_name = sanitize_text_field($_POST['manual_last_name']);
            
            if (empty($email) || !is_email($email)) {
                echo '<div class="notice notice-error"><p>' . __('Invalid email address.', 'company-accounts-manager') . '</p></div>';
            } else {
                // Generate random password
                $password = wp_generate_password();
                
                // Create user
                $user_id = wp_create_user($email, $password, $email);
                
                if (is_wp_error($user_id)) {
                    echo '<div class="notice notice-error"><p>' . $user_id->get_error_message() . '</p></div>';
                } else {
                    // Update user meta
                    update_user_meta($user_id, 'first_name', $first_name);
                    update_user_meta($user_id, 'last_name', $last_name);
                    
                    // Set display name if first and last name are provided
                    if (!empty($first_name) && !empty($last_name)) {
                        wp_update_user(array(
                            'ID' => $user_id,
                            'display_name' => $first_name . ' ' . $last_name
                        ));
                    } elseif (!empty($first_name)) {
                        wp_update_user(array(
                            'ID' => $user_id,
                            'display_name' => $first_name
                        ));
                    } elseif (!empty($last_name)) {
                        wp_update_user(array(
                            'ID' => $user_id,
                            'display_name' => $last_name
                        ));
                    }
                    
                    // Assign child account role
                    $user = new WP_User($user_id);
                    $user->set_role('company_child');
                    
                    // Link to company
                    $company_id = CAM_Roles::get_user_company_id();
                    global $wpdb;
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'cam_child_accounts',
                        array(
                            'user_id' => $user_id,
                            'company_id' => $company_id,
                            'created_date' => current_time('mysql'),
                            'status' => 'active'
                        ),
                        array('%d', '%d', '%s', '%s')
                    );
                    
                    if ($result) {
                        // Send welcome email
                        self::send_child_account_welcome_email($user_id, $password);
                        
                        echo '<div class="notice notice-success"><p>' . __('Child account created successfully.', 'company-accounts-manager') . '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . __('Error linking child account to company.', 'company-accounts-manager') . '</p></div>';
                    }
                }
            }
        }
        
        // Process child account status toggle (suspend/activate)
        if (isset($_POST['action']) && $_POST['action'] === 'cam_toggle_child_account_status' && 
            isset($_POST['cam_toggle_child_nonce']) && wp_verify_nonce($_POST['cam_toggle_child_nonce'], 'cam_toggle_child_account_status')) {
            
            $child_account_id = intval($_POST['child_account_id']);
            $action = sanitize_text_field($_POST['child_account_action']);
            
            if ($child_account_id > 0) {
                // Verify this child account belongs to the current company admin
                $company_id = CAM_Roles::get_user_company_id();
                global $wpdb;
                
                $child_company_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $child_account_id
                ));
                
                if ($child_company_id == $company_id) {
                    // Update the status
                    $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                    
                    $wpdb->update(
                        $wpdb->prefix . 'cam_child_accounts',
                        array('status' => $new_status),
                        array('user_id' => $child_account_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    // Show success message
                    $message = ($action === 'suspend') ? 
                        __('Child account has been suspended.', 'company-accounts-manager') : 
                        __('Child account has been activated.', 'company-accounts-manager');
                    
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage this account.', 'company-accounts-manager') . '</p></div>';
                }
            }
        }
        
        // Handle manual population of company orders table
        if (isset($_POST['action']) && $_POST['action'] === 'cam_populate_orders' && 
            isset($_POST['cam_populate_nonce']) && wp_verify_nonce($_POST['cam_populate_nonce'], 'cam_populate_orders') &&
            current_user_can('administrator')) {
            
            // Get all users associated with this company
            $company_user_ids = array();
            
            // Get company admin
            $company = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            if ($company) {
                $company_user_ids[] = $company->user_id;
                
                // Get child accounts
                $child_accounts = $wpdb->get_results($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}cam_child_accounts WHERE company_id = %d",
                    $company_id
                ));
                
                foreach ($child_accounts as $child) {
                    $company_user_ids[] = $child->user_id;
                }
            }
            
            if (!empty($company_user_ids)) {
                // Format user IDs for SQL query
                $user_ids_string = implode(',', array_map('intval', $company_user_ids));
                
                // Get order IDs for these users
                $order_ids = $wpdb->get_results("
                    SELECT posts.ID, posts.post_date, pm.meta_value as user_id
                    FROM {$wpdb->posts} AS posts
                    INNER JOIN {$wpdb->postmeta} AS pm ON posts.ID = pm.post_id
                    WHERE posts.post_type = 'shop_order'
                    AND posts.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
                    AND pm.meta_key = '_customer_user'
                    AND pm.meta_value IN ({$user_ids_string})
                ");
                
                $orders_added = 0;
                
                foreach ($order_ids as $order_data) {
                    // Check if this order is already in the company orders table
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}cam_company_orders WHERE order_id = %d",
                        $order_data->ID
                    ));
                    
                    if (!$exists) {
                        // Add to company orders table
                        $order = wc_get_order($order_data->ID);
                        if ($order) {
                            $wpdb->insert(
                                $wpdb->prefix . 'cam_company_orders',
                                array(
                                    'order_id' => $order_data->ID,
                                    'company_id' => $company_id,
                                    'user_id' => $order_data->user_id,
                                    'order_total' => $order->get_total(),
                                    'order_date' => $order_data->post_date
                                ),
                                array('%d', '%d', '%d', '%f', '%s')
                            );
                            $orders_added++;
                        }
                    }
                }
                
                echo '<div class="notice notice-success"><p>' . sprintf(__('Orders processed: %d. New orders added to statistics: %d.', 'company-accounts-manager'), count($order_ids), $orders_added) . '</p></div>';
            }
        }
        
        $company_id = CAM_Roles::get_user_company_id();
        if (!$company_id) {
            return;
        }
        
        // Simply include the template - let the template handle the statistics calculation
        include(CAM_PLUGIN_DIR . 'templates/company-dashboard.php');
    }

    /**
     * Enqueue necessary scripts
     */
    public static function enqueue_scripts() {
        if (CAM_Roles::is_company_admin()) {
            wp_enqueue_style(
                'cam-admin-style',
                CAM_PLUGIN_URL . 'assets/css/company-admin.css',
                array(),
                CAM_VERSION
            );

            wp_enqueue_script(
                'cam-admin-script',
                CAM_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                CAM_VERSION,
                true
            );

            wp_localize_script('cam-admin-script', 'camCompanyAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cam-ajax-nonce')
            ));
        }
    }

    /**
     * AJAX handler for creating child accounts
     */
    public static function ajax_create_child_account() {
        check_ajax_referer('cam-ajax-nonce', 'security');

        if (!CAM_Roles::is_company_admin()) {
            wp_send_json_error(__('Permission denied.', 'company-accounts-manager'));
        }

        $company_id = CAM_Roles::get_user_company_id();
        if (!$company_id) {
            wp_send_json_error(__('Company not found.', 'company-accounts-manager'));
        }

        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(__('Invalid email address.', 'company-accounts-manager'));
        }

        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Company Admin: Creating child account for email: ' . $email);
            error_log('CAM Company Admin: Company ID: ' . $company_id);
        }

        // Generate random password
        $password = wp_generate_password();

        // Create user
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Error creating user: ' . $user_id->get_error_message());
            }
            wp_send_json_error($user_id->get_error_message());
        }

        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        
        // Set display name if first and last name are provided
        if (!empty($first_name) && !empty($last_name)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $first_name . ' ' . $last_name
            ));
        } elseif (!empty($first_name)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $first_name
            ));
        } elseif (!empty($last_name)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $last_name
            ));
        }

        // Assign child account role - first remove default role
        $user = new WP_User($user_id);
        $user->remove_role('subscriber'); // Remove the default subscriber role
        $user->add_role('company_child');

        // Link to company
        global $wpdb;
        $insert_result = $wpdb->insert(
            $wpdb->prefix . 'cam_child_accounts',
            array(
                'user_id' => $user_id,
                'company_id' => $company_id,
                'created_date' => current_time('mysql'),
                'status' => 'active'
            ),
            array('%d', '%d', '%s', '%s')
        );

        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Company Admin: User created - ID: ' . $user_id);
            error_log('CAM Company Admin: Child account role added');
            error_log('CAM Company Admin: Child account record created - Result: ' . ($insert_result ? 'Success (ID: ' . $wpdb->insert_id . ')' : 'Failed'));
            error_log('CAM Company Admin: SQL Query: ' . $wpdb->last_query);
            
            // Verify the record was created correctly
            $check_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($check_record) {
                error_log('CAM Company Admin: Child account record verified - ID: ' . $check_record->id);
                error_log('CAM Company Admin: Child account company ID: ' . $check_record->company_id);
                error_log('CAM Company Admin: Child account status: ' . ($check_record->status ?? 'NULL'));
            } else {
                error_log('CAM Company Admin: Failed to verify child account record');
            }
        }

        // Send welcome email
        self::send_child_account_welcome_email($user_id, $password);

        wp_send_json_success(array(
            'message' => __('Child account created successfully.', 'company-accounts-manager'),
            'user_id' => $user_id
        ));
    }

    /**
     * Send welcome email to new child account
     */
    private static function send_child_account_welcome_email($user_id, $password) {
        $user = get_userdata($user_id);
        $company = self::get_company_details(CAM_Roles::get_user_company_id());
        
        $subject = sprintf(
            __('Welcome to %s - Your Child Account', 'company-accounts-manager'),
            $company->company_name
        );
        
        $message = sprintf(
            __("Welcome to %s!\n\nYour account has been created with the following details:\n\nUsername: %s\nPassword: %s\n\nYou can log in at: %s\n\nPlease change your password after first login.", 'company-accounts-manager'),
            $company->company_name,
            $user->user_email,
            $password,
            wp_login_url()
        );
        
        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Get company details
     */
    public static function get_company_details($company_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cam_companies WHERE id = %d",
            $company_id
        ));
    }

    /**
     * Register shortcodes
     */
    public static function register_shortcodes() {
        add_shortcode('company_registration_form', array(__CLASS__, 'registration_form_shortcode'));
    }
    
    /**
     * Registration form shortcode
     */
    public static function registration_form_shortcode($atts) {
        // If user is logged in, don't show the form
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'company-accounts-manager') . '</p>';
        }
        
        // Process form submission
        $error = '';
        $success = '';
        
        if (isset($_POST['cam_register']) && isset($_POST['cam_register_nonce']) && wp_verify_nonce($_POST['cam_register_nonce'], 'cam_register')) {
            $username = sanitize_user($_POST['cam_username']);
            $email = sanitize_email($_POST['cam_email']);
            $password = $_POST['cam_password'];
            $company_name = sanitize_text_field($_POST['cam_company_name']);
            $industry = isset($_POST['cam_industry']) ? sanitize_text_field($_POST['cam_industry']) : '';
            $company_info = isset($_POST['cam_company_info']) ? sanitize_textarea_field($_POST['cam_company_info']) : '';
            
            // Validate fields
            if (empty($username) || empty($email) || empty($password) || empty($company_name)) {
                $error = __('Please fill in all required fields.', 'company-accounts-manager');
            } elseif (!is_email($email)) {
                $error = __('Please enter a valid email address.', 'company-accounts-manager');
            } elseif (username_exists($username)) {
                $error = __('This username is already registered.', 'company-accounts-manager');
            } elseif (email_exists($email)) {
                $error = __('This email is already registered.', 'company-accounts-manager');
            } else {
                // Create the user
                $user_id = wp_create_user($username, $password, $email);
                
                if (is_wp_error($user_id)) {
                    $error = $user_id->get_error_message();
                } else {
                    // Add user meta
                    update_user_meta($user_id, 'cam_is_company_admin', 1);
                    
                    // Assign the company_admin role
                    $user = new WP_User($user_id);
                    $user->set_role('company_admin'); // Use set_role instead of add_role to replace the default subscriber role
                    
                    // Add company to database
                    global $wpdb;
                    $wpdb->insert(
                        $wpdb->prefix . 'cam_companies',
                        array(
                            'user_id' => $user_id,
                            'company_name' => $company_name,
                            'industry' => $industry,
                            'company_info' => $company_info,
                            'status' => 'pending',
                            'registration_date' => current_time('mysql')
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%s')
                    );
                    
                    // Send admin notification
                    self::send_admin_notification($user_id, $company_name);
                    
                    $success = __('Your company registration has been submitted and is pending approval. You will receive an email when your account is approved.', 'company-accounts-manager');
                }
            }
        }
        
        // Start output buffering
        ob_start();
        
        // Show error/success messages
        if (!empty($error)) {
            echo '<div class="woocommerce-error">' . esc_html($error) . '</div>';
        }
        
        if (!empty($success)) {
            echo '<div class="woocommerce-message">' . esc_html($success) . '</div>';
        } else {
            // Include the template
            $template_path = CAM_PLUGIN_DIR . 'templates/registration-form.php';
            
            // Debug output
            echo '<!-- Template path: ' . esc_html($template_path) . ' -->';
            echo '<!-- Template exists: ' . (file_exists($template_path) ? 'Yes' : 'No') . ' -->';
            
            if (file_exists($template_path)) {
                include($template_path);
            } else {
                // Fallback HTML form
                ?>
                <form method="post" class="cam-registration-form">
                    <h2><?php _e('Company Registration', 'company-accounts-manager'); ?></h2>
                    
                    <p class="form-row form-row-wide">
                        <label for="cam_username"><?php _e('Username', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                        <input type="text" class="input-text" name="cam_username" id="cam_username" autocomplete="username" value="<?php echo (!empty($_POST['cam_username'])) ? esc_attr(wp_unslash($_POST['cam_username'])) : ''; ?>" required />
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="cam_email"><?php _e('Email address', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                        <input type="email" class="input-text" name="cam_email" id="cam_email" autocomplete="email" value="<?php echo (!empty($_POST['cam_email'])) ? esc_attr(wp_unslash($_POST['cam_email'])) : ''; ?>" required />
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="cam_password"><?php _e('Password', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                        <input type="password" class="input-text" name="cam_password" id="cam_password" autocomplete="new-password" required />
                    </p>

                    <h3><?php _e('Company Information', 'company-accounts-manager'); ?></h3>

                    <p class="form-row form-row-wide">
                        <label for="cam_company_name"><?php _e('Company Name', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                        <input type="text" class="input-text" name="cam_company_name" id="cam_company_name" value="<?php echo (!empty($_POST['cam_company_name'])) ? esc_attr(wp_unslash($_POST['cam_company_name'])) : ''; ?>" required />
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="cam_industry"><?php _e('Industry', 'company-accounts-manager'); ?></label>
                        <input type="text" class="input-text" name="cam_industry" id="cam_industry" value="<?php echo (!empty($_POST['cam_industry'])) ? esc_attr(wp_unslash($_POST['cam_industry'])) : ''; ?>" />
                    </p>

                    <p class="form-row form-row-wide">
                        <label for="cam_company_info"><?php _e('Additional Information', 'company-accounts-manager'); ?></label>
                        <textarea name="cam_company_info" id="cam_company_info" rows="4"><?php echo (!empty($_POST['cam_company_info'])) ? esc_textarea(wp_unslash($_POST['cam_company_info'])) : ''; ?></textarea>
                    </p>

                    <p class="form-row">
                        <?php wp_nonce_field('cam_register', 'cam_register_nonce'); ?>
                        <button type="submit" class="button" name="cam_register" value="<?php esc_attr_e('Register', 'company-accounts-manager'); ?>"><?php _e('Register', 'company-accounts-manager'); ?></button>
                    </p>
                </form>
                <?php
            }
        }
        
        return ob_get_clean();
    }

    /**
     * Display the child accounts content
     */
    public static function child_accounts_content() {
        // Process child account status toggle (suspend/activate)
        if (isset($_POST['action']) && $_POST['action'] === 'cam_toggle_child_account_status' && 
            isset($_POST['cam_toggle_child_nonce']) && wp_verify_nonce($_POST['cam_toggle_child_nonce'], 'cam_toggle_child_account_status')) {
            
            $child_account_id = intval($_POST['child_account_id']);
            $action = sanitize_text_field($_POST['child_account_action']);
            
            if ($child_account_id > 0) {
                // Verify this child account belongs to the current company admin
                $company_id = CAM_Roles::get_user_company_id();
                global $wpdb;
                
                $child_company_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $child_account_id
                ));
                
                if ($child_company_id == $company_id) {
                    // Update the status
                    $new_status = ($action === 'suspend') ? 'suspended' : 'active';
                    
                    $wpdb->update(
                        $wpdb->prefix . 'cam_child_accounts',
                        array('status' => $new_status),
                        array('user_id' => $child_account_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    // Show success message
                    $message = ($action === 'suspend') ? 
                        __('Child account has been suspended.', 'company-accounts-manager') : 
                        __('Child account has been activated.', 'company-accounts-manager');
                    
                    echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('You do not have permission to manage this account.', 'company-accounts-manager') . '</p></div>';
                }
            }
        }
        
        $company_id = CAM_Roles::get_user_company_id();
        if (!$company_id) {
            return;
        }
        
        // Get company details
        global $wpdb;
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cam_companies WHERE id = %d",
            $company_id
        ));
        
        // Get child accounts
        $child_accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT ca.*, u.user_email, u.display_name 
            FROM {$wpdb->prefix}cam_child_accounts ca
            JOIN {$wpdb->users} u ON ca.user_id = u.ID
            WHERE ca.company_id = %d
            ORDER BY ca.created_date DESC",
            $company_id
        ));
        
        // Include the template
        include(CAM_PLUGIN_DIR . 'templates/child-accounts.php');
    }

    /**
     * Fix missing child accounts
     */
    public static function fix_missing_child_accounts() {
        global $wpdb;
        
        // Check if the tables exist
        $child_accounts_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cam_child_accounts'");
        $companies_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cam_companies'");
        
        if (!$child_accounts_table || !$companies_table) {
            return;
        }
        
        // Get all users with company_child role
        $users = get_users(array(
            'role' => 'company_child',
            'fields' => array('ID', 'user_email')
        ));
        
        if (empty($users)) {
            return;
        }
        
        $fixed_count = 0;
        
        foreach ($users as $user) {
            // Check if user already has a child account record
            $has_record = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user->ID
                )
            );
            
            if ($has_record) {
                continue;
            }
            
            // User has company_child role but no record in child_accounts table
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Found user with company_child role but no record - ID: ' . $user->ID . ', Email: ' . $user->user_email);
            }
            
            // Try to find the company ID from user meta
            $company_id = get_user_meta($user->ID, 'cam_company_id', true);
            
            if (!$company_id) {
                // Try to find the company by email domain
                $email_parts = explode('@', $user->user_email);
                $domain = end($email_parts);
                
                // Get all companies
                $companies = $wpdb->get_results(
                    "SELECT id, user_id FROM {$wpdb->prefix}cam_companies"
                );
                
                foreach ($companies as $company) {
                    // Get company admin email
                    $admin = get_userdata($company->user_id);
                    if ($admin) {
                        $admin_email_parts = explode('@', $admin->user_email);
                        $admin_domain = end($admin_email_parts);
                        
                        if ($domain === $admin_domain) {
                            $company_id = $company->id;
                            break;
                        }
                    }
                }
            }
            
            if ($company_id) {
                // Check if user has a suspended status in user meta
                $status = get_user_meta($user->ID, 'cam_account_status', true);
                if (empty($status)) {
                    $status = 'active'; // Default to active if no status is set
                }
                
                // Create a child account record
                $wpdb->insert(
                    $wpdb->prefix . 'cam_child_accounts',
                    array(
                        'user_id' => $user->ID,
                        'company_id' => $company_id,
                        'created_date' => current_time('mysql'),
                        'status' => $status
                    ),
                    array('%d', '%d', '%s', '%s')
                );
                
                $fixed_count++;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Company Admin: Created child account record for user ID: ' . $user->ID . ' with company ID: ' . $company_id . ' and status: ' . $status);
                }
            } else if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Could not find company ID for user ID: ' . $user->ID);
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && $fixed_count > 0) {
            error_log('CAM Company Admin: Fixed ' . $fixed_count . ' missing child account records');
        }
    }

    /**
     * Fix child account roles by removing the subscriber role
     */
    public static function fix_child_account_roles() {
        // Only run this on admin pages or when a company admin is logged in
        if (!is_admin() && !CAM_Roles::is_company_admin()) {
            return;
        }
        
        // Get all users with company_child role
        $users = get_users(array(
            'role' => 'company_child',
            'fields' => array('ID', 'user_email')
        ));
        
        if (empty($users)) {
            return;
        }
        
        $fixed_count = 0;
        
        foreach ($users as $user) {
            $user_obj = new WP_User($user->ID);
            
            // Check if user has subscriber role
            if (in_array('subscriber', (array) $user_obj->roles)) {
                // Remove subscriber role
                $user_obj->remove_role('subscriber');
                $fixed_count++;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Company Admin: Removed subscriber role from child account - ID: ' . $user->ID . ', Email: ' . $user->user_email);
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && $fixed_count > 0) {
            error_log('CAM Company Admin: Fixed roles for ' . $fixed_count . ' child accounts');
        }
    }

    /**
     * Check if the current user's company is suspended and log them out if it is
     */
    public static function check_company_suspension() {
        // Skip on login/logout pages to prevent redirect loops
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            return;
        }
        
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            global $wpdb;
            
            // Get company ID
            $company_id = CAM_Roles::get_user_company_id($user_id);
            
            if (!$company_id) {
                return;
            }
            
            // Get company status
            $company = self::get_company_details($company_id);
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Checking company status for user ID: ' . $user_id);
                error_log('CAM Company Admin: Company ID: ' . $company_id);
                error_log('CAM Company Admin: Company status: ' . ($company ? $company->status : 'NULL'));
            }
            
            // If company is suspended or rejected, force logout
            if ($company && ($company->status === 'suspended' || $company->status === 'rejected')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Company Admin: Forcing logout for user ID: ' . $user_id . ' due to company status: ' . $company->status);
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Redirect to login page with error message
                $error_type = $company->status === 'suspended' ? 'company_suspended' : 'company_rejected';
                wp_redirect(add_query_arg($error_type, '1', wp_login_url()));
                exit;
            }
            
            // If admin status is suspended, force logout
            if ($company && $company->admin_status === 'suspended') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Company Admin: Forcing logout for user ID: ' . $user_id . ' due to admin status: suspended');
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Redirect to login page with error message
                wp_redirect(add_query_arg('admin_suspended', '1', wp_login_url()));
                exit;
            }
        }
    }

    /**
     * Display a message on the login page for suspended companies
     */
    public static function suspended_company_login_message() {
        // Check if company_suspended parameter is set
        if (isset($_GET['company_suspended'])) {
            ?>
            <div id="login_error">
                <strong><?php _e('Error:', 'company-accounts-manager'); ?></strong> 
                <?php _e('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager'); ?>
            </div>
            <?php
        }
        
        // Check if company_rejected parameter is set
        if (isset($_GET['company_rejected'])) {
            ?>
            <div id="login_error">
                <strong><?php _e('Error:', 'company-accounts-manager'); ?></strong> 
                <?php _e('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager'); ?>
            </div>
            <?php
        }
        
        // Check if admin_suspended parameter is set
        if (isset($_GET['admin_suspended'])) {
            ?>
            <div id="login_error">
                <strong><?php _e('Error:', 'company-accounts-manager'); ?></strong> 
                <?php _e('Your admin account has been suspended. Please contact the site administrator.', 'company-accounts-manager'); ?>
            </div>
            <?php
        }
    }
    
    /**
     * Add a JavaScript redirect for suspended companies
     */
    public static function add_suspended_company_redirect_script() {
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for company admins
        if (!CAM_Roles::is_company_admin($user_id)) {
            return;
        }
        
        global $wpdb;
        
        // Get company ID
        $company_id = CAM_Roles::get_user_company_id($user_id);
        
        if (!$company_id) {
            return;
        }
        
        // Get company status
        $company = self::get_company_details($company_id);
        
        // If company is suspended or rejected, add JavaScript redirect
        if ($company && ($company->status === 'suspended' || $company->status === 'rejected')) {
            // Clear auth cookies server-side as well
            wp_clear_auth_cookie();
            
            // Delete user sessions
            $sessions = get_user_meta($user_id, 'session_tokens', true);
            if (!empty($sessions)) {
                delete_user_meta($user_id, 'session_tokens');
            }
            
            $error_type = $company->status === 'suspended' ? 'company_suspended' : 'company_rejected';
            $message = $company->status === 'suspended' ? 
                'Your company account has been suspended. Please contact the site administrator.' : 
                'Your company registration has been rejected. Please contact the site administrator.';
            
            // Output JavaScript to force logout
            ?>
            <script type="text/javascript">
            (function() {
                console.log('Company <?php echo $company->status; ?> - forcing logout');
                
                // Clear all cookies
                var cookies = document.cookie.split(';');
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i];
                    var eqPos = cookie.indexOf('=');
                    var name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    
                    // Also try with domain
                    var domain = window.location.hostname;
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + domain;
                }
                
                // Show message before redirect
                document.body.innerHTML = '<div style="text-align:center; padding:50px; font-family:sans-serif;">' +
                    '<h2>Company Account <?php echo ucfirst($company->status); ?></h2>' +
                    '<p><?php echo esc_js($message); ?></p>' +
                    '<p>You will be redirected to the login page in a moment...</p>' +
                    '</div>';
                
                // Redirect after a short delay
                setTimeout(function() {
                    window.location.href = "<?php echo esc_url(add_query_arg($error_type, '1', wp_login_url())); ?>";
                }, 3000);
            })();
            </script>
            <?php
            exit;
        }
        
        // If admin status is suspended, add JavaScript redirect
        if ($company && $company->admin_status === 'suspended') {
            // Clear auth cookies server-side as well
            wp_clear_auth_cookie();
            
            // Delete user sessions
            $sessions = get_user_meta($user_id, 'session_tokens', true);
            if (!empty($sessions)) {
                delete_user_meta($user_id, 'session_tokens');
            }
            
            // Output JavaScript to force logout
            ?>
            <script type="text/javascript">
            (function() {
                console.log('Admin suspended - forcing logout');
                
                // Clear all cookies
                var cookies = document.cookie.split(';');
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i];
                    var eqPos = cookie.indexOf('=');
                    var name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    
                    // Also try with domain
                    var domain = window.location.hostname;
                    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + domain;
                }
                
                // Show message before redirect
                document.body.innerHTML = '<div style="text-align:center; padding:50px; font-family:sans-serif;">' +
                    '<h2>Admin Account Suspended</h2>' +
                    '<p>Your admin account has been suspended. Please contact the site administrator.</p>' +
                    '<p>You will be redirected to the login page in a moment...</p>' +
                    '</div>';
                
                // Redirect after a short delay
                setTimeout(function() {
                    window.location.href = "<?php echo esc_url(add_query_arg('admin_suspended', '1', wp_login_url())); ?>";
                }, 3000);
            })();
            </script>
            <?php
            exit;
        }
    }

    /**
     * Block suspended companies from making AJAX requests
     */
    public static function block_suspended_company_ajax() {
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for company admins
        if (!CAM_Roles::is_company_admin($user_id)) {
            return;
        }
        
        global $wpdb;
        
        // Get company ID
        $company_id = CAM_Roles::get_user_company_id($user_id);
        
        if (!$company_id) {
            return;
        }
        
        // Get company status
        $company = self::get_company_details($company_id);
        
        // If company is suspended or rejected, block AJAX request
        if ($company && ($company->status === 'suspended' || $company->status === 'rejected')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Blocking AJAX request for user ID: ' . $user_id . ' due to company status: ' . $company->status);
            }
            
            $message = $company->status === 'suspended' ? 
                __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager') : 
                __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager');
            
            wp_send_json_error(array(
                'message' => $message
            ));
            exit;
        }
        
        // If admin status is suspended, block AJAX request
        if ($company && $company->admin_status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Blocking AJAX request for user ID: ' . $user_id . ' due to admin status: suspended');
            }
            
            wp_send_json_error(array(
                'message' => __('Your admin account has been suspended. Please contact the site administrator.', 'company-accounts-manager')
            ));
            exit;
        }
    }
    
    /**
     * Block suspended companies from making REST API requests
     */
    public static function block_suspended_company_rest_api($errors) {
        // If there are already errors, return them
        if (is_wp_error($errors)) {
            return $errors;
        }
        
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return $errors;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for company admins
        if (!CAM_Roles::is_company_admin($user_id)) {
            return $errors;
        }
        
        global $wpdb;
        
        // Get company ID
        $company_id = CAM_Roles::get_user_company_id($user_id);
        
        if (!$company_id) {
            return $errors;
        }
        
        // Get company status
        $company = self::get_company_details($company_id);
        
        // If company is suspended or rejected, block REST API request
        if ($company && ($company->status === 'suspended' || $company->status === 'rejected')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Blocking REST API request for user ID: ' . $user_id . ' due to company status: ' . $company->status);
            }
            
            $message = $company->status === 'suspended' ? 
                __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager') : 
                __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager');
            
            return new WP_Error(
                'company_' . $company->status,
                $message,
                array('status' => 403)
            );
        }
        
        // If admin status is suspended, block REST API request
        if ($company && $company->admin_status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Company Admin: Blocking REST API request for user ID: ' . $user_id . ' due to admin status: suspended');
            }
            
            return new WP_Error(
                'admin_suspended',
                __('Your admin account has been suspended. Please contact the site administrator.', 'company-accounts-manager'),
                array('status' => 403)
            );
        }
        
        return $errors;
    }

    /**
     * Allow company admins to view orders placed by their child accounts
     */
    public static function allow_company_admin_view_orders($caps, $cap, $user_id, $args) {
        // Handle view_order capability
        if ($cap === 'view_order' && !empty($args[0])) {
            // Check if the user is a company admin
            if (!CAM_Roles::is_company_admin($user_id)) {
                return $caps;
            }
            
            $order_id = $args[0];
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return $caps;
            }
            
            // Get the customer ID from the order
            $customer_id = $order->get_customer_id();
            
            if (!$customer_id) {
                return $caps;
            }
            
            // Get company ID of the company admin
            $admin_company_id = CAM_Roles::get_user_company_id($user_id);
            
            if (!$admin_company_id) {
                return $caps;
            }
            
            // Check if the customer is a child account of this company
            global $wpdb;
            $is_child_account = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
                WHERE user_id = %d AND company_id = %d",
                $customer_id, $admin_company_id
            ));
            
            // If this is a child account of the company, grant the capability
            if ($is_child_account) {
                // Debug log
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("CAM: Allowing company admin (ID: {$user_id}) to view order #{$order_id} placed by child account (ID: {$customer_id})");
                }
                
                // Replace the 'do_not_allow' cap with 'read'
                return array('read');
            }
        }
        
        // Handle edit_shop_orders capability for admin area access
        if ($cap === 'edit_shop_orders' && !empty($args[0])) {
            // Check if the user is a company admin
            if (!CAM_Roles::is_company_admin($user_id)) {
                return $caps;
            }
            
            $order_id = $args[0];
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return $caps;
            }
            
            // Get the customer ID from the order
            $customer_id = $order->get_customer_id();
            
            if (!$customer_id) {
                return $caps;
            }
            
            // Get company ID of the company admin
            $admin_company_id = CAM_Roles::get_user_company_id($user_id);
            
            if (!$admin_company_id) {
                return $caps;
            }
            
            // Check if the customer is a child account of this company
            global $wpdb;
            $is_child_account = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
                WHERE user_id = %d AND company_id = %d",
                $customer_id, $admin_company_id
            ));
            
            // If this is a child account of the company, grant the capability
            if ($is_child_account) {
                // Debug log
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("CAM: Allowing company admin (ID: {$user_id}) to edit order #{$order_id} placed by child account (ID: {$customer_id})");
                }
                
                // Replace the 'do_not_allow' cap with 'edit_posts'
                return array('edit_posts');
            }
        }
        
        return $caps;
    }

    /**
     * Filter capabilities for company admins to allow viewing orders
     */
    public static function filter_company_admin_caps($allcaps, $caps, $args) {
        // Only process for company admins
        $user_id = isset($args[1]) ? $args[1] : get_current_user_id();
        
        if (!CAM_Roles::is_company_admin($user_id)) {
            return $allcaps;
        }
        
        // Get the current screen if in admin
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        
        // If we're on the orders screen, add the capability to view orders
        if (is_admin() && $screen && $screen->base === 'shop_order') {
            $allcaps['view_woocommerce_reports'] = true;
            $allcaps['edit_shop_orders'] = true;
            $allcaps['read_private_shop_orders'] = true;
        }
        
        // If we're viewing a specific order
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'shop_order') {
            $order_id = absint($_GET['post']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                $customer_id = $order->get_customer_id();
                $admin_company_id = CAM_Roles::get_user_company_id($user_id);
                
                if ($customer_id && $admin_company_id) {
                    global $wpdb;
                    $is_child_account = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
                        WHERE user_id = %d AND company_id = %d",
                        $customer_id, $admin_company_id
                    ));
                    
                    if ($is_child_account) {
                        $allcaps['edit_shop_orders'] = true;
                        $allcaps['read_private_shop_orders'] = true;
                        $allcaps['view_order'] = true;
                    }
                }
            }
        }
        
        // If we're on the my-account page viewing an order
        if (!is_admin() && isset($_GET['view-order'])) {
            $order_id = absint($_GET['view-order']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                $customer_id = $order->get_customer_id();
                $admin_company_id = CAM_Roles::get_user_company_id($user_id);
                
                if ($customer_id && $admin_company_id) {
                    global $wpdb;
                    $is_child_account = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
                        WHERE user_id = %d AND company_id = %d",
                        $customer_id, $admin_company_id
                    ));
                    
                    if ($is_child_account) {
                        // Debug log
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("CAM: Granting view_order capability to company admin (ID: {$user_id}) for order #{$order_id}");
                        }
                        
                        // Add the capability
                        $allcaps['view_order'] = true;
                    }
                }
            }
        }
        
        return $allcaps;
    }

    /**
     * Allow company admins to view WooCommerce orders from their child accounts
     */
    public static function allow_company_admin_view_wc_order($visible, $order_id) {
        // If already visible, return true
        if ($visible) {
            return true;
        }
        
        // Get current user
        $user_id = get_current_user_id();
        
        // Check if user is a company admin
        if (!CAM_Roles::is_company_admin($user_id)) {
            return $visible;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return $visible;
        }
        
        // Get customer ID
        $customer_id = $order->get_customer_id();
        
        if (!$customer_id) {
            return $visible;
        }
        
        // Get company ID
        $admin_company_id = CAM_Roles::get_user_company_id($user_id);
        
        if (!$admin_company_id) {
            return $visible;
        }
        
        // Check if customer is a child account of this company
        global $wpdb;
        $is_child_account = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
            WHERE user_id = %d AND company_id = %d",
            $customer_id, $admin_company_id
        ));
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CAM: Checking if order #{$order_id} is visible to company admin (ID: {$user_id})");
            error_log("CAM: Customer ID: {$customer_id}, Company ID: {$admin_company_id}, Is Child Account: {$is_child_account}");
        }
        
        // If customer is a child account, make order visible
        if ($is_child_account) {
            return true;
        }
        
        return $visible;
    }

    /**
     * Add billing and shipping addresses to order view for Company Admins
     */
    public static function add_addresses_to_order_view($order_id) {
        // Only proceed if user is a company admin
        if (!CAM_Roles::is_company_admin()) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get customer ID from order
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        // Get company ID of the company admin
        $admin_company_id = CAM_Roles::get_user_company_id();
        if (!$admin_company_id) {
            return;
        }
        
        // Check if the customer is a child account of this company
        global $wpdb;
        $is_child_account = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
            WHERE user_id = %d AND company_id = %d",
            $customer_id, $admin_company_id
        ));
        
        // Only display addresses for child accounts
        if (!$is_child_account) {
            return;
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CAM: Adding addresses to order view for company admin (ID: " . get_current_user_id() . ") viewing order #$order_id");
        }
        
        // Use our custom template
        include(CAM_PLUGIN_DIR . 'templates/order/order-addresses.php');
    }

    /**
     * Ensure address visibility in admin area for Company Admins
     */
    public static function ensure_admin_address_visibility($order) {
        // Only proceed if user is a company admin
        if (!CAM_Roles::is_company_admin() || is_admin()) {
            return;
        }
        
        // Get customer ID from order
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        // Get company ID of the company admin
        $admin_company_id = CAM_Roles::get_user_company_id();
        if (!$admin_company_id) {
            return;
        }
        
        // Check if the customer is a child account of this company
        global $wpdb;
        $is_child_account = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
            WHERE user_id = %d AND company_id = %d",
            $customer_id, $admin_company_id
        ));
        
        // Only proceed for child accounts
        if (!$is_child_account) {
            return;
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CAM: Ensuring address visibility in admin for company admin (ID: " . get_current_user_id() . ") viewing order #" . $order->get_id());
        }
    }

    /**
     * Ensure all order details are visible for Company Admins
     */
    public static function ensure_order_details_visibility($formatted_meta, $item) {
        // Only proceed if user is a company admin
        if (!CAM_Roles::is_company_admin()) {
            return $formatted_meta;
        }
        
        // Get the order from the item
        $order = $item->get_order();
        if (!$order) {
            return $formatted_meta;
        }
        
        // Get customer ID from order
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return $formatted_meta;
        }
        
        // Get company ID of the company admin
        $admin_company_id = CAM_Roles::get_user_company_id();
        if (!$admin_company_id) {
            return $formatted_meta;
        }
        
        // Check if the customer is a child account of this company
        global $wpdb;
        $is_child_account = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts 
            WHERE user_id = %d AND company_id = %d",
            $customer_id, $admin_company_id
        ));
        
        // Only proceed for child accounts
        if (!$is_child_account) {
            return $formatted_meta;
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("CAM: Ensuring order details visibility for company admin (ID: " . get_current_user_id() . ") viewing order #" . $order->get_id());
        }
        
        return $formatted_meta;
    }
} 