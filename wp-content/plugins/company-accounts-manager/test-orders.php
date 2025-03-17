<?php
/**
 * Test Orders
 * 
 * This file tests order retrieval for the Company Accounts Manager plugin.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('administrator')) {
    wp_die('You do not have permission to access this page.');
}

echo '<h1>Test Orders</h1>';

// Get all companies
global $wpdb;
$companies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cam_companies");

foreach ($companies as $company) {
    echo '<h2>Company: ' . esc_html($company->company_name) . ' (ID: ' . $company->id . ')</h2>';
    
    // Get all orders for this company
    $orders = wc_get_orders(array(
        'meta_key' => '_cam_company_id',
        'meta_value' => $company->id,
        'limit' => -1
    ));
    
    echo '<p>Orders found with meta query: ' . count($orders) . '</p>';
    
    if (!empty($orders)) {
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr><th>Order ID</th><th>Date</th><th>Status</th><th>Total</th></tr>';
        
        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td>' . $order->get_id() . '</td>';
            echo '<td>' . $order->get_date_created()->date_i18n(get_option('date_format')) . '</td>';
            echo '<td>' . wc_get_order_status_name($order->get_status()) . '</td>';
            echo '<td>' . $order->get_formatted_order_total() . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    // Try a different approach - get all users for this company
    $company_user_ids = array();
    
    // Get company admin
    $admin = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}cam_companies WHERE id = %d",
        $company->id
    ));
    
    if ($admin) {
        $company_user_ids[] = $admin->user_id;
    }
    
    // Get child accounts
    $child_accounts = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}cam_child_accounts WHERE company_id = %d",
        $company->id
    ));
    
    foreach ($child_accounts as $child) {
        $company_user_ids[] = $child->user_id;
    }
    
    echo '<p>Users found for this company: ' . count($company_user_ids) . '</p>';
    
    if (!empty($company_user_ids)) {
        // Get orders for these users
        $orders = array();
        foreach ($company_user_ids as $user_id) {
            $user_orders = wc_get_orders(array(
                'customer' => $user_id,
                'limit' => -1
            ));
            $orders = array_merge($orders, $user_orders);
        }
        
        echo '<p>Orders found with customer query: ' . count($orders) . '</p>';
        
        if (!empty($orders)) {
            echo '<table border="1" cellpadding="5" cellspacing="0">';
            echo '<tr><th>Order ID</th><th>Date</th><th>Status</th><th>Customer</th><th>Total</th></tr>';
            
            foreach ($orders as $order) {
                $customer_id = $order->get_customer_id();
                $customer = get_userdata($customer_id);
                
                echo '<tr>';
                echo '<td>' . $order->get_id() . '</td>';
                echo '<td>' . $order->get_date_created()->date_i18n(get_option('date_format')) . '</td>';
                echo '<td>' . wc_get_order_status_name($order->get_status()) . '</td>';
                echo '<td>' . ($customer ? esc_html($customer->display_name) : 'Guest') . ' (ID: ' . $customer_id . ')</td>';
                echo '<td>' . $order->get_formatted_order_total() . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // Calculate totals
            $total_spent = 0;
            foreach ($orders as $order) {
                $total_spent += $order->get_total();
            }
            
            echo '<p>Total Orders: ' . count($orders) . '</p>';
            echo '<p>Total Spent: ' . wc_price($total_spent) . '</p>';
            echo '<p>Average Order: ' . (count($orders) > 0 ? wc_price($total_spent / count($orders)) : wc_price(0)) . '</p>';
        }
    }
} 