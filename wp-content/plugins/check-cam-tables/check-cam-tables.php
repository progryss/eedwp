<?php
/**
 * Plugin Name: Check CAM Tables
 * Description: Checks if Company Accounts Manager tables exist in the database
 * Version: 1.0
 * Author: Claude
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'check_cam_tables_menu');

function check_cam_tables_menu() {
    add_management_page(
        'Check CAM Tables',
        'Check CAM Tables',
        'manage_options',
        'check-cam-tables',
        'check_cam_tables_page'
    );
}

// Admin page
function check_cam_tables_page() {
    global $wpdb;

    $tables_to_check = array(
        $wpdb->prefix . 'cam_companies',
        $wpdb->prefix . 'cam_child_accounts',
        $wpdb->prefix . 'cam_company_orders',
        $wpdb->prefix . 'cam_discount_tiers'
    );

    echo '<div class="wrap">';
    echo '<h1>Check Company Accounts Manager Tables</h1>';
    
    echo '<table class="widefat" style="margin-top: 20px;">';
    echo '<thead><tr><th>Table Name</th><th>Status</th><th>Structure</th><th>Row Count</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($tables_to_check as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        
        echo '<tr>';
        echo '<td>' . esc_html($table) . '</td>';
        echo '<td>' . ($table_exists ? '<span style="color:green;font-weight:bold;">EXISTS</span>' : '<span style="color:red;font-weight:bold;">DOES NOT EXIST</span>') . '</td>';
        
        if ($table_exists) {
            // Get table structure
            $columns = $wpdb->get_results("DESCRIBE $table");
            echo '<td><details><summary>Show Structure</summary><ul>';
            foreach ($columns as $column) {
                echo '<li>' . esc_html($column->Field) . ' (' . esc_html($column->Type) . ')</li>';
            }
            echo '</ul></details></td>';
            
            // Count rows
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo '<td>' . intval($count) . '</td>';
        } else {
            echo '<td>N/A</td><td>N/A</td>';
        }
        
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    echo '<div style="margin-top: 20px;">';
    echo '<h2>How to create the tables</h2>';
    echo '<p>If the tables do not exist, you can try the following:</p>';
    echo '<ol>';
    echo '<li>Deactivate the Company Accounts Manager plugin</li>';
    echo '<li>Reactivate the Company Accounts Manager plugin</li>';
    echo '<li>Refresh this page to check if the tables were created</li>';
    echo '</ol>';
    
    echo '<p>If the tables still do not exist, you may need to manually create them. Here is the SQL:</p>';
    
    echo '<pre style="background:#f5f5f5;padding:10px;overflow:auto;">';
    echo "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_companies (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    company_name varchar(255) NOT NULL,
    industry varchar(255),
    company_info text,
    registration_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status varchar(20) NOT NULL DEFAULT 'pending',
    tier_id bigint(20) DEFAULT NULL,
    admin_status varchar(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY  (id),
    KEY user_id (user_id)
) {$wpdb->get_charset_collate()};

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_child_accounts (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    company_id bigint(20) NOT NULL,
    created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status varchar(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY  (id),
    KEY user_id (user_id),
    KEY company_id (company_id)
) {$wpdb->get_charset_collate()};

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_company_orders (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    order_id bigint(20) NOT NULL,
    company_id bigint(20) NOT NULL,
    user_id bigint(20) NOT NULL,
    order_total decimal(10,2) NOT NULL DEFAULT 0,
    order_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY order_id (order_id),
    KEY company_id (company_id),
    KEY user_id (user_id)
) {$wpdb->get_charset_collate()};

CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cam_discount_tiers (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    tier_name varchar(255) NOT NULL,
    discount_percentage decimal(5,2) NOT NULL DEFAULT 0,
    created_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id)
) {$wpdb->get_charset_collate()};";
    echo '</pre>';
    
    echo '</div>';
    echo '</div>';
} 