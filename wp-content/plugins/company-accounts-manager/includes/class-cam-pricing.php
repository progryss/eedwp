<?php
/**
 * Handles pricing and discount functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Pricing {
    /**
     * Initialize pricing functionality
     */
    public static function init() {
        // Add hooks for price modification - only apply to the actual price, not regular price
        add_filter('woocommerce_product_get_price', array(__CLASS__, 'apply_tier_discount'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array(__CLASS__, 'apply_tier_discount'), 10, 2);
        
        // Add hooks for displaying original price with strikethrough
        add_filter('woocommerce_get_price_html', array(__CLASS__, 'price_html_with_original'), 10, 2);
        add_filter('woocommerce_cart_item_price', array(__CLASS__, 'cart_price_html_with_original'), 10, 3);
        
        // Add hook for variable product price HTML
        add_filter('woocommerce_variable_price_html', array(__CLASS__, 'variable_price_html_with_discount'), 10, 2);
        
        // Add hook for variation price HTML (when a specific variation is selected)
        add_filter('woocommerce_variation_price_html', array(__CLASS__, 'price_html_with_original'), 10, 2);
        add_filter('woocommerce_variation_sale_price_html', array(__CLASS__, 'price_html_with_original'), 10, 2);
        
        // Add CSS for discount badge
        add_action('wp_head', array(__CLASS__, 'add_discount_badge_css'));
    }
    
    /**
     * Apply tier discount to product price
     */
    public static function apply_tier_discount($price, $product) {
        // Skip if no price or in admin
        if (empty($price) || is_admin()) {
            return $price;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $user_id = get_current_user_id();
            error_log('CAM Pricing Debug: ==================== START ====================');
            error_log('CAM Pricing Debug: Applying tier discount for user: ' . $user_id);
            
            $user = get_userdata($user_id);
            if ($user) {
                error_log('CAM Pricing Debug: User email: ' . $user->user_email);
                error_log('CAM Pricing Debug: User roles: ' . implode(', ', (array) $user->roles));
            }
            
            error_log('CAM Pricing Debug: Is company admin: ' . (CAM_Roles::is_company_admin($user_id) ? 'Yes' : 'No'));
            error_log('CAM Pricing Debug: Is child account: ' . (CAM_Roles::is_child_account($user_id) ? 'Yes' : 'No'));
        }
        
        $tier = null;
        $user_id = get_current_user_id();
        
        // Get company ID directly from database
        $company_id = null;
        global $wpdb;
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            $company_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing Debug: Company admin - Company ID query: ' . $wpdb->last_query);
                error_log('CAM Pricing Debug: Company admin - Company ID result: ' . ($company_id ? $company_id : 'None'));
            }
        }
        // Check if user is in child_accounts table
        else {
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing Debug: Child account query executed');
                
                if ($child_account) {
                    error_log('CAM Pricing Debug: Child account found - ID: ' . $child_account->id);
                    error_log('CAM Pricing Debug: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM Pricing Debug: Child account status: ' . ($child_account->status ?? 'NULL'));
                    
                    // Check if account is suspended
                    if (isset($child_account->status) && $child_account->status === 'suspended') {
                        error_log('CAM Pricing Debug: Child account is suspended - No discount will be applied');
                        return $price; // Return original price without discount
                    }
                } else {
                    error_log('CAM Pricing Debug: No child account record found for user ID: ' . $user_id);
                    
                    // Check if user has company_child role but no record
                    if ($user && in_array('company_child', (array) $user->roles)) {
                        error_log('CAM Pricing Debug: User has company_child role but no record in child_accounts table');
                    }
                }
            }
            
            if ($child_account) {
                // Skip discount if account is suspended
                if (isset($child_account->status) && $child_account->status === 'suspended') {
                    return $price;
                }
                
                $company_id = $child_account->company_id;
            }
        }
        
        // Get tier if company ID is found
        if ($company_id) {
            $tier = CAM_Tiers::get_company_tier($company_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($tier) {
                    error_log('CAM Pricing Debug: Tier found - ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%');
                } else {
                    error_log('CAM Pricing Debug: No tier found for company ID: ' . $company_id);
                    
                    // Check if company has a tier assigned
                    $company_tier_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT tier_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                            $company_id
                        )
                    );
                    error_log('CAM Pricing Debug: Company tier_id from database: ' . ($company_tier_id ? $company_tier_id : 'None'));
                    
                    if ($company_tier_id) {
                        // Check if tier exists in discount_tiers table
                        $tier_exists = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}cam_discount_tiers WHERE id = %d",
                                $company_tier_id
                            )
                        );
                        error_log('CAM Pricing Debug: Tier exists in discount_tiers table: ' . ($tier_exists ? 'Yes' : 'No'));
                    }
                }
            }
        }
        
        // Debug tier information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($tier) {
                error_log('CAM Pricing Debug: Final tier found - ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%');
            } else {
                error_log('CAM Pricing Debug: No tier found for user');
            }
            
            error_log('CAM Pricing Debug: Product ID: ' . $product->get_id());
            error_log('CAM Pricing Debug: Original price: ' . $price);
            error_log('CAM Pricing Debug: ==================== END ====================');
        }
        
        if (!$tier) {
            return $price;
        }
        
        // Calculate discounted price
        $discount = $price * ($tier->discount_percentage / 100);
        $discounted_price = $price - $discount;
        
        // Debug price information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Pricing Debug: Discount calculation:');
            error_log('CAM Pricing Debug: - Original price: ' . $price);
            error_log('CAM Pricing Debug: - Discount amount: ' . $discount);
            error_log('CAM Pricing Debug: - Final price: ' . $discounted_price);
        }
        
        return $discounted_price;
    }
    
    /**
     * Modify the price HTML to show original price with strikethrough
     */
    public static function price_html_with_original($price_html, $product) {
        // Skip if in admin
        if (is_admin()) {
            return $price_html;
        }
        
        // Get tier using the same approach as in apply_tier_discount
        $tier = null;
        $user_id = get_current_user_id();
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            global $wpdb;
            $company_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($company_id) {
                $tier = CAM_Tiers::get_company_tier($company_id);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing HTML: Company ID for admin from direct query: ' . ($company_id ? $company_id : 'None'));
                error_log('CAM Pricing HTML: Tier for admin: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Check if user is a child account
        else if (CAM_Roles::is_child_account($user_id)) {
            global $wpdb;
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($child_account) {
                // Skip discount if account is suspended
                if (isset($child_account->status) && $child_account->status === 'suspended') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Pricing HTML: Child account is suspended - No discount will be applied');
                    }
                    return $price_html;
                }
                
                $company_id = $child_account->company_id;
                if ($company_id) {
                    $tier = CAM_Tiers::get_company_tier($company_id);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing HTML: Child account query executed');
                if ($child_account) {
                    error_log('CAM Pricing HTML: Child account found - ID: ' . $child_account->id);
                    error_log('CAM Pricing HTML: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM Pricing HTML: Child account status: ' . $child_account->status);
                } else {
                    error_log('CAM Pricing HTML: No child account record found');
                }
                error_log('CAM Pricing HTML: Tier for child account: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Fallback to the general method
        else {
        $tier = CAM_Tiers::get_current_user_tier();
        }
        
        if (!$tier) {
            return $price_html;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Pricing: Modifying price HTML for product ID: ' . $product->get_id());
            error_log('CAM Pricing: Regular price: ' . $product->get_regular_price());
            error_log('CAM Pricing: Sale price: ' . $product->get_sale_price());
            error_log('CAM Pricing: Is on sale: ' . ($product->is_on_sale() ? 'Yes' : 'No'));
        }
        
        // Get regular price
        $regular_price = $product->get_regular_price();
        if (empty($regular_price)) {
            return $price_html;
        }
        
        // Handle products that are already on sale differently
        if ($product->is_on_sale()) {
            // Get the original sale price
            $sale_price = $product->get_sale_price();
            
            // Calculate tier discount on the sale price
            $discount = $sale_price * ($tier->discount_percentage / 100);
            $final_price = $sale_price - $discount;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing: Product on sale - Regular price: ' . $regular_price);
                error_log('CAM Pricing: Product on sale - Sale price: ' . $sale_price);
                error_log('CAM Pricing: Product on sale - Tier discount: ' . $discount);
                error_log('CAM Pricing: Product on sale - Final price: ' . $final_price);
            }
            
            // Format prices
            $regular_price_html = wc_price($regular_price);
            $sale_price_html = wc_price($sale_price);
            $final_price_html = wc_price($final_price);
            
            // Create HTML that shows: Regular Price → Sale Price → Final Price with Tier Discount
            $new_price_html = '<del>' . $regular_price_html . '</del> ';
            $new_price_html .= '<del class="sale-price">' . $sale_price_html . '</del> ';
            $new_price_html .= '<ins>' . $final_price_html . '</ins>';
            $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
            
            return $new_price_html;
        } else {
            // Regular product (not on sale)
            // Calculate tier discount on the regular price
            $discount = $regular_price * ($tier->discount_percentage / 100);
            $discounted_price = $regular_price - $discount;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing: Regular product - Price: ' . $regular_price);
                error_log('CAM Pricing: Regular product - Tier discount: ' . $discount);
                error_log('CAM Pricing: Regular product - Discounted price: ' . $discounted_price);
            }
        
        // Format prices
        $regular_price_html = wc_price($regular_price);
        $discounted_price_html = wc_price($discounted_price);
        
        // Create new price HTML with strikethrough
        $new_price_html = '<del>' . $regular_price_html . '</del> <ins>' . $discounted_price_html . '</ins>';
        $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
        
        return $new_price_html;
        }
    }
    
    /**
     * Modify the cart item price HTML to show original price with strikethrough
     */
    public static function cart_price_html_with_original($price_html, $cart_item, $cart_item_key) {
        // Skip if in admin
        if (is_admin()) {
            return $price_html;
        }
        
        // Get tier using the same approach as in apply_tier_discount
        $tier = null;
        $user_id = get_current_user_id();
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            global $wpdb;
            $company_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($company_id) {
                $tier = CAM_Tiers::get_company_tier($company_id);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Cart Pricing: Company ID for admin from direct query: ' . ($company_id ? $company_id : 'None'));
                error_log('CAM Cart Pricing: Tier for admin: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Check if user is a child account
        else if (CAM_Roles::is_child_account($user_id)) {
            global $wpdb;
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($child_account) {
                // Skip discount if account is suspended
                if (isset($child_account->status) && $child_account->status === 'suspended') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Cart Pricing: Child account is suspended - No discount will be applied');
                    }
                    return $price_html;
                }
                
                $company_id = $child_account->company_id;
                if ($company_id) {
                    $tier = CAM_Tiers::get_company_tier($company_id);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Cart Pricing: Child account query executed');
                if ($child_account) {
                    error_log('CAM Cart Pricing: Child account found - ID: ' . $child_account->id);
                    error_log('CAM Cart Pricing: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM Cart Pricing: Child account status: ' . $child_account->status);
                } else {
                    error_log('CAM Cart Pricing: No child account record found');
                }
                error_log('CAM Cart Pricing: Tier for child account: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Fallback to the general method
        else {
        $tier = CAM_Tiers::get_current_user_tier();
        }
        
        if (!$tier) {
            return $price_html;
        }
        
        $product = $cart_item['data'];
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Pricing: Modifying cart price HTML for product ID: ' . $product->get_id());
            error_log('CAM Pricing: Regular price: ' . $product->get_regular_price());
            error_log('CAM Pricing: Sale price: ' . $product->get_sale_price());
            error_log('CAM Pricing: Is on sale: ' . ($product->is_on_sale() ? 'Yes' : 'No'));
        }
        
        // Get regular price
        $regular_price = $product->get_regular_price();
        if (empty($regular_price)) {
            return $price_html;
        }
        
        // Handle products that are already on sale differently
        if ($product->is_on_sale()) {
            // Get the original sale price
            $sale_price = $product->get_sale_price();
            
            // Calculate tier discount on the sale price
            $discount = $sale_price * ($tier->discount_percentage / 100);
            $final_price = $sale_price - $discount;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing: Cart - Product on sale - Regular price: ' . $regular_price);
                error_log('CAM Pricing: Cart - Product on sale - Sale price: ' . $sale_price);
                error_log('CAM Pricing: Cart - Product on sale - Tier discount: ' . $discount);
                error_log('CAM Pricing: Cart - Product on sale - Final price: ' . $final_price);
            }
            
            // Format prices
            $regular_price_html = wc_price($regular_price);
            $sale_price_html = wc_price($sale_price);
            $final_price_html = wc_price($final_price);
            
            // Create HTML that shows: Regular Price → Sale Price → Final Price with Tier Discount
            $new_price_html = '<del>' . $regular_price_html . '</del><br>';
            $new_price_html .= '<del class="sale-price">' . $sale_price_html . '</del><br>';
            $new_price_html .= '<ins>' . $final_price_html . '</ins>';
            
            return $new_price_html;
        } else {
            // Regular product (not on sale)
            // Calculate tier discount on the regular price
            $discount = $regular_price * ($tier->discount_percentage / 100);
            $discounted_price = $regular_price - $discount;
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Pricing: Cart - Regular product - Price: ' . $regular_price);
                error_log('CAM Pricing: Cart - Regular product - Tier discount: ' . $discount);
                error_log('CAM Pricing: Cart - Regular product - Discounted price: ' . $discounted_price);
            }
        
        // Format prices
        $regular_price_html = wc_price($regular_price);
        $discounted_price_html = wc_price($discounted_price);
        
        // Create new price HTML with strikethrough
            $new_price_html = '<del>' . $regular_price_html . '</del><br>';
            $new_price_html .= '<ins>' . $discounted_price_html . '</ins>';
            
            return $new_price_html;
        }
    }
    
    /**
     * Modify the variable product price HTML to show discount
     */
    public static function variable_price_html_with_discount($price_html, $product) {
        // Skip if in admin
        if (is_admin()) {
            return $price_html;
        }
        
        // Get tier using the same approach as in apply_tier_discount
        $tier = null;
        $user_id = get_current_user_id();
        
        // Check if user is a company admin
        if (CAM_Roles::is_company_admin($user_id)) {
            global $wpdb;
            $company_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}cam_companies WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($company_id) {
                $tier = CAM_Tiers::get_company_tier($company_id);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Variable Pricing: Company ID for admin from direct query: ' . ($company_id ? $company_id : 'None'));
                error_log('CAM Variable Pricing: Tier for admin: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Check if user is a child account
        else if (CAM_Roles::is_child_account($user_id)) {
            global $wpdb;
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if ($child_account) {
                // Skip discount if account is suspended
                if (isset($child_account->status) && $child_account->status === 'suspended') {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('CAM Variable Pricing: Child account is suspended - No discount will be applied');
                    }
                    return $price_html;
                }
                
                $company_id = $child_account->company_id;
                if ($company_id) {
                    $tier = CAM_Tiers::get_company_tier($company_id);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Variable Pricing: Child account query executed');
                if ($child_account) {
                    error_log('CAM Variable Pricing: Child account found - ID: ' . $child_account->id);
                    error_log('CAM Variable Pricing: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM Variable Pricing: Child account status: ' . $child_account->status);
                } else {
                    error_log('CAM Variable Pricing: No child account record found');
                }
                error_log('CAM Variable Pricing: Tier for child account: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        // Fallback to the general method
        else {
            $tier = CAM_Tiers::get_current_user_tier();
        }
        
        if (!$tier) {
            return $price_html;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Variable Pricing: Modifying price HTML for variable product ID: ' . $product->get_id());
        }
        
        // Get min and max prices
        $prices = $product->get_variation_prices(true);
        
        if (empty($prices['price'])) {
            return $price_html;
        }
        
        $min_price = current($prices['price']);
        $max_price = end($prices['price']);
        
        $min_regular_price = current($prices['regular_price']);
        $max_regular_price = end($prices['regular_price']);
        
        // Check if product is on sale
        $is_on_sale = $product->is_on_sale();
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM Variable Pricing: Min price: ' . $min_price . ', Max price: ' . $max_price);
            error_log('CAM Variable Pricing: Min regular price: ' . $min_regular_price . ', Max regular price: ' . $max_regular_price);
            error_log('CAM Variable Pricing: Is on sale: ' . ($is_on_sale ? 'Yes' : 'No'));
        }
        
        // Handle products that are on sale differently
        if ($is_on_sale) {
            // Apply tier discount to min and max sale prices
            $min_discounted_price = $min_price - ($min_price * ($tier->discount_percentage / 100));
            $max_discounted_price = $max_price - ($max_price * ($tier->discount_percentage / 100));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Variable Pricing: Min sale price: ' . $min_price);
                error_log('CAM Variable Pricing: Max sale price: ' . $max_price);
                error_log('CAM Variable Pricing: Min discounted price: ' . $min_discounted_price);
                error_log('CAM Variable Pricing: Max discounted price: ' . $max_discounted_price);
            }
            
            // Format the price range
            if ($min_regular_price !== $max_regular_price) {
                $regular_price_html = wc_format_price_range($min_regular_price, $max_regular_price);
                $sale_price_html = wc_format_price_range($min_price, $max_price);
                $discounted_price_html = wc_format_price_range($min_discounted_price, $max_discounted_price);
                
                $new_price_html = '<del>' . $regular_price_html . '</del> ';
                $new_price_html .= '<del class="sale-price">' . $sale_price_html . '</del> ';
                $new_price_html .= '<ins>' . $discounted_price_html . '</ins>';
                $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
            } else {
                $regular_price_html = wc_price($min_regular_price);
                $sale_price_html = wc_price($min_price);
                $discounted_price_html = wc_price($min_discounted_price);
                
                $new_price_html = '<del>' . $regular_price_html . '</del> ';
                $new_price_html .= '<del class="sale-price">' . $sale_price_html . '</del> ';
                $new_price_html .= '<ins>' . $discounted_price_html . '</ins>';
                $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
            }
        } else {
            // Regular product (not on sale)
            // Apply tier discount to min and max prices
            $min_discounted_price = $min_price - ($min_price * ($tier->discount_percentage / 100));
            $max_discounted_price = $max_price - ($max_price * ($tier->discount_percentage / 100));
            
            // Debug information
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM Variable Pricing: Min price: ' . $min_price . ', Max price: ' . $max_price);
                error_log('CAM Variable Pricing: Min discounted price: ' . $min_discounted_price . ', Max discounted price: ' . $max_discounted_price);
            }
            
            // Format the price range
            if ($min_price !== $max_price) {
                $original_price_html = wc_format_price_range($min_price, $max_price);
                $discounted_price_html = wc_format_price_range($min_discounted_price, $max_discounted_price);
                
                $new_price_html = '<del>' . $original_price_html . '</del> <ins>' . $discounted_price_html . '</ins>';
                $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
            } else {
                $original_price_html = wc_price($min_price);
                $discounted_price_html = wc_price($min_discounted_price);
                
                $new_price_html = '<del>' . $original_price_html . '</del> <ins>' . $discounted_price_html . '</ins>';
                $new_price_html .= ' <span class="cam-discount-badge">(' . $tier->discount_percentage . '% ' . __('Discount', 'company-accounts-manager') . ')</span>';
            }
        }
        
        return $new_price_html;
    }
    
    /**
     * Add CSS for discount badge
     */
    public static function add_discount_badge_css() {
        // Get tier using the same approach as in apply_tier_discount
        $tier = null;
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('CAM CSS Debug: ==================== START ====================');
            error_log('CAM CSS Debug: Adding discount badge CSS for user: ' . $user_id);
            
            $user = get_userdata($user_id);
            if ($user) {
                error_log('CAM CSS Debug: User email: ' . $user->user_email);
                error_log('CAM CSS Debug: User roles: ' . implode(', ', (array) $user->roles));
                
                // Special debug for hr2@progryss.com
                if ($user->user_email === 'hr2@progryss.com') {
                    error_log('CAM CSS Debug: SPECIAL DEBUG FOR hr2@progryss.com');
                    global $wpdb;
                    
                    // Check if user exists in child_accounts table
                    $child_account = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                            $user_id
                        )
                    );
                    
                    if ($child_account) {
                        error_log('CAM CSS Debug: Child account record found - ID: ' . $child_account->id);
                        error_log('CAM CSS Debug: Child account company ID: ' . $child_account->company_id);
                        error_log('CAM CSS Debug: Child account status: ' . $child_account->status);
                        
                        // Get company details
                        $company = $wpdb->get_row(
                            $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                                $child_account->company_id
                            )
                        );
                        
                        if ($company) {
                            error_log('CAM CSS Debug: Company found - Name: ' . $company->company_name);
                            error_log('CAM CSS Debug: Company tier ID: ' . ($company->tier_id ? $company->tier_id : 'None'));
                            
                            if ($company->tier_id) {
                                // Get tier details
                                $tier_details = $wpdb->get_row(
                                    $wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}cam_discount_tiers WHERE id = %d",
                                        $company->tier_id
                                    )
                                );
                                
                                if ($tier_details) {
                                    error_log('CAM CSS Debug: Tier found - Name: ' . $tier_details->tier_name);
                                    error_log('CAM CSS Debug: Tier discount percentage: ' . $tier_details->discount_percentage . '%');
                                } else {
                                    error_log('CAM CSS Debug: No tier found with ID: ' . $company->tier_id);
                                }
                            }
                        } else {
                            error_log('CAM CSS Debug: No company found with ID: ' . $child_account->company_id);
                        }
                    } else {
                        error_log('CAM CSS Debug: No child account record found for hr2@progryss.com');
                    }
                }
            }
            
            error_log('CAM CSS Debug: Is company admin: ' . (CAM_Roles::is_company_admin($user_id) ? 'Yes' : 'No'));
            error_log('CAM CSS Debug: Is child account: ' . (CAM_Roles::is_child_account($user_id) ? 'Yes' : 'No'));
        }
        
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
                error_log('CAM CSS Debug: Company admin - Company ID query: ' . $wpdb->last_query);
                error_log('CAM CSS Debug: Company admin - Company ID result: ' . ($company_id ? $company_id : 'None'));
            }
            
            if ($company_id) {
                $tier = CAM_Tiers::get_company_tier($company_id);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if ($tier) {
                        error_log('CAM CSS Debug: Company admin - Found tier - ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%');
                    } else {
                        // Check if company has a tier assigned
                        $company_tier_id = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT tier_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                                $company_id
                            )
                        );
                        error_log('CAM CSS Debug: Company admin - Company tier_id from database: ' . ($company_tier_id ? $company_tier_id : 'None'));
                    }
                }
            }
        }
        // Check if user is a child account
        else if (CAM_Roles::is_child_account($user_id)) {
            global $wpdb;
            
            // Get child account record
            $child_account = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                    $user_id
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM CSS Debug: Child account - Last query: ' . $wpdb->last_query);
                
                if ($child_account) {
                    error_log('CAM CSS Debug: Child account found - ID: ' . $child_account->id);
                    error_log('CAM CSS Debug: Child account company ID: ' . $child_account->company_id);
                    error_log('CAM CSS Debug: Child account status: ' . $child_account->status);
                    
                    // Check if account is suspended
                    if (isset($child_account->status) && $child_account->status === 'suspended') {
                        error_log('CAM CSS Debug: Child account is suspended - No discount CSS will be applied');
                        return; // Exit without adding CSS
                    }
                } else {
                    error_log('CAM CSS Debug: No child account record found for this user');
                }
            }
            
            if ($child_account) {
                // Skip CSS if account is suspended
                if (isset($child_account->status) && $child_account->status === 'suspended') {
                    return;
                }
                
                $company_id = $child_account->company_id;
                
                if ($company_id) {
                    $tier = CAM_Tiers::get_company_tier($company_id);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        if ($tier) {
                            error_log('CAM CSS Debug: Child account - Found tier - ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%');
                        } else {
                            // Check if company has a tier assigned
                            $company_tier_id = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT tier_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
                                    $company_id
                                )
                            );
                            error_log('CAM CSS Debug: Child account - Company tier_id from database: ' . ($company_tier_id ? $company_tier_id : 'None'));
                            
                            if ($company_tier_id) {
                                // Check if tier exists in discount_tiers table
                                $tier_exists = $wpdb->get_row(
                                    $wpdb->prepare(
                                        "SELECT * FROM {$wpdb->prefix}cam_discount_tiers WHERE id = %d",
                                        $company_tier_id
                                    )
                                );
                                error_log('CAM CSS Debug: Child account - Tier exists in discount_tiers table: ' . ($tier_exists ? 'Yes' : 'No'));
                            }
                        }
                    }
                }
            }
        }
        // Fallback to the general method
        else {
            $tier = CAM_Tiers::get_current_user_tier();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('CAM CSS Debug: Using fallback get_current_user_tier method');
                error_log('CAM CSS Debug: Tier result: ' . ($tier ? 'Found (ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%)' : 'Not found'));
            }
        }
        
        // Debug tier information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($tier) {
                error_log('CAM CSS Debug: Final tier found - ID: ' . $tier->id . ', Discount: ' . $tier->discount_percentage . '%');
            } else {
                error_log('CAM CSS Debug: No tier found for user');
            }
            error_log('CAM CSS Debug: ==================== END ====================');
        }
        
        // Only add CSS if user has a tier
        if (!$tier) {
            return;
        }
        
        ?>
        <style type="text/css">
            .cam-discount-badge {
                display: inline-block;
                background-color: #f8d7da;
                color: #721c24;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.8em;
                margin-left: 5px;
                font-weight: bold;
            }
            
            .woocommerce-Price-amount {
                display: inline-block;
            }
            
            del .woocommerce-Price-amount {
                color: #999;
                text-decoration: line-through;
                font-weight: normal;
            }
            
            del.sale-price .woocommerce-Price-amount {
                color: #777;
                font-size: 0.95em;
            }
            
            ins .woocommerce-Price-amount {
                color: #721c24;
                font-weight: bold;
            }
            
            ins {
                text-decoration: none;
            }
            
            /* Additional styles for clarity */
            .price del {
                opacity: 0.8;
                margin-right: 5px;
            }
            
            .price del.sale-price {
                opacity: 0.9;
                font-style: italic;
                position: relative;
            }
            
            .price ins {
                background: transparent;
            }
            
            /* Make sure cart prices are consistent */
            .woocommerce-cart-form .product-price del,
            .woocommerce-cart-form .product-subtotal del {
                display: block;
                font-size: 0.9em;
            }
            
            .woocommerce-cart-form .product-price ins,
            .woocommerce-cart-form .product-subtotal ins {
                display: block;
                margin-top: 2px;
            }
            
            /* Style for products on sale */
            .product.sale .price del {
                display: block;
            }
            
            .product.sale .price del.sale-price {
                display: block;
                margin-top: 2px;
                margin-bottom: 2px;
            }
            
            .product.sale .price ins {
                display: block;
                margin-top: 2px;
            }
            
            /* Add some spacing between prices on product pages */
            .product .price del,
            .product .price del.sale-price,
            .product .price ins {
                margin-bottom: 3px;
            }
            
            /* Make the final price stand out more */
            .product .price ins {
                font-weight: bold;
                font-size: 1.05em;
            }
            
            /* Variable product styles */
            .variable-item:not(.radio-variable-item) {
                margin-bottom: 5px;
            }
            
            .woocommerce-variation-price {
                margin-bottom: 15px;
            }
            
            /* Ensure discount badge is visible on variable products */
            .woocommerce-variation-price .cam-discount-badge {
                display: inline-block;
                margin-top: 5px;
            }
            
            /* Fix for variable product price display on category pages */
            .products .product.product-type-variable .price del,
            .products .product.product-type-variable .price ins {
                display: inline-block;
            }
        </style>
        <?php
    }
} 