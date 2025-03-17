<?php
/**
 * Handles discount tiers management
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Tiers {
    /**
     * Initialize tiers functionality
     */
    public static function init() {
        // Add hooks for tier management
        add_action('wp_ajax_cam_add_tier', array(__CLASS__, 'ajax_add_tier'));
        add_action('wp_ajax_cam_update_tier', array(__CLASS__, 'ajax_update_tier'));
        add_action('wp_ajax_cam_delete_tier', array(__CLASS__, 'ajax_delete_tier'));
        add_action('wp_ajax_cam_assign_tier', array(__CLASS__, 'ajax_assign_tier'));
    }
    
    /**
     * Get all tiers
     */
    public static function get_all_tiers() {
        global $wpdb;
        
        $tiers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}cam_discount_tiers ORDER BY discount_percentage ASC"
        );
        
        return $tiers;
    }
    
    /**
     * Get tier by ID
     */
    public static function get_tier($tier_id) {
        global $wpdb;
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Tiers: Getting tier details for tier ID: ' . $tier_id);
        }
        
        $tier = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cam_discount_tiers WHERE id = %d",
                $tier_id
            )
        );
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Tiers: SQL Query: ' . $wpdb->last_query);
            if ($tier) {
                error_log('CAM Tiers: Tier found - ID: ' . $tier->id . ', Name: ' . $tier->name . ', Discount: ' . $tier->discount_percentage . '%');
            } else {
                error_log('CAM Tiers: No tier found for ID: ' . $tier_id);
            }
        }
        
        return $tier;
    }
    
    /**
     * Add a new tier
     */
    public static function add_tier($tier_name, $discount_percentage) {
        global $wpdb;
        
        $result = $wpdb->insert(
            "{$wpdb->prefix}cam_discount_tiers",
            array(
                'tier_name' => sanitize_text_field($tier_name),
                'discount_percentage' => floatval($discount_percentage),
                'created_date' => current_time('mysql')
            ),
            array('%s', '%f', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update an existing tier
     */
    public static function update_tier($tier_id, $tier_name, $discount_percentage) {
        global $wpdb;
        
        $result = $wpdb->update(
            "{$wpdb->prefix}cam_discount_tiers",
            array(
                'tier_name' => sanitize_text_field($tier_name),
                'discount_percentage' => floatval($discount_percentage)
            ),
            array('id' => $tier_id),
            array('%s', '%f'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a tier
     */
    public static function delete_tier($tier_id) {
        global $wpdb;
        
        // First, remove this tier from any companies using it
        $wpdb->update(
            "{$wpdb->prefix}cam_companies",
            array('tier_id' => null),
            array('tier_id' => $tier_id),
            array('%d'),
            array('%d')
        );
        
        // Then delete the tier
        $result = $wpdb->delete(
            "{$wpdb->prefix}cam_discount_tiers",
            array('id' => $tier_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Assign a tier to a company
     */
    public static function assign_tier_to_company($company_id, $tier_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            "{$wpdb->prefix}cam_companies",
            array('tier_id' => $tier_id ? $tier_id : null),
            array('id' => $company_id),
            array('%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get company tier
     */
    public static function get_company_tier($company_id) {
        global $wpdb;
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Tiers: Getting tier for company ID: ' . $company_id);
        }
        
        $tier_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tier_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                $company_id
            )
        );
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Tiers: SQL Query: ' . $wpdb->last_query);
            error_log('CAM Tiers: Tier ID found for company: ' . ($tier_id ? $tier_id : 'None'));
        }
        
        if (!$tier_id) {
            return null;
        }
        
        $tier = self::get_tier($tier_id);
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($tier) {
                error_log('CAM Tiers: Tier details - ID: ' . $tier->id . ', Name: ' . $tier->name . ', Discount: ' . $tier->discount_percentage . '%');
            } else {
                error_log('CAM Tiers: No tier details found for tier ID: ' . $tier_id);
            }
        }
        
        return $tier;
    }
    
    /**
     * Get current user's company tier
     */
    public static function get_current_user_tier() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Tiers: Getting tier for user ID: ' . $user_id);
            $user = get_userdata($user_id);
            if ($user) {
                error_log('CAM Tiers: User roles: ' . implode(', ', (array) $user->roles));
                error_log('CAM Tiers: Is company admin: ' . (CAM_Roles::is_company_admin($user_id) ? 'Yes' : 'No'));
                error_log('CAM Tiers: Is child account: ' . (CAM_Roles::is_child_account($user_id) ? 'Yes' : 'No'));
            }
        }
        
        $company_id = null;
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            global $wpdb;
            $company_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Tiers: Company ID for admin from direct query: ' . ($company_id ? $company_id : 'None'));
            }
        }
        // Check if user is a child account
        else if (CAM_Roles::is_child_account($user_id)) {
            global $wpdb;
            
            // Get child account record with any status
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($child_account) {
                    error_log('CAM Tiers: Child account found - ID: ' . $child_account->id);
                    error_log('CAM Tiers: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM Tiers: Child account status: ' . ($child_account->status ?? 'NULL'));
                    
                    // Skip if account is suspended
                    if (isset($child_account->status) && $child_account->status === 'suspended') {
                        error_log('CAM Tiers: Child account is suspended - No tier will be applied');
                        return null;
                    }
                } else {
                    error_log('CAM Tiers: No child account record found');
                }
            }
            
            // Only proceed if account exists and is not suspended
            if ($child_account && (!isset($child_account->status) || $child_account->status !== 'suspended')) {
                $company_id = $child_account->company_id;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Tiers: Company ID for child account: ' . ($company_id ? $company_id : 'None'));
            }
        }
        // Fallback to the general method
        else {
            $company_id = CAM_Roles::get_user_company_id($user_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Tiers: Company ID from get_user_company_id fallback: ' . ($company_id ? $company_id : 'None'));
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Check if the user is in the child_accounts table
            global $wpdb;
            $child_account_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            error_log('CAM Tiers: Child account exists in table: ' . ($child_account_exists > 0 ? 'Yes' : 'No'));
            
            // Check if the child account is active
            if ($child_account_exists > 0) {
                $child_account_active = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d AND status = 'active'",
                        $user_id
                    )
                );
                error_log('CAM Tiers: Child account is active: ' . ($child_account_active > 0 ? 'Yes' : 'No'));
            }
        }
        
        if ($company_id) {
            $tier = self::get_company_tier($company_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Tiers: Tier found: ' . ($tier ? 'Yes (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'No'));
                
                if (!$tier) {
                    // Check if the company has a tier assigned
                    global $wpdb;
                    $tier_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT tier_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                            $company_id
                        )
                    );
                    error_log('CAM Tiers: Company tier ID from direct query: ' . ($tier_id ? $tier_id : 'None'));
                    error_log('CAM Tiers: SQL Query: ' . $wpdb->last_query);
                    
                    if ($tier_id) {
                        // Check if the tier exists in the tiers table
                        $tier_exists = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}cam_discount_tiers WHERE id = %d",
                                $tier_id
                            )
                        );
                        error_log('CAM Tiers: Tier exists in tiers table: ' . ($tier_exists > 0 ? 'Yes' : 'No'));
                    }
                }
            }
            
            return $tier;
        }
        
        return null;
    }
    
    /**
     * AJAX handler for adding a tier
     */
    public static function ajax_add_tier() {
        check_ajax_referer('cam_tier_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'company-accounts-manager')));
        }
        
        $tier_name = isset($_POST['tier_name']) ? sanitize_text_field($_POST['tier_name']) : '';
        $discount_percentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
        
        if (empty($tier_name)) {
            wp_send_json_error(array('message' => __('Tier name is required.', 'company-accounts-manager')));
        }
        
        if ($discount_percentage < 0 || $discount_percentage > 100) {
            wp_send_json_error(array('message' => __('Discount percentage must be between 0 and 100.', 'company-accounts-manager')));
        }
        
        $tier_id = self::add_tier($tier_name, $discount_percentage);
        
        if ($tier_id) {
            wp_send_json_success(array(
                'message' => __('Tier added successfully.', 'company-accounts-manager'),
                'tier_id' => $tier_id,
                'tier_name' => $tier_name,
                'discount_percentage' => $discount_percentage
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to add tier.', 'company-accounts-manager')));
        }
    }
    
    /**
     * AJAX handler for updating a tier
     */
    public static function ajax_update_tier() {
        check_ajax_referer('cam_tier_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'company-accounts-manager')));
        }
        
        $tier_id = isset($_POST['tier_id']) ? intval($_POST['tier_id']) : 0;
        $tier_name = isset($_POST['tier_name']) ? sanitize_text_field($_POST['tier_name']) : '';
        $discount_percentage = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
        
        if (empty($tier_id)) {
            wp_send_json_error(array('message' => __('Tier ID is required.', 'company-accounts-manager')));
        }
        
        if (empty($tier_name)) {
            wp_send_json_error(array('message' => __('Tier name is required.', 'company-accounts-manager')));
        }
        
        if ($discount_percentage < 0 || $discount_percentage > 100) {
            wp_send_json_error(array('message' => __('Discount percentage must be between 0 and 100.', 'company-accounts-manager')));
        }
        
        $success = self::update_tier($tier_id, $tier_name, $discount_percentage);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Tier updated successfully.', 'company-accounts-manager'),
                'tier_id' => $tier_id,
                'tier_name' => $tier_name,
                'discount_percentage' => $discount_percentage
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update tier.', 'company-accounts-manager')));
        }
    }
    
    /**
     * AJAX handler for deleting a tier
     */
    public static function ajax_delete_tier() {
        check_ajax_referer('cam_tier_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'company-accounts-manager')));
        }
        
        $tier_id = isset($_POST['tier_id']) ? intval($_POST['tier_id']) : 0;
        
        if (empty($tier_id)) {
            wp_send_json_error(array('message' => __('Tier ID is required.', 'company-accounts-manager')));
        }
        
        $success = self::delete_tier($tier_id);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Tier deleted successfully.', 'company-accounts-manager'),
                'tier_id' => $tier_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete tier.', 'company-accounts-manager')));
        }
    }
    
    /**
     * AJAX handler for assigning a tier to a company
     */
    public static function ajax_assign_tier() {
        check_ajax_referer('cam_tier_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'company-accounts-manager')));
        }
        
        $company_id = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
        $tier_id = isset($_POST['tier_id']) ? intval($_POST['tier_id']) : null;
        
        if (empty($company_id)) {
            wp_send_json_error(array('message' => __('Company ID is required.', 'company-accounts-manager')));
        }
        
        $success = self::assign_tier_to_company($company_id, $tier_id);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Tier assigned successfully.', 'company-accounts-manager'),
                'company_id' => $company_id,
                'tier_id' => $tier_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to assign tier.', 'company-accounts-manager')));
        }
    }
    
    /**
     * Create the discount tiers table
     */
    public static function create_tiers_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        // Discount tiers table
        $sql_tiers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_discount_tiers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tier_name varchar(255) NOT NULL,
            discount_percentage decimal(5,2) NOT NULL DEFAULT 0,
            created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tiers);
        
        // Log the creation attempt
        error_log('CAM: Tiers table creation attempted');
        
        // We'll skip the column addition here - it will be handled by the main installation process
    }
} 