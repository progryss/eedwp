<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get company details
$company = isset($company) ? $company : null;
if (!$company) {
    echo '<div class="notice notice-error"><p>' . __('Company not found.', 'company-accounts-manager') . '</p></div>';
    return;
}

// Get company admin details
$admin_user = get_userdata($company->user_id);
if (!$admin_user) {
    echo '<div class="notice notice-error"><p>' . __('Company admin not found.', 'company-accounts-manager') . '</p></div>';
    return;
}

// Get child accounts
$child_accounts = CAM_Roles::get_company_child_accounts($company->id);

// Get date filter parameters
$current_month = date('m');
$current_year = date('Y');
$current_day = date('d');

$filter_month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// Calculate start and end dates for filtering
$start_date = $filter_year . '-' . str_pad($filter_month, 2, '0', STR_PAD_LEFT) . '-01';
$end_date = date('Y-m-d', strtotime('+1 month', strtotime($start_date)) - 1);

// If current month, limit to current day
if ($filter_month == $current_month && $filter_year == $current_year) {
    $end_date = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-' . $current_day;
}

// Get company orders within date range
$company_orders = CAM_Order_Manager::get_company_orders_by_date($company->id, $start_date, $end_date);

// Get company tier
$company_tier = CAM_Tiers::get_company_tier($company->id);
$all_tiers = CAM_Tiers::get_all_tiers();

// Calculate totals
$total_orders = count($company_orders);
$total_spent = 0;
foreach ($company_orders as $order) {
    $total_spent += $order->get_total();
}

// Group orders by user
$user_orders = array();
$user_orders[$admin_user->ID] = array(
    'user' => $admin_user,
    'orders' => array(),
    'total_orders' => 0,
    'total_spent' => 0
);

foreach ($child_accounts as $child) {
    $user_orders[$child->user_id] = array(
        'user' => get_userdata($child->user_id),
        'orders' => array(),
        'total_orders' => 0,
        'total_spent' => 0
    );
}

foreach ($company_orders as $order) {
    $user_id = $order->get_customer_id();
    if (isset($user_orders[$user_id])) {
        $user_orders[$user_id]['orders'][] = $order;
        $user_orders[$user_id]['total_orders']++;
        $user_orders[$user_id]['total_spent'] += $order->get_total();
    }
}
?>

