<?php
/**
 * Handles child account functionality and order tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Child_Account {
    /**
     * Initialize child account functionality
     */
    public static function init() {
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'link_order_to_company'));
        add_filter('woocommerce_my_account_my_orders_query', array(__CLASS__, 'filter_orders_query'));
        add_action('woocommerce_before_my_account', array(__CLASS__, 'display_company_info'));
        
        // Add authentication filters to check child account status
        add_filter('authenticate', array(__CLASS__, 'check_child_account_status'), 30, 3);
        add_filter('wp_authenticate_user', array(__CLASS__, 'block_suspended_user_login'), 999, 2);
        
        // Check if current user is suspended on every page load
        add_action('init', array(__CLASS__, 'check_current_user_suspension'), 1);
        
        // Check for suspended accounts on AJAX requests
        add_action('wp_ajax_nopriv_*', array(__CLASS__, 'block_suspended_ajax'), 1);
        add_action('wp_ajax_*', array(__CLASS__, 'block_suspended_ajax'), 1);
        
        // Check for suspended accounts on REST API requests
        add_filter('rest_authentication_errors', array(__CLASS__, 'block_suspended_rest_api'), 10);
        
        // Hide admin bar for suspended accounts
        add_filter('show_admin_bar', array(__CLASS__, 'hide_admin_bar_for_suspended'), 999);
        
        // Add login error message for suspended accounts
        add_action('login_message', array(__CLASS__, 'suspended_account_login_message'));
        
        // Add a JavaScript redirect for suspended accounts
        add_action('wp_head', array(__CLASS__, 'add_suspended_redirect_script'), 1);
        add_action('admin_head', array(__CLASS__, 'add_suspended_redirect_script'), 1);
        
        // Add a filter to prevent suspended users from being authenticated
        add_filter('determine_current_user', array(__CLASS__, 'prevent_suspended_user_auth'), 999);
        
        // Add a filter to check auth cookies validity
        add_filter('auth_cookie_valid', array(__CLASS__, 'check_auth_cookie_valid'), 999, 2);
    }

    /**
     * Link order to company during checkout
     */
    public static function link_order_to_company($order_id) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }

        $company_id = CAM_Roles::get_user_company_id($user_id);
        
        if (!$company_id) {
            return;
        }

        $order = wc_get_order($order_id);
        $order_total = $order->get_total();

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cam_company_orders',
            array(
                'order_id' => $order_id,
                'company_id' => $company_id,
                'user_id' => $user_id,
                'order_total' => $order_total,
                'order_date' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%f', '%s')
        );

        // Add company info to order meta
        update_post_meta($order_id, '_cam_company_id', $company_id);
        
        // If it's a child account, add that info too
        if (CAM_Roles::is_child_account($user_id)) {
            update_post_meta($order_id, '_cam_is_child_order', 'yes');
        }

        // Notify company admin about the order
        if (CAM_Roles::is_child_account($user_id)) {
            self::notify_company_admin_about_order($order_id, $company_id);
        }
    }

    /**
     * Filter orders query for child accounts
     */
    public static function filter_orders_query($args) {
        $user_id = get_current_user_id();
        
        if (CAM_Roles::is_child_account($user_id)) {
            // Child accounts can only see their own orders
            $args['customer'] = $user_id;
        }
        
        return $args;
    }

    /**
     * Display company info in My Account
     */
    public static function display_company_info() {
        $user_id = get_current_user_id();
        
        if (!CAM_Roles::is_child_account($user_id)) {
            return;
        }

        $company_id = CAM_Roles::get_user_company_id($user_id);
        if (!$company_id) {
            return;
        }

        $company = CAM_Company_Admin::get_company_details($company_id);
        if (!$company) {
            return;
        }

        ?>
        <div class="cam-company-info">
            <h3><?php _e('Your Company Information', 'company-accounts-manager'); ?></h3>
            <p>
                <strong><?php _e('Company:', 'company-accounts-manager'); ?></strong>
                <?php echo esc_html($company->company_name); ?>
            </p>
            <?php if (!empty($company->company_info)) : ?>
                <p>
                    <strong><?php _e('Company Info:', 'company-accounts-manager'); ?></strong>
                    <?php echo wp_kses_post($company->company_info); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Notify company admin about new order from child account
     */
    private static function notify_company_admin_about_order($order_id, $company_id) {
        $order = wc_get_order($order_id);
        $user = $order->get_user();
        $admin = CAM_Roles::get_company_admin_details($company_id);

        if (!$admin) {
            return;
        }

        $subject = sprintf(
            __('[%s] New Order from Child Account: #%s', 'company-accounts-manager'),
            get_bloginfo('name'),
            $order->get_order_number()
        );

        $message = sprintf(
            __("A new order has been placed by one of your child accounts:\n\nOrder: #%s\nUser: %s\nAmount: %s\n\nView order details here: %s", 'company-accounts-manager'),
            $order->get_order_number(),
            $user->display_name,
            $order->get_formatted_order_total(),
            $order->get_edit_order_url()
        );

        wp_mail($admin->user_email, $subject, $message);
    }

    /**
     * Get orders for a specific child account
     */
    public static function get_child_account_orders($user_id, $args = array()) {
        $default_args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'customer' => $user_id
        );

        $args = wp_parse_args($args, $default_args);
        return wc_get_orders($args);
    }

    /**
     * Get total spent by a child account
     */
    public static function get_child_account_total_spent($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(order_total) 
            FROM {$wpdb->prefix}cam_company_orders 
            WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get order statistics for a child account
     */
    public static function get_child_account_stats($user_id, $date_range = '') {
        global $wpdb;

        $where = "WHERE user_id = %d";
        $params = array($user_id);

        if ($date_range) {
            switch ($date_range) {
                case '7days':
                    $where .= " AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case '30days':
                    $where .= " AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'this_month':
                    $where .= " AND MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE())";
                    break;
            }
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(order_total) as total_spent,
                AVG(order_total) as average_order,
                MAX(order_total) as largest_order
            FROM {$wpdb->prefix}cam_company_orders
            $where",
            $params
        ));

        return array(
            'total_orders' => (int) $stats->total_orders,
            'total_spent' => (float) $stats->total_spent,
            'average_order' => (float) $stats->average_order,
            'largest_order' => (float) $stats->largest_order
        );
    }

    /**
     * Check if a child account is suspended during authentication
     */
    public static function check_child_account_status($user, $username, $password) {
        // If authentication failed, return the error
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth: Authentication check starting for user: ' . $username);
            error_log('CAM Auth: User ID: ' . (isset($user->ID) ? $user->ID : 'Not set'));
            error_log('CAM Auth: User roles: ' . (isset($user->roles) ? implode(', ', (array) $user->roles) : 'Not set'));
        }
        
            // Check if this is a child account
        if (isset($user->roles) && in_array('company_child', (array) $user->roles)) {
                global $wpdb;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth: User is a child account, checking status');
            }
                
                // Get child account status
                $status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user->ID
                ));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth: Child account status query: ' . $wpdb->last_query);
                error_log('CAM Auth: Child account status: ' . ($status ? $status : 'NULL'));
            }
                
                // If suspended, prevent login
                if ($status === 'suspended') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth: Login denied - Account is suspended');
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user->ID, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user->ID, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Return error
                return new WP_Error(
                    'account_suspended', 
                    __('Your account has been suspended. Please contact your company administrator.', 'company-accounts-manager')
                );
            }
            
            // Check if parent company is suspended
            $company_id = $wpdb->get_var($wpdb->prepare(
                "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                $user->ID
            ));
            
            if ($company_id) {
                $company_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                    $company_id
                ));
                
                // Debug information
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth: Company ID: ' . $company_id);
                    error_log('CAM Auth: Company status query: ' . $wpdb->last_query);
                    error_log('CAM Auth: Company status: ' . ($company_status ? $company_status : 'NULL'));
                }
                
                if ($company_status === 'suspended') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Auth: Login denied - Company is suspended');
                    }
                    
                    // Delete user sessions
                    $sessions = get_user_meta($user->ID, 'session_tokens', true);
                    if (!empty($sessions)) {
                        delete_user_meta($user->ID, 'session_tokens');
                    }
                    
                    // Clear auth cookies
                    wp_clear_auth_cookie();
                    
                    // Return error
                    return new WP_Error(
                        'company_suspended', 
                        __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager')
                    );
                }
                
                if ($company_status === 'rejected') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Auth: Login denied - Company is rejected');
                    }
                    
                    // Delete user sessions
                    $sessions = get_user_meta($user->ID, 'session_tokens', true);
                    if (!empty($sessions)) {
                        delete_user_meta($user->ID, 'session_tokens');
                    }
                    
                    // Clear auth cookies
                    wp_clear_auth_cookie();
                    
                    // Return error
                    return new WP_Error(
                        'company_rejected', 
                        __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager')
                    );
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth: User is not a child account, skipping status check');
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth: Authentication check completed, allowing login');
        }
        
        return $user;
    }

    /**
     * Check if current logged-in user is suspended and force logout if they are
     */
    public static function check_current_user_suspension() {
        // Skip on login/logout pages to prevent redirect loops
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            return;
        }
        
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for child accounts
        if (!in_array('company_child', (array) wp_get_current_user()->roles)) {
            return;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth Check: Checking user ID: ' . $user_id);
            error_log('CAM Auth Check: User status: ' . ($status ? $status : 'NULL'));
        }
        
        // If suspended, force logout
        if ($status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Check: Forcing logout for suspended user ID: ' . $user_id);
            }
            
            // Delete user sessions
            $sessions = get_user_meta($user_id, 'session_tokens', true);
            if (!empty($sessions)) {
                delete_user_meta($user_id, 'session_tokens');
            }
            
            // Clear auth cookies
            wp_clear_auth_cookie();
            
            // Redirect to login page with error message
            wp_redirect(add_query_arg('account_suspended', '1', wp_login_url()));
            exit;
        }
        
        // Also check if parent company is suspended
        $company_id = $wpdb->get_var($wpdb->prepare(
            "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Check: Company ID: ' . $company_id);
                error_log('CAM Auth Check: Company status: ' . ($company_status ? $company_status : 'NULL'));
            }
            
            // If company is suspended or rejected, force logout
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Check: Forcing logout for user ID: ' . $user_id . ' due to company status: ' . $company_status);
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Redirect to login page with error message
                $error_type = $company_status === 'suspended' ? 'company_suspended' : 'company_rejected';
                wp_redirect(add_query_arg($error_type, '1', wp_login_url()));
                exit;
            }
        }
    }

    /**
     * Display a message on the login page for suspended accounts
     */
    public static function suspended_account_login_message() {
        // Check if account_suspended parameter is set
        if (isset($_GET['account_suspended'])) {
            ?>
            <div id="login_error">
                <strong><?php _e('Error:', 'company-accounts-manager'); ?></strong> 
                <?php _e('Your account has been suspended. Please contact your company administrator.', 'company-accounts-manager'); ?>
            </div>
            <?php
        }
        
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
    }

    /**
     * Block suspended accounts from making AJAX requests
     */
    public static function block_suspended_ajax() {
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for child accounts
        if (!CAM_Roles::is_child_account($user_id)) {
            return;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // If suspended, block AJAX request
        if ($status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Check: Blocking AJAX request for suspended user ID: ' . $user_id);
            }
            
            wp_send_json_error(array(
                'message' => __('Your account has been suspended. Please contact your company administrator.', 'company-accounts-manager')
            ));
            exit;
        }
        
        // Also check if parent company is suspended
        $company_id = CAM_Roles::get_user_company_id($user_id);
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // If company is suspended or rejected, block AJAX request
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Check: Blocking AJAX request for user ID: ' . $user_id . ' due to company status: ' . $company_status);
                }
                
                $message = $company_status === 'suspended' ? 
                    __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager') : 
                    __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager');
                
                wp_send_json_error(array(
                    'message' => $message
                ));
                exit;
            }
        }
    }

    /**
     * Block suspended accounts from making REST API requests
     */
    public static function block_suspended_rest_api($errors) {
        // If there are already errors, return them
        if (is_wp_error($errors)) {
            return $errors;
        }
        
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return $errors;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for child accounts
        if (!CAM_Roles::is_child_account($user_id)) {
            return $errors;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // If suspended, block REST API request
        if ($status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Check: Blocking REST API request for suspended user ID: ' . $user_id);
            }
            
            return new WP_Error(
                'account_suspended',
                __('Your account has been suspended. Please contact your company administrator.', 'company-accounts-manager'),
                array('status' => 403)
            );
        }
        
        // Also check if parent company is suspended
        $company_id = CAM_Roles::get_user_company_id($user_id);
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // If company is suspended or rejected, block REST API request
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Check: Blocking REST API request for user ID: ' . $user_id . ' due to company status: ' . $company_status);
                }
                
                $message = $company_status === 'suspended' ? 
                    __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager') : 
                    __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager');
                
                return new WP_Error(
                    'company_' . $company_status,
                    $message,
                    array('status' => 403)
                );
            }
        }
        
        return $errors;
    }

    /**
     * Hide admin bar for suspended accounts
     */
    public static function hide_admin_bar_for_suspended($show) {
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return $show;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for child accounts
        if (!CAM_Roles::is_child_account($user_id)) {
            return $show;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // If suspended, hide admin bar
        if ($status === 'suspended') {
            return false;
        }
        
        // Also check if parent company is suspended
        $company_id = CAM_Roles::get_user_company_id($user_id);
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // If company is suspended or rejected, hide admin bar
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                return false;
            }
        }
        
        return $show;
    }
    
    /**
     * Add a JavaScript redirect for suspended accounts
     */
    public static function add_suspended_redirect_script() {
        // Only check for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Only check for child accounts
        if (!in_array('company_child', (array) wp_get_current_user()->roles)) {
            return;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // If suspended, add JavaScript redirect
        if ($status === 'suspended') {
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
                console.log('Account suspended - forcing logout');
                
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
                    '<h2>Account Suspended</h2>' +
                    '<p>Your account has been suspended. Please contact your company administrator.</p>' +
                    '<p>You will be redirected to the login page in a moment...</p>' +
                    '</div>';
                
                // Redirect after a short delay
                setTimeout(function() {
                    window.location.href = "<?php echo esc_url(add_query_arg('account_suspended', '1', wp_login_url())); ?>";
                }, 2000);
            })();
            </script>
            <?php
            exit;
        }
        
        // Also check if parent company is suspended
        $company_id = $wpdb->get_var($wpdb->prepare(
            "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // If company is suspended or rejected, add JavaScript redirect
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                // Clear auth cookies server-side as well
                wp_clear_auth_cookie();
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                $error_type = $company_status === 'suspended' ? 'company_suspended' : 'company_rejected';
                $message = $company_status === 'suspended' ? 
                    'Your company account has been suspended. Please contact the site administrator.' : 
                    'Your company registration has been rejected. Please contact the site administrator.';
                
                // Output JavaScript to force logout
                ?>
                <script type="text/javascript">
                (function() {
                    console.log('Company <?php echo $company_status; ?> - forcing logout');
                    
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
                        '<h2>Company Account <?php echo ucfirst($company_status); ?></h2>' +
                        '<p><?php echo esc_js($message); ?></p>' +
                        '<p>You will be redirected to the login page in a moment...</p>' +
                        '</div>';
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = "<?php echo esc_url(add_query_arg($error_type, '1', wp_login_url())); ?>";
                    }, 2000);
                })();
                </script>
                <?php
                exit;
            }
        }
    }

    /**
     * Prevent suspended users from being authenticated
     */
    public static function prevent_suspended_user_auth($user_id) {
        if (!$user_id) {
            return $user_id;
        }
        
        // Only check for child accounts
        $user = get_userdata($user_id);
        if (!$user || !in_array('company_child', (array) $user->roles)) {
            return $user_id;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth Filter: Checking user ID: ' . $user_id);
            error_log('CAM Auth Filter: User status: ' . ($status ? $status : 'NULL'));
        }
        
        // If suspended, prevent authentication
        if ($status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Filter: Preventing authentication for suspended user ID: ' . $user_id);
            }
            
            // Delete user sessions
            $sessions = get_user_meta($user_id, 'session_tokens', true);
            if (!empty($sessions)) {
                delete_user_meta($user_id, 'session_tokens');
            }
            
            // Clear auth cookies
            wp_clear_auth_cookie();
            
            // Return 0 to indicate no user
            return 0;
        }
        
        // Also check if parent company is suspended
        $company_id = $wpdb->get_var($wpdb->prepare(
            "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Filter: Company ID: ' . $company_id);
                error_log('CAM Auth Filter: Company status: ' . ($company_status ? $company_status : 'NULL'));
            }
            
            // If company is suspended or rejected, prevent authentication
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Filter: Preventing authentication for user ID: ' . $user_id . ' due to company status: ' . $company_status);
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Return 0 to indicate no user
                return 0;
            }
        }
        
        return $user_id;
    }
    
    /**
     * Check if the auth cookie is valid for suspended accounts
     */
    public static function check_auth_cookie_valid($valid, $user_id) {
        if (!$valid) {
            return $valid;
        }
        
        // Only check for child accounts
        $user = get_userdata($user_id);
        if (!$user || !in_array('company_child', (array) $user->roles)) {
            return $valid;
        }
        
        global $wpdb;
        
        // Get child account status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth Cookie: Checking user ID: ' . $user_id);
            error_log('CAM Auth Cookie: User status: ' . ($status ? $status : 'NULL'));
        }
        
        // If suspended, invalidate cookie
        if ($status === 'suspended') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Cookie: Invalidating cookie for suspended user ID: ' . $user_id);
            }
            
            // Delete user sessions
            $sessions = get_user_meta($user_id, 'session_tokens', true);
            if (!empty($sessions)) {
                delete_user_meta($user_id, 'session_tokens');
            }
            
            return false;
        }
        
        // Also check if parent company is suspended
        $company_id = $wpdb->get_var($wpdb->prepare(
            "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
            $user_id
        ));
        
        if ($company_id) {
            $company_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            ));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Cookie: Company ID: ' . $company_id);
                error_log('CAM Auth Cookie: Company status: ' . ($company_status ? $company_status : 'NULL'));
            }
            
            // If company is suspended or rejected, invalidate cookie
            if ($company_status === 'suspended' || $company_status === 'rejected') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Cookie: Invalidating cookie for user ID: ' . $user_id . ' due to company status: ' . $company_status);
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user_id, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user_id, 'session_tokens');
                }
                
                return false;
            }
        }
        
        return $valid;
    }

    /**
     * Block suspended users from logging in (final check)
     */
    public static function block_suspended_user_login($user, $password) {
        // If authentication failed, return the error
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth Final Check: Starting for user ID: ' . $user->ID);
        }
        
        // Check if this is a child account
        if (isset($user->roles) && in_array('company_child', (array) $user->roles)) {
            global $wpdb;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Final Check: User is a child account, checking status');
            }
            
            // Get child account status directly from the database
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                $user->ID
            ));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Auth Final Check: Child account status query: ' . $wpdb->last_query);
                error_log('CAM Auth Final Check: Child account status: ' . ($status ? $status : 'NULL'));
            }
            
            // If suspended, prevent login
            if ($status === 'suspended') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Final Check: Login denied - Account is suspended');
                }
                
                // Delete user sessions
                $sessions = get_user_meta($user->ID, 'session_tokens', true);
                if (!empty($sessions)) {
                    delete_user_meta($user->ID, 'session_tokens');
                }
                
                // Clear auth cookies
                wp_clear_auth_cookie();
                
                // Return error
                return new WP_Error(
                    'account_suspended', 
                    __('Your account has been suspended. Please contact your company administrator.', 'company-accounts-manager')
                    );
                }
                
                // Check if parent company is suspended
            $company_id = $wpdb->get_var($wpdb->prepare(
                "SELECT company_id FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                $user->ID
            ));
            
                if ($company_id) {
                    $company_status = $wpdb->get_var($wpdb->prepare(
                        "SELECT status FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                        $company_id
                    ));
                
                // Debug information
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('CAM Auth Final Check: Company ID: ' . $company_id);
                    error_log('CAM Auth Final Check: Company status query: ' . $wpdb->last_query);
                    error_log('CAM Auth Final Check: Company status: ' . ($company_status ? $company_status : 'NULL'));
                }
                    
                    if ($company_status === 'suspended') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Auth Final Check: Login denied - Company is suspended');
                    }
                    
                    // Delete user sessions
                    $sessions = get_user_meta($user->ID, 'session_tokens', true);
                    if (!empty($sessions)) {
                        delete_user_meta($user->ID, 'session_tokens');
                    }
                    
                    // Clear auth cookies
                    wp_clear_auth_cookie();
                    
                    // Return error
                        return new WP_Error(
                            'company_suspended', 
                            __('Your company account has been suspended. Please contact the site administrator.', 'company-accounts-manager')
                        );
                    }
                
                if ($company_status === 'rejected') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Auth Final Check: Login denied - Company is rejected');
                    }
                    
                    // Delete user sessions
                    $sessions = get_user_meta($user->ID, 'session_tokens', true);
                    if (!empty($sessions)) {
                        delete_user_meta($user->ID, 'session_tokens');
                    }
                    
                    // Clear auth cookies
                    wp_clear_auth_cookie();
                    
                    // Return error
                    return new WP_Error(
                        'company_rejected', 
                        __('Your company registration has been rejected. Please contact the site administrator.', 'company-accounts-manager')
                    );
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Auth Final Check: Authentication check completed, allowing login');
        }
        
        return $user;
    }
} 