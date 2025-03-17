<?php
/**
 * Handles user roles and capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Roles {
    /**
     * Initialize roles related hooks
     */
    public static function init() {
        add_filter('user_has_cap', array(__CLASS__, 'user_has_cap'), 10, 4);
        add_filter('editable_roles', array(__CLASS__, 'filter_editable_roles'));
    }

    /**
     * Check if user is a company admin
     */
    public static function is_company_admin($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: Checking if user ' . $user_id . ' is a company admin');
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Roles: User not found');
            }
            return false;
        }
        
        $is_admin = in_array('company_admin', (array) $user->roles);
        
        // Debug the result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: User roles: ' . implode(', ', (array) $user->roles));
            error_log('CAM Roles: Is company admin based on role: ' . ($is_admin ? 'Yes' : 'No'));
            
            // Check if user exists in companies table
            global $wpdb;
            $exists_in_table = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            error_log('CAM Roles: User exists in companies table: ' . ($exists_in_table > 0 ? 'Yes' : 'No'));
        }
        
        return $is_admin;
    }

    /**
     * Check if user is a child account
     */
    public static function is_child_account($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: Checking if user ' . $user_id . ' is a child account');
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Roles: User not found');
            }
            return false;
        }
        
        $is_child = in_array('company_child', (array) $user->roles);
        
        // Check if user exists in child_accounts table
        if ($is_child) {
            global $wpdb;
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            // Debug information about the child account
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($child_account) {
                    error_log('CAM Roles: Child account found in database - ID: ' . $child_account->id);
                    error_log('CAM Roles: Child account status: ' . ($child_account->status ?? 'NULL'));
                } else {
                    error_log('CAM Roles: User has company_child role but no record in child_accounts table');
                }
            }
        }
        
        // Debug the result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: User roles: ' . implode(', ', (array) $user->roles));
            error_log('CAM Roles: Is child account based on role: ' . ($is_child ? 'Yes' : 'No'));
            
            // Check if user exists in child_accounts table
            global $wpdb;
            $exists_in_table = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            error_log('CAM Roles: User exists in child_accounts table: ' . ($exists_in_table > 0 ? 'Yes' : 'No'));
            
            if ($exists_in_table > 0) {
                $status = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                        $user_id
                    )
                );
                error_log('CAM Roles: Child account status: ' . $status);
            }
        }
        
        return $is_child;
    }

    /**
     * Get company ID for a user
     */
    public static function get_user_company_id($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: Getting company ID for user: ' . $user_id);
            $user = get_userdata($user_id);
            if ($user) {
                error_log('CAM Roles: User email: ' . $user->user_email);
                error_log('CAM Roles: User roles: ' . implode(', ', (array) $user->roles));
            }
            error_log('CAM Roles: Is company admin: ' . (self::is_company_admin($user_id) ? 'Yes' : 'No'));
            error_log('CAM Roles: Is child account: ' . (self::is_child_account($user_id) ? 'Yes' : 'No'));
        }

        // First, check if user is a company admin
        if (self::is_company_admin($user_id)) {
            $query = $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                $user_id
            );
            
            // Debug the query
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Roles: Company admin query: ' . $query);
            }
            
            $company_id = $wpdb->get_var($query);
            
            // Debug the result
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Roles: Company ID found for admin: ' . ($company_id ? $company_id : 'None'));
            }
            
            return $company_id;
        }
        
        // Next, check if user is in the child_accounts table (regardless of role)
        $child_account = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                $user_id
            )
        );
        
        // Debug the result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: Child account query executed');
            
            if ($child_account) {
                error_log('CAM Roles: Child account found - ID: ' . $child_account->id);
                error_log('CAM Roles: Child account company ID: ' . $child_account->company_id);
                error_log('CAM Roles: Child account status: ' . ($child_account->status ?? 'NULL'));
            } else {
                error_log('CAM Roles: No child account record found for user ID: ' . $user_id);
                
                // Check if user has company_child role but no record
                $user = get_userdata($user_id);
                if ($user && in_array('company_child', (array) $user->roles)) {
                    error_log('CAM Roles: User has company_child role but no record in child_accounts table');
                }
            }
        }
        
        if ($child_account) {
            return $child_account->company_id;
        }
        
        // If no company ID found, try to get it from user meta as a fallback
        $company_id = get_user_meta($user_id, 'cam_company_id', true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Roles: Fallback - Company ID from user meta: ' . ($company_id ? $company_id : 'None'));
        }
        
        return $company_id;
    }

    /**
     * Filter user capabilities
     */
    public static function user_has_cap($allcaps, $caps, $args, $user) {
        // If checking for specific capabilities
        if (!empty($caps)) {
            foreach ($caps as $cap) {
                switch ($cap) {
                    case 'manage_child_accounts':
                        if (self::is_company_admin($user->ID)) {
                            $allcaps[$cap] = true;
                        }
                        break;

                    case 'make_purchases':
                        if (self::is_child_account($user->ID) || self::is_company_admin($user->ID)) {
                            $allcaps[$cap] = true;
                        }
                        break;

                    case 'view_company_reports':
                        if (self::is_company_admin($user->ID)) {
                            $allcaps[$cap] = true;
                        }
                        break;
                }
            }
        }

        return $allcaps;
    }

    /**
     * Filter editable roles
     */
    public static function filter_editable_roles($roles) {
        if (!current_user_can('administrator')) {
            // Company admins can only create child accounts
            if (self::is_company_admin()) {
                return array(
                    'company_child' => $roles['company_child']
                );
            }
            // Remove company roles from normal users
            unset($roles['company_admin']);
            unset($roles['company_child']);
        }
        return $roles;
    }

    /**
     * Get all child accounts for a company
     */
    public static function get_company_child_accounts($company_id) {
        global $wpdb;
        
        // Debug information
        error_log('Getting child accounts for company ID: ' . $company_id);
        
        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cam_child_accounts'");
        if (!$table_exists) {
            error_log('Child accounts table does not exist');
            return array();
        }
        
        $query = $wpdb->prepare(
            "SELECT ca.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}cam_child_accounts ca 
            JOIN {$wpdb->users} u ON ca.user_id = u.ID 
            WHERE ca.company_id = %d 
            ORDER BY ca.created_date DESC",
            $company_id
        );
        
        // Debug the query
        error_log('Child accounts query: ' . $query);
        
        $results = $wpdb->get_results($query);
        
        // Debug the results
        error_log('Child accounts found: ' . count($results));
        
        return $results;
    }

    /**
     * Get company admin details
     */
    public static function get_company_admin_details($company_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
            $company_id
        ));

        if ($user_id) {
            return get_userdata($user_id);
        }

        return false;
    }

    /**
     * Check if a user can manage another user
     */
    public static function can_manage_user($manager_id, $user_id) {
        if (user_can($manager_id, 'administrator')) {
            return true;
        }

        if (self::is_company_admin($manager_id)) {
            $company_id = self::get_user_company_id($manager_id);
            $child_company_id = self::get_user_company_id($user_id);
            return $company_id && $company_id === $child_company_id;
        }

        return false;
    }

    /**
     * Add custom roles and capabilities
     */
    public static function add_roles() {
        // Add Company Admin role
        add_role(
            'company_admin',
            __('Company Admin', 'company-accounts-manager'),
            array(
                'read' => true,
                'manage_child_accounts' => true,
                'view_company_reports' => true,
                'make_purchases' => true,
            )
        );
        
        // Add Company Child role
        add_role(
            'company_child',
            __('Company Child Account', 'company-accounts-manager'),
            array(
                'read' => true,
                'make_purchases' => true,
            )
        );
        
        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('approve_company_registrations');
            $admin->add_cap('manage_company_accounts');
        }
    }
    
    /**
     * Remove custom roles and capabilities
     */
    public static function remove_roles() {
        // Remove roles
        remove_role('company_admin');
        remove_role('company_child');
        
        // Remove capabilities from administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap('approve_company_registrations');
            $admin->remove_cap('manage_company_accounts');
        }
    }

    /**
     * Get company ID for a child account (alias for get_user_company_id)
     */
    public static function get_child_account_company_id($user_id = null) {
        return self::get_user_company_id($user_id);
    }
} 