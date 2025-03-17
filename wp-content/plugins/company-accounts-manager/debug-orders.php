<?php
/**
 * Debug Orders
 * 
 * This file helps debug order retrieval issues in the Company Accounts Manager plugin.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('administrator')) {
    wp_die('You do not have permission to access this page.');
}

// Get company ID from URL
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if (!$company_id) {
    echo '<p>Please provide a company ID in the URL: ?company_id=X</p>';
    exit;
}

// Get company details
$company = CAM_Company_Admin::get_company_details($company_id);
if (!$company) {
    echo '<p>Company not found with ID: ' . $company_id . '</p>';
    exit;
}

echo '<h1>Debug Orders for Company: ' . esc_html($company->company_name) . ' (ID: ' . $company_id . ')</h1>';

// Get all user IDs associated with this company
global $wpdb;
$company_user_ids = array();

// Get company admin
$admin = $wpdb->get_row($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
    $company_id
));

if ($admin) {
    $company_user_ids[] = $admin->user_id;
    $admin_user = get_userdata($admin->user_id);
    echo '<p>Company Admin: ' . esc_html($admin_user->display_name) . ' (ID: ' . $admin->user_id . ')</p>';
}

// Get child accounts
$child_accounts = $wpdb->get_results($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->prefix}cam_child_accounts WHERE company_id = %d",
    $company_id
));

echo '<p>Child Accounts: ' . count($child_accounts) . '</p>';
echo '<ul>';
foreach ($child_accounts as $child) {
    $company_user_ids[] = $child->user_id;
    $child_user = get_userdata($child->user_id);
    echo '<li>' . esc_html($child_user->display_name) . ' (ID: ' . $child->user_id . ')</li>';
}
echo '</ul>';

if (empty($company_user_ids)) {
    echo '<p>No users found for this company.</p>';
    exit;
}

// Format user IDs for SQL query
$user_ids_string = implode(',', array_map('intval', $company_user_ids));

// Include all order statuses
$order_statuses = array(
    'wc-completed', 
    'wc-processing', 
    'wc-on-hold', 
    'wc-pending',
    'wc-failed',
    'wc-refunded',
    'wc-cancelled'
);
$status_string = "'" . implode("','", $order_statuses) . "'";

// Get order IDs for these users
$query = "
    SELECT posts.ID, posts.post_date, posts.post_status, pm.meta_value as user_id
    FROM {$wpdb->posts} AS posts
    INNER JOIN {$wpdb->postmeta} AS pm ON posts.ID = pm.post_id
    WHERE posts.post_type = 'shop_order'
    AND posts.post_status IN ({$status_string})
    AND pm.meta_key = '_customer_user'
    AND pm.meta_value IN ({$user_ids_string})
    ORDER BY posts.post_date DESC
";

echo '<h2>SQL Query</h2>';
echo '<pre>' . esc_html($query) . '</pre>';

$order_data = $wpdb->get_results($query);

echo '<h2>Orders Found: ' . count($order_data) . '</h2>';

if (empty($order_data)) {
    echo '<p>No orders found for this company.</p>';
} else {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Order ID</th><th>Date</th><th>Status</th><th>Customer ID</th><th>Customer Name</th><th>Total</th></tr>';
    
    foreach ($order_data as $data) {
        $order = wc_get_order($data->ID);
        if ($order) {
            $customer = get_userdata($data->user_id);
            echo '<tr>';
            echo '<td>' . $data->ID . '</td>';
            echo '<td>' . $data->post_date . '</td>';
            echo '<td>' . $data->post_status . '</td>';
            echo '<td>' . $data->user_id . '</td>';
            echo '<td>' . ($customer ? esc_html($customer->display_name) : 'Unknown') . '</td>';
            echo '<td>' . $order->get_formatted_order_total() . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</table>';
}

// Check cam_company_orders table
echo '<h2>Orders in cam_company_orders Table</h2>';

$company_orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}cam_company_orders WHERE company_id = %d ORDER BY order_date DESC",
    $company_id
));

echo '<p>Records found: ' . count($company_orders) . '</p>';

if (!empty($company_orders)) {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>ID</th><th>Order ID</th><th>Company ID</th><th>User ID</th><th>Order Total</th><th>Order Date</th></tr>';
    
    foreach ($company_orders as $order) {
        echo '<tr>';
        echo '<td>' . $order->id . '</td>';
        echo '<td>' . $order->order_id . '</td>';
        echo '<td>' . $order->company_id . '</td>';
        echo '<td>' . $order->user_id . '</td>';
        echo '<td>' . $order->order_total . '</td>';
        echo '<td>' . $order->order_date . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

// Calculate totals
$total_orders = count($order_data);
$total_spent = 0;
foreach ($order_data as $data) {
    $order = wc_get_order($data->ID);
    if ($order) {
        $total_spent += $order->get_total();
    }
}

echo '<h2>Summary</h2>';
echo '<p>Total Orders: ' . $total_orders . '</p>';
echo '<p>Total Spent: ' . wc_price($total_spent) . '</p>';
echo '<p>Average Order: ' . ($total_orders > 0 ? wc_price($total_spent / $total_orders) : wc_price(0)) . '</p>'; 