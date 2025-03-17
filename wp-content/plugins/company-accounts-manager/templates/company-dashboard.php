<?php
if (!defined('ABSPATH')) {
    exit;
}

// Debug current user
$current_user_id = get_current_user_id();
$current_user = get_userdata($current_user_id);
echo '<!-- Current User ID: ' . esc_html($current_user_id) . ' -->';
echo '<!-- Current User Roles: ' . esc_html(implode(', ', (array) $current_user->roles)) . ' -->';

// Get company details
$company_id = CAM_Roles::get_user_company_id();
echo '<!-- Company ID from get_user_company_id: ' . esc_html($company_id) . ' -->';

if (!$company_id) {
    echo '<p>' . __('No company found for your account. Please contact the administrator.', 'company-accounts-manager') . '</p>';
    return;
}

$company = CAM_Company_Admin::get_company_details($company_id);
echo '<!-- Company details: ' . (is_object($company) ? 'Found' : 'Not found') . ' -->';

if (!$company) {
    echo '<p>' . __('Company details not found. Please contact the administrator.', 'company-accounts-manager') . '</p>';
    return;
}

// Get child accounts
$child_accounts = CAM_Roles::get_company_child_accounts($company_id);

// Debug information
echo '<!-- Company ID: ' . esc_html($company_id) . ' -->';
echo '<!-- Company Name: ' . esc_html($company->company_name) . ' -->';
echo '<!-- Child Accounts Count: ' . count($child_accounts) . ' -->';

// Check if child accounts table exists
global $wpdb;
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}cam_child_accounts'");
echo '<!-- Child accounts table exists: ' . ($table_exists ? 'Yes' : 'No') . ' -->';

// Check for any child accounts in the database
$any_child_accounts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts");
echo '<!-- Total child accounts in database: ' . esc_html($any_child_accounts) . ' -->';

// Debug statistics
echo '<!-- Stats variable: ' . (isset($stats) ? 'Set' : 'Not set') . ' -->';
if (isset($stats) && is_array($stats)) {
    echo '<!-- Stats summary: ' . (isset($stats['summary']) ? 'Set' : 'Not set') . ' -->';
    if (isset($stats['summary'])) {
        echo '<!-- Total orders: ' . esc_html($stats['summary']['total_orders']) . ' -->';
        echo '<!-- Total spent: ' . esc_html($stats['summary']['total_spent']) . ' -->';
        echo '<!-- Average order: ' . esc_html($stats['summary']['average_order']) . ' -->';
    }
}

// Check if company has any orders in the company_orders table
$company_orders_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders WHERE company_id = %d",
    $company_id
));
echo '<!-- Company orders in database: ' . esc_html($company_orders_count) . ' -->';

// Debug WooCommerce orders
if (isset($company_orders)) {
    echo '<!-- WooCommerce orders count: ' . count($company_orders) . ' -->';
    $wc_total_spent = 0;
    foreach ($company_orders as $order) {
        $wc_total_spent += $order->get_total();
    }
    echo '<!-- WooCommerce total spent: ' . $wc_total_spent . ' -->';
}

// Get all users associated with this company
$company_user_ids = array();

// Add company admin
$company_user_ids[] = $company->user_id;

// Add child accounts
foreach ($child_accounts as $child) {
    $company_user_ids[] = $child->user_id;
}

// Get orders for these users
$all_orders = array();
foreach ($company_user_ids as $user_id) {
    $user_orders = wc_get_orders(array(
        'customer' => $user_id,
        'limit' => -1
    ));
    $all_orders = array_merge($all_orders, $user_orders);
}

// Double-check that we only have orders for our company users
$filtered_orders = array();
foreach ($all_orders as $order) {
    $customer_id = $order->get_customer_id();
    if (in_array($customer_id, $company_user_ids)) {
        $filtered_orders[] = $order;
    }
}
$all_orders = $filtered_orders;

// Calculate statistics
$total_orders = count($all_orders);
$total_spent = 0;
foreach ($all_orders as $order) {
    $total_spent += $order->get_total();
}
$average_order = $total_orders > 0 ? $total_spent / $total_orders : 0;

echo '<!-- Direct WooCommerce query - Orders found: ' . $total_orders . ' -->';
echo '<!-- Direct WooCommerce query - Total spent: ' . $total_spent . ' -->';
?>

