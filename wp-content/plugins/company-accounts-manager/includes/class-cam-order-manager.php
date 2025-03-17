<?php
/**
 * Handles order tracking and statistics
 */

if (!defined('ABSPATH')) {
    exit;
}

class CAM_Order_Manager {
    /**
     * Initialize order manager functionality
     */
    public static function init() {
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'update_order_stats'), 10, 4);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', array(__CLASS__, 'handle_custom_query_var'), 10, 2);
        
        // Populate company orders table with existing orders
        add_action('init', array(__CLASS__, 'populate_company_orders_table'), 20);
    }

    /**
     * Update order statistics when order status changes
     */
    public static function update_order_stats($order_id, $old_status, $new_status, $order) {
        if ($new_status === 'completed') {
            $company_id = get_post_meta($order_id, '_cam_company_id', true);
            if ($company_id) {
                self::update_company_stats($company_id);
            }
        }
    }

    /**
     * Update company statistics
     */
    private static function update_company_stats($company_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(order_total) as total_spent
            FROM {$wpdb->prefix}cam_company_orders 
            WHERE company_id = %d",
            $company_id
        ));

        update_option("cam_company_{$company_id}_total_orders", $stats->total_orders);
        update_option("cam_company_{$company_id}_total_spent", $stats->total_spent);
    }

    /**
     * Handle custom query variables for WooCommerce orders
     */
    public static function handle_custom_query_var($query, $query_vars) {
        if (!empty($query_vars['cam_company_id'])) {
            $query['meta_query'][] = array(
                'key' => '_cam_company_id',
                'value' => esc_attr($query_vars['cam_company_id'])
            );
        }

        if (isset($query_vars['cam_is_child_order'])) {
            $query['meta_query'][] = array(
                'key' => '_cam_is_child_order',
                'value' => 'yes'
            );
        }

        return $query;
    }

    /**
     * Get company orders
     */
    public static function get_company_orders($company_id, $args = array()) {
        $default_args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'cam_company_id' => $company_id
        );

        $args = wp_parse_args($args, $default_args);
        return wc_get_orders($args);
    }

    /**
     * Get company child orders
     */
    public static function get_company_child_orders($company_id, $args = array()) {
        $default_args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'cam_company_id' => $company_id,
            'cam_is_child_order' => true
        );

        $args = wp_parse_args($args, $default_args);
        return wc_get_orders($args);
    }

    /**
     * Get company order statistics
     */
    public static function get_company_order_stats($company_id, $date_range = '') {
        global $wpdb;

        $where = "WHERE company_id = %d";
        $params = array($company_id);

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
                case 'custom':
                    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                        $where .= " AND order_date BETWEEN %s AND %s";
                        $params[] = sanitize_text_field($_GET['start_date']);
                        $params[] = sanitize_text_field($_GET['end_date']);
                    }
                    break;
            }
        }

        // Get overall statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(order_total) as total_spent,
                AVG(order_total) as average_order,
                MAX(order_total) as largest_order,
                COUNT(DISTINCT user_id) as unique_customers
            FROM {$wpdb->prefix}cam_company_orders
            $where",
            $params
        ));

        // Get orders by day for the chart
        $daily_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(order_date) as order_day,
                COUNT(*) as orders_count,
                SUM(order_total) as daily_total
            FROM {$wpdb->prefix}cam_company_orders
            $where
            GROUP BY DATE(order_date)
            ORDER BY order_day ASC",
            $params
        ));

        // Get top child accounts
        $top_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                co.user_id,
                u.display_name,
                COUNT(*) as orders_count,
                SUM(co.order_total) as total_spent
            FROM {$wpdb->prefix}cam_company_orders co
            JOIN {$wpdb->users} u ON co.user_id = u.ID
            $where
            GROUP BY co.user_id
            ORDER BY total_spent DESC
            LIMIT 5",
            $params
        ));

        return array(
            'summary' => array(
                'total_orders' => (int) $stats->total_orders,
                'total_spent' => (float) $stats->total_spent,
                'average_order' => (float) $stats->average_order,
                'largest_order' => (float) $stats->largest_order,
                'unique_customers' => (int) $stats->unique_customers
            ),
            'daily_orders' => $daily_orders,
            'top_customers' => $top_customers
        );
    }

    /**
     * Export company orders to CSV
     */
    public static function export_company_orders_csv($company_id) {
        $orders = self::get_company_orders($company_id);
        
        if (empty($orders)) {
            return false;
        }

        $headers = array(
            'Order ID',
            'Date',
            'Customer',
            'Status',
            'Total',
            'Items'
        );

        $data = array();
        foreach ($orders as $order) {
            $items = array();
            foreach ($order->get_items() as $item) {
                $items[] = $item->get_name() . ' × ' . $item->get_quantity();
            }

            $data[] = array(
                $order->get_order_number(),
                $order->get_date_created()->date('Y-m-d H:i:s'),
                $order->get_formatted_billing_full_name(),
                $order->get_status(),
                $order->get_formatted_order_total(),
                implode(', ', $items)
            );
        }

        $filename = 'company-orders-' . $company_id . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $fp = fopen('php://output', 'w');
        fputcsv($fp, $headers);
        
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        return true;
    }

    /**
     * Get company orders by date range
     * 
     * @param int $company_id Company ID
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @return array Array of WC_Order objects
     */
    public static function get_company_orders_by_date($company_id, $start_date, $end_date) {
        global $wpdb;
        
        // Get all user IDs associated with this company
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
        
        if (empty($company_user_ids)) {
            return array();
        }
        
        // Format user IDs for SQL query
        $user_ids_string = implode(',', array_map('intval', $company_user_ids));
        
        // Include more order statuses to ensure we get all orders
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
        
        // Get order IDs for these users within the date range
        $query = "
            SELECT posts.ID
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS pm ON posts.ID = pm.post_id
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_string})
            AND pm.meta_key = '_customer_user'
            AND pm.meta_value IN ({$user_ids_string})
            AND posts.post_date >= '{$start_date} 00:00:00'
            AND posts.post_date <= '{$end_date} 23:59:59'
            ORDER BY posts.post_date DESC
        ";
        
        // Debug the query
        error_log('CAM Order Query: ' . $query);
        
        $order_ids = $wpdb->get_col($query);
        
        // Debug the results
        error_log('CAM Order IDs found: ' . count($order_ids));
        
        $orders = array();
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[] = $order;
            }
        }
        
        return $orders;
    }

    /**
     * Populate company orders table with existing orders
     * This ensures that orders placed before the plugin was fully functional
     * are included in the statistics
     */
    public static function populate_company_orders_table() {
        global $wpdb;
        
        // Check if we need to run this
        $has_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders");
        if ($has_orders > 0) {
            // We already have orders in the table, so only run once per day
            $last_run = get_option('cam_populate_orders_last_run');
            if ($last_run && (time() - $last_run < DAY_IN_SECONDS)) {
                return;
            }
        }
        
        // Get all company IDs
        $company_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}cam_companies");
        
        if (empty($company_ids)) {
            return;
        }
        
        $orders_added = 0;
        
        foreach ($company_ids as $company_id) {
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
            
            if (empty($company_user_ids)) {
                continue;
            }
            
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
                        $result = $wpdb->insert(
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
                        
                        if ($result) {
                            $orders_added++;
                        }
                    }
                }
            }
        }
        
        // Update last run time
        update_option('cam_populate_orders_last_run', time());
        
        // For debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('CAM: Populated company orders table. Added %d new orders.', $orders_added));
        }
    }
} 