<div class="wrap">
    <h1>
        <?php echo esc_html($company->company_name); ?> - <?php _e('Company Details', 'company-accounts-manager'); ?>
        <?php if ($company->status === 'active') : ?>
            <button type="button" class="button suspend-entire-company" data-company-id="<?php echo esc_attr($company->id); ?>" data-action="suspend" title="<?php _e('Suspend the entire company including admin and all child accounts', 'company-accounts-manager'); ?>">
                <?php _e('Suspend Entire Company', 'company-accounts-manager'); ?>
            </button>
        <?php elseif ($company->status === 'suspended') : ?>
            <button type="button" class="button suspend-entire-company" data-company-id="<?php echo esc_attr($company->id); ?>" data-action="activate" title="<?php _e('Activate the entire company including admin and all child accounts', 'company-accounts-manager'); ?>">
                <?php _e('Activate Entire Company', 'company-accounts-manager'); ?>
            </button>
            <span class="cam-status cam-status-suspended" style="vertical-align: middle;">
                <?php _e('Company Suspended', 'company-accounts-manager'); ?>
            </span>
        <?php endif; ?>
    </h1>
    
    <div class="cam-company-details">
        <!-- Company Information -->
        <div class="cam-section">
            <h2><?php _e('Company Information', 'company-accounts-manager'); ?></h2>
            <table class="widefat fixed">
                <tbody>
                    <tr>
                        <th width="200"><?php _e('Company Name', 'company-accounts-manager'); ?></th>
                        <td><?php echo esc_html($company->company_name); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                        <td>
                            <span class="cam-status cam-status-<?php echo esc_attr($company->status); ?>">
                                <?php echo esc_html(ucfirst($company->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Registration Date', 'company-accounts-manager'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($company->registration_date))); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Industry', 'company-accounts-manager'); ?></th>
                        <td><?php echo esc_html($company->industry); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Discount Tier', 'company-accounts-manager'); ?></th>
                        <td>
                            <form id="assign-tier-form">
                                <select name="tier_id" id="tier_id">
                                    <option value=""><?php _e('No Tier (No Discount)', 'company-accounts-manager'); ?></option>
                                    <?php foreach ($all_tiers as $tier) : ?>
                                        <option value="<?php echo esc_attr($tier->id); ?>" <?php selected($company_tier && $company_tier->id == $tier->id); ?>>
                                            <?php echo esc_html($tier->tier_name); ?> (<?php echo esc_html($tier->discount_percentage); ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button"><?php _e('Assign Tier', 'company-accounts-manager'); ?></button>
                                <span class="spinner" style="float: none; margin-top: 0;"></span>
                                <span class="tier-message"></span>
                                <?php wp_nonce_field('cam_tier_nonce', 'cam_tier_nonce'); ?>
                                <input type="hidden" name="company_id" value="<?php echo esc_attr($company->id); ?>">
                            </form>
                            <p class="description">
                                <?php _e('Assign a discount tier to this company. All users in this company will see discounted prices based on the tier percentage.', 'company-accounts-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php if (!empty($company->company_info)) : ?>
                    <tr>
                        <th><?php _e('Additional Information', 'company-accounts-manager'); ?></th>
                        <td><?php echo nl2br(esc_html($company->company_info)); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Date Filter -->
        <div class="cam-section">
            <h2><?php _e('Order Statistics', 'company-accounts-manager'); ?></h2>
            
            <form method="get" class="cam-date-filter">
                <input type="hidden" name="page" value="cam-company-details">
                <input type="hidden" name="company_id" value="<?php echo esc_attr($company->id); ?>">
                
                <div class="cam-filter-controls">
                    <label>
                        <?php _e('Month:', 'company-accounts-manager'); ?>
                        <select name="month">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php selected($filter_month, $i); ?>>
                                    <?php echo date_i18n('F', strtotime('2023-' . str_pad($i, 2, '0', STR_PAD_LEFT) . '-01')); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    
                    <label>
                        <?php _e('Year:', 'company-accounts-manager'); ?>
                        <select name="year">
                            <?php for ($i = intval(date('Y')) - 2; $i <= intval(date('Y')); $i++) : ?>
                                <option value="<?php echo $i; ?>" <?php selected($filter_year, $i); ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    
                    <button type="submit" class="button"><?php _e('Filter', 'company-accounts-manager'); ?></button>
                </div>
                
                <p class="description">
                    <?php printf(
                        __('Showing orders from %s to %s', 'company-accounts-manager'),
                        date_i18n(get_option('date_format'), strtotime($start_date)),
                        date_i18n(get_option('date_format'), strtotime($end_date))
                    ); ?>
                </p>
            </form>
            
            <!-- Company Summary -->
            <div class="cam-summary-box">
                <h3><?php _e('Company Summary', 'company-accounts-manager'); ?></h3>
                <div class="cam-summary-stats">
                    <div class="cam-stat">
                        <span class="cam-stat-label"><?php _e('Total Orders', 'company-accounts-manager'); ?></span>
                        <span class="cam-stat-value"><?php echo esc_html($total_orders); ?></span>
                    </div>
                    <div class="cam-stat">
                        <span class="cam-stat-label"><?php _e('Total Spent', 'company-accounts-manager'); ?></span>
                        <span class="cam-stat-value"><?php echo wc_price($total_spent); ?></span>
                    </div>
                    <div class="cam-stat">
                        <span class="cam-stat-label"><?php _e('Average Order', 'company-accounts-manager'); ?></span>
                        <span class="cam-stat-value">
                            <?php echo $total_orders > 0 ? wc_price($total_spent / $total_orders) : wc_price(0); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Accounts -->
        <div class="cam-section">
            <h2><?php _e('User Accounts', 'company-accounts-manager'); ?></h2>
            
            <table class="widefat striped cam-users-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Email', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Created', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Orders', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Spent', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Actions', 'company-accounts-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Company Admin -->
                    <tr class="cam-admin-row">
                        <td>
                            <strong><?php echo esc_html($admin_user->display_name); ?></strong>
                            <span class="cam-role-badge"><?php _e('Admin', 'company-accounts-manager'); ?></span>
                        </td>
                        <td><?php echo esc_html($admin_user->user_email); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($company->registration_date))); ?></td>
                        <td><?php echo esc_html($user_orders[$admin_user->ID]['total_orders']); ?></td>
                        <td><?php echo wc_price($user_orders[$admin_user->ID]['total_spent']); ?></td>
                        <td>
                            <?php if (!empty($user_orders[$admin_user->ID]['orders'])) : ?>
                                <button type="button" class="button button-small toggle-orders" data-user="admin">
                                    <?php _e('View Orders', 'company-accounts-manager'); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($company->admin_status === 'active') : ?>
                                <button type="button" class="button button-small suspend-company-admin" data-user-id="<?php echo esc_attr($admin_user->ID); ?>" data-action="suspend" title="<?php _e('Suspend only the company admin account', 'company-accounts-manager'); ?>">
                                    <?php _e('Suspend Admin Only', 'company-accounts-manager'); ?>
                                </button>
                            <?php elseif ($company->admin_status === 'suspended') : ?>
                                <button type="button" class="button button-small suspend-company-admin" data-user-id="<?php echo esc_attr($admin_user->ID); ?>" data-action="activate" title="<?php _e('Activate only the company admin account', 'company-accounts-manager'); ?>">
                                    <?php _e('Activate Admin Only', 'company-accounts-manager'); ?>
                                </button>
                                <span class="cam-status cam-status-suspended">
                                    <?php _e('Admin Suspended', 'company-accounts-manager'); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($company->status === 'suspended') : ?>
                                <span class="cam-status cam-status-suspended">
                                    <?php _e('Company Suspended', 'company-accounts-manager'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if (!empty($user_orders[$admin_user->ID]['orders'])) : ?>
                    <tr class="cam-orders-row admin-orders" style="display: none;">
                        <td colspan="6">
                            <div class="cam-user-orders">
                                <h4><?php _e('Recent Orders', 'company-accounts-manager'); ?></h4>
                                <table class="cam-orders-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Order', 'company-accounts-manager'); ?></th>
                                            <th><?php _e('Date', 'company-accounts-manager'); ?></th>
                                            <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                                            <th><?php _e('Total', 'company-accounts-manager'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($user_orders[$admin_user->ID]['orders'], 0, 5) as $order) : ?>
                                            <tr>
                                                <td>
                                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>">
                                                        #<?php echo esc_html($order->get_order_number()); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                                                <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                                <td><?php echo $order->get_formatted_order_total(); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Child Accounts -->
                    <?php if (!empty($child_accounts)) : 
                        foreach ($child_accounts as $index => $child) : 
                            $child_user = get_userdata($child->user_id);
                            if (!$child_user) continue;
                            
                            $first_name = get_user_meta($child->user_id, 'first_name', true);
                            $last_name = get_user_meta($child->user_id, 'last_name', true);
                            $full_name = trim("$first_name $last_name");
                            
                            if (empty($full_name)) {
                                $full_name = $child_user->display_name;
                            }
                            
                            if ($full_name === $child_user->user_email) {
                                $full_name = __('Child Account', 'company-accounts-manager') . ' #' . $child->id;
                            }
                        ?>
                            <tr class="cam-child-row">
                                <td><?php echo esc_html($full_name); ?></td>
                                <td><?php echo esc_html($child_user->user_email); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($child->created_date))); ?></td>
                                <td><?php echo esc_html($user_orders[$child->user_id]['total_orders']); ?></td>
                                <td><?php echo wc_price($user_orders[$child->user_id]['total_spent']); ?></td>
                                <td>
                                    <?php if (!empty($user_orders[$child->user_id]['orders'])) : ?>
                                        <button type="button" class="button button-small toggle-orders" data-user="child-<?php echo $index; ?>">
                                            <?php _e('View Orders', 'company-accounts-manager'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Get child account status
                                    global $wpdb;
                                    $child_status = $wpdb->get_var($wpdb->prepare(
                                        "SELECT status FROM {$wpdb->prefix}cam_child_accounts WHERE user_id = %d",
                                        $child->user_id
                                    ));
                                    
                                    // Default to active if status is not set
                                    if (empty($child_status)) {
                                        $child_status = 'active';
                                    }
                                    
                                    if ($child_status === 'active') : ?>
                                        <button type="button" class="button button-small suspend-child-account" data-user-id="<?php echo $child->user_id; ?>" data-action="suspend">
                                            <?php _e('Suspend', 'company-accounts-manager'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="button button-small suspend-child-account" data-user-id="<?php echo $child->user_id; ?>" data-action="activate">
                                            <?php _e('Activate', 'company-accounts-manager'); ?>
                                        </button>
                                        <span class="cam-status cam-status-suspended">
                                            <?php _e('Suspended', 'company-accounts-manager'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php if (!empty($user_orders[$child->user_id]['orders'])) : ?>
                            <tr class="cam-orders-row child-orders-<?php echo $index; ?>" style="display: none;">
                                <td colspan="6">
                                    <div class="cam-user-orders">
                                        <h4><?php _e('Recent Orders', 'company-accounts-manager'); ?></h4>
                                        <table class="cam-orders-table">
                                            <thead>
                                                <tr>
                                                    <th><?php _e('Order', 'company-accounts-manager'); ?></th>
                                                    <th><?php _e('Date', 'company-accounts-manager'); ?></th>
                                                    <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                                                    <th><?php _e('Total', 'company-accounts-manager'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($user_orders[$child->user_id]['orders'], 0, 5) as $order) : ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>">
                                                                #<?php echo esc_html($order->get_order_number()); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                                                        <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" class="cam-no-data"><?php _e('No child accounts found for this company.', 'company-accounts-manager'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.cam-company-details {
    margin-top: 20px;
}

.cam-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.cam-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.cam-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.cam-status-active {
    background-color: #dff0d8;
    color: #3c763d;
}

.cam-status-pending {
    background-color: #fcf8e3;
    color: #8a6d3b;
}

.cam-status-rejected {
    background-color: #f2dede;
    color: #a94442;
}

.cam-status-suspended {
    background-color: #f8d7da;
    color: #721c24;
}

.cam-date-filter {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 4px;
}

.cam-filter-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-bottom: 10px;
}

.cam-summary-box {
    margin-top: 20px;
    padding: 15px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.cam-summary-stats {
    display: flex;
    gap: 30px;
    margin-top: 10px;
}

.cam-stat {
    display: flex;
    flex-direction: column;
}

.cam-stat-label {
    font-size: 12px;
    color: #666;
}

.cam-stat-value {
    font-size: 18px;
    font-weight: 600;
    color: #0073aa;
}

.cam-user-box {
    margin-bottom: 30px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 4px;
}

.cam-user-details {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.cam-user-info {
    flex: 1;
}

.cam-user-info p {
    margin: 5px 0;
}

.cam-user-stats {
    display: flex;
    gap: 20px;
}

.cam-orders-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.cam-orders-table th,
.cam-orders-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.cam-orders-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.cam-orders-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.cam-orders-table tr:hover {
    background-color: #f1f1f1;
}

.cam-no-data {
    color: #666;
    font-style: italic;
    margin: 20px 0;
}

.cam-users-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 13px;
}

.cam-users-table th,
.cam-users-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    vertical-align: middle;
}

.cam-users-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.cam-users-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.cam-users-table tr:hover {
    background-color: #f1f1f1;
}

.cam-admin-row {
    background-color: #f0f7ff !important;
}

.cam-role-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 6px;
    background-color: #0073aa;
    color: white;
    font-size: 11px;
    border-radius: 3px;
    vertical-align: middle;
}