<div class="cam-company-dashboard">
    <h2><?php echo esc_html($company->company_name); ?> - <?php _e('Company Dashboard', 'company-accounts-manager'); ?></h2>

    <!-- Statistics Overview -->
    <div class="cam-stats-overview">
        <div class="cam-stat-box">
            <h3><?php _e('Total Orders', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo esc_html($total_orders); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Total Spent', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo wc_price($total_spent); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Average Order', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo wc_price($average_order); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Child Accounts', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number">
                <?php echo count($child_accounts); ?>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('child-accounts')); ?>" class="button button-small" style="margin-left: 10px;">
                    <?php _e('Manage', 'company-accounts-manager'); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="cam-section">
        <h3><?php _e('Recent Orders', 'company-accounts-manager'); ?></h3>
        <?php
        // Use the orders we already retrieved for statistics
        // Sort the orders by date (newest first)
        usort($all_orders, function($a, $b) {
            return $b->get_date_created()->getTimestamp() - $a->get_date_created()->getTimestamp();
        });
        
        // Get the 10 most recent orders
        $recent_orders = array_slice($all_orders, 0, 10);
        
        if (empty($recent_orders)) : ?>
            <p class="cam-no-data"><?php _e('No orders yet.', 'company-accounts-manager'); ?></p>
        <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Date', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Customer', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Total', 'company-accounts-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>">
                                    #<?php echo esc_html($order->get_order_number()); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                            <td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
                            <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                            <td><?php echo $order->get_formatted_order_total(); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.cam-company-dashboard {
    margin: 20px 0;
}

.cam-stats-overview {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.cam-stat-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    min-width: 200px;
    flex: 1;
}

.cam-stat-box h3 {
    margin: 0 0 10px;
    color: #23282d;
    font-size: 14px;
}

.cam-stat-number {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
    color: #0073aa;
}

.cam-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.cam-section h3 {
    margin-top: 0;
}

.cam-no-data {
    color: #666;
    font-style: italic;
    margin: 20px 0;
}

/* Status Indicators */
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

.cam-status-suspended {
    background-color: #f8d7da;
    color: #721c24;
}

/* Child Accounts Table Styling */
.cam-child-accounts-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 13px;
}

.cam-child-accounts-table th,
.cam-child-accounts-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.cam-child-accounts-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.cam-child-accounts-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.cam-child-accounts-table tr:hover {
    background-color: #f1f1f1;
}

.cam-table-container {
    overflow-x: auto;
    margin-top: 15px;
}
</style>

<!-- Debug section for administrators -->
<?php if (current_user_can('administrator')): ?>
<div class="cam-section" style="margin-top: 20px; border: 1px dashed #ccc; background: #f9f9f9;">
    <h3><?php _e('Administrator Debug Tools', 'company-accounts-manager'); ?></h3>
    <p><?php _e('These tools are only visible to site administrators.', 'company-accounts-manager'); ?></p>
    
    <form method="post" action="">
        <?php wp_nonce_field('cam_populate_orders', 'cam_populate_nonce'); ?>
        <input type="hidden" name="action" value="cam_populate_orders">
        <p>
            <button type="submit" class="button"><?php _e('Populate Company Orders Table', 'company-accounts-manager'); ?></button>
            <span class="description"><?php _e('This will scan for all orders associated with this company and add them to the statistics table.', 'company-accounts-manager'); ?></span>
        </p>
    </form>
    
    <div style="margin-top: 20px;">
        <h4><?php _e('Debug: Company Users', 'company-accounts-manager'); ?></h4>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('User ID', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Name', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Email', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Role', 'company-accounts-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Company admin
                $admin_user = get_userdata($company->user_id);
                if ($admin_user): 
                ?>
                <tr>
                    <td><?php echo $admin_user->ID; ?></td>
                    <td><?php echo esc_html($admin_user->display_name); ?></td>
                    <td><?php echo esc_html($admin_user->user_email); ?></td>
                    <td><?php _e('Company Admin', 'company-accounts-manager'); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php 
                // Child accounts
                foreach ($child_accounts as $child): 
                    $child_user = get_userdata($child->user_id);
                    if (!$child_user) continue;
                ?>
                <tr>
                    <td><?php echo $child_user->ID; ?></td>
                    <td><?php echo esc_html($child_user->display_name); ?></td>
                    <td><?php echo esc_html($child_user->user_email); ?></td>
                    <td><?php _e('Child Account', 'company-accounts-manager'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (isset($all_orders) && !empty($all_orders)): ?>
    <div style="margin-top: 20px;">
        <h4><?php _e('Debug: Retrieved Orders', 'company-accounts-manager'); ?></h4>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Order ID', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Date', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Customer ID', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Customer', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Total', 'company-accounts-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_orders as $order): ?>
                <tr>
                    <td><?php echo $order->get_id(); ?></td>
                    <td><?php echo $order->get_date_created()->date_i18n(get_option('date_format')); ?></td>
                    <td><?php echo $order->get_customer_id(); ?></td>
                    <td><?php echo $order->get_formatted_billing_full_name(); ?></td>
                    <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                    <td><?php echo $order->get_formatted_order_total(); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?> 