.cam-orders-row td {
    padding: 0 !important;
}

.cam-user-orders {
    padding: 15px;
    background-color: #f9f9f9;
}

.cam-user-orders h4 {
    margin-top: 0;
    margin-bottom: 10px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle orders view
    $('.toggle-orders').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user');
        
        if (userId === 'admin') {
            // Toggle admin orders
            $('.admin-orders').toggle();
            
            if ($('.admin-orders').is(':visible')) {
                $(this).text('<?php _e('Hide Orders', 'company-accounts-manager'); ?>');
            } else {
                $(this).text('<?php _e('View Orders', 'company-accounts-manager'); ?>');
            }
        } else if (userId.startsWith('child-')) {
            // Toggle child orders
            var index = userId.replace('child-', '');
            $('.child-orders-' + index).toggle();
            
            if ($('.child-orders-' + index).is(':visible')) {
                $(this).text('<?php _e('Hide Orders', 'company-accounts-manager'); ?>');
            } else {
                $(this).text('<?php _e('View Orders', 'company-accounts-manager'); ?>');
            }
        }
    });
    
    // Suspend/activate child account
    $('.suspend-child-account').on('click', function(e) {
        e.preventDefault();
        
        var userId = $(this).data('user-id');
        var action = $(this).data('action');
        var confirmMessage = action === 'suspend' 
            ? '<?php _e('Are you sure you want to suspend this child account? The user will not be able to log in until reactivated.', 'company-accounts-manager'); ?>'
            : '<?php _e('Are you sure you want to activate this child account?', 'company-accounts-manager'); ?>';
            
        if (confirm(confirmMessage)) {
            $('#cam-suspend-child-form input[name="user_id"]').val(userId);
            $('#cam-suspend-child-form input[name="action_type"]').val(action);
            $('#cam-suspend-child-form').submit();
        }
    });
    
    // Suspend/activate company admin
    $('.suspend-company-admin').on('click', function(e) {
        e.preventDefault();
        
        var userId = $(this).data('user-id');
        var action = $(this).data('action');
        var confirmMessage = action === 'suspend' 
            ? '<?php _e('Are you sure you want to suspend the company admin? The admin will not be able to log in until reactivated. This does not affect child accounts.', 'company-accounts-manager'); ?>'
            : '<?php _e('Are you sure you want to activate the company admin?', 'company-accounts-manager'); ?>';
            
        if (confirm(confirmMessage)) {
            $('#cam-suspend-admin-form input[name="user_id"]').val(userId);
            $('#cam-suspend-admin-form input[name="action_type"]').val(action);
            $('#cam-suspend-admin-form').submit();
        }
    });
    
    // Suspend/activate entire company
    $('.suspend-entire-company').on('click', function(e) {
        e.preventDefault();
        
        var companyId = $(this).data('company-id');
        var action = $(this).data('action');
        var confirmMessage = action === 'suspend' 
            ? '<?php _e('Are you sure you want to suspend the entire company? The company admin and all child accounts will not be able to log in until the company is reactivated.', 'company-accounts-manager'); ?>'
            : '<?php _e('Are you sure you want to activate the entire company? This will activate the company admin AND all child accounts. All users will be able to log in.', 'company-accounts-manager'); ?>';
            
        if (confirm(confirmMessage)) {
            $('#cam-suspend-company-form input[name="company_id"]').val(companyId);
            $('#cam-suspend-company-form input[name="action_type"]').val(action);
            $('#cam-suspend-company-form').submit();
        }
    });
    
    // Assign tier to company
    $('#assign-tier-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var spinner = form.find('.spinner');
        var message = form.find('.tier-message');
        var tierId = form.find('#tier_id').val();
        var companyId = form.find('input[name="company_id"]').val();
        
        spinner.addClass('is-active');
        message.text('').removeClass('notice-success notice-error');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cam_assign_tier',
                tier_id: tierId,
                company_id: companyId,
                security: form.find('#cam_tier_nonce').val()
            },
            success: function(response) {
                spinner.removeClass('is-active');
                
                if (response.success) {
                    message.text(response.data.message).addClass('notice-success');
                } else {
                    message.text(response.data.message).addClass('notice-error');
                }
            },
            error: function() {
                spinner.removeClass('is-active');
                message.text('<?php _e('An error occurred. Please try again.', 'company-accounts-manager'); ?>').addClass('notice-error');
            }
        });
    });
});
</script>

<!-- Hidden forms for actions -->
<form id="cam-suspend-child-form" method="post" style="display: none;">
    <input type="hidden" name="cam_toggle_child_status" value="1">
    <input type="hidden" name="user_id" value="">
    <input type="hidden" name="action_type" value="">
    <?php wp_nonce_field('cam_toggle_child_status', 'cam_toggle_child_status_nonce'); ?>
</form>

<form id="cam-suspend-admin-form" method="post" style="display: none;">
    <input type="hidden" name="cam_toggle_admin_status" value="1">
    <input type="hidden" name="user_id" value="">
    <input type="hidden" name="action_type" value="">
    <?php wp_nonce_field('cam_toggle_admin_status', 'cam_toggle_admin_status_nonce'); ?>
</form>

<form id="cam-suspend-company-form" method="post" style="display: none;">
    <input type="hidden" name="cam_toggle_entire_company_status" value="1">
    <input type="hidden" name="company_id" value="">
    <input type="hidden" name="action_type" value="">
    <?php wp_nonce_field('cam_toggle_company_status', 'cam_toggle_company_status_nonce'); ?>
</form> 