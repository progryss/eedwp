<?php
/**
 * Clear Dashboard Data
 * 
 * This script clears all data from the CAM dashboard tables.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
    require_once(ABSPATH . 'wp-config.php');
    require_once(ABSPATH . 'wp-load.php');
}

// Check if user is logged in and is an admin
if (!current_user_can('manage_options')) {
    die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

// Check if we need to force clear with direct SQL
$force_clear = isset($_POST['force_clear']) && $_POST['force_clear'] === 'yes';

// Tables to clear
$tables = array(
    $wpdb->prefix . 'cam_companies',
    $wpdb->prefix . 'cam_child_accounts',
    $wpdb->prefix . 'cam_company_orders',
);

// Clear each table
$cleared = array();
foreach ($tables as $table) {
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($table_exists) {
        // Get count before clearing
        $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        if ($force_clear) {
            // Use direct SQL to clear the table
            $wpdb->query("DELETE FROM $table");
            $wpdb->query("ALTER TABLE $table AUTO_INCREMENT = 1");
        } else {
            // Clear the table using WordPress method
            $wpdb->query("TRUNCATE TABLE $table");
        }
        
        // Get count after clearing
        $count_after = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        $cleared[] = array(
            'table' => $table,
            'count_before' => $count_before,
            'count_after' => $count_after,
            'success' => ($count_after == 0)
        );
    } else {
        $cleared[] = array(
            'table' => $table,
            'error' => 'Table does not exist'
        );
    }
}

// Clear any transients
$transients = array(
    'cam_dashboard_stats',
    'cam_total_companies',
    'cam_total_child_accounts',
    'cam_total_orders',
    'cam_total_revenue'
);

foreach ($transients as $transient) {
    delete_transient($transient);
}

// Output results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear Dashboard Data</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        h1 {
            color: #23282d;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .button {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 3px;
        }
        .button-danger {
            background-color: #d63638;
            border-color: #d63638;
        }
    </style>
</head>
<body>
    <h1>Clear Dashboard Data</h1>
    
    <h2>Results</h2>
    <table>
        <tr>
            <th>Table</th>
            <th>Records Before</th>
            <th>Records After</th>
            <th>Status</th>
        </tr>
        <?php foreach ($cleared as $result): ?>
            <tr>
                <td><?php echo esc_html($result['table']); ?></td>
                <td><?php echo isset($result['count_before']) ? esc_html($result['count_before']) : 'N/A'; ?></td>
                <td><?php echo isset($result['count_after']) ? esc_html($result['count_after']) : 'N/A'; ?></td>
                <td>
                    <?php if (isset($result['error'])): ?>
                        <span class="error"><?php echo esc_html($result['error']); ?></span>
                    <?php elseif ($result['success']): ?>
                        <span class="success">Cleared</span>
                    <?php else: ?>
                        <span class="error">Failed</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>Transients</h2>
    <p>The following transients were cleared:</p>
    <ul>
        <?php foreach ($transients as $transient): ?>
            <li><?php echo esc_html($transient); ?></li>
        <?php endforeach; ?>
    </ul>
    
    <?php if (!$force_clear): ?>
    <h2>Force Clear</h2>
    <p>If the tables still show data after clearing, you can try a force clear which uses direct SQL DELETE statements:</p>
    <form method="post" id="force-clear-form">
        <input type="hidden" name="force_clear" value="yes">
        <button type="submit" class="button button-danger" id="force-clear-button">Force Clear All Data</button>
    </form>
    <br>
    
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var forceClearForm = document.getElementById('force-clear-form');
            if (forceClearForm) {
                forceClearForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var confirmMessage = 'WARNING: This will permanently delete all company data, child accounts, and order records using direct SQL commands. This action cannot be undone and bypasses WordPress safeguards. Are you absolutely sure you want to proceed?';
                    
                    if (confirm(confirmMessage)) {
                        this.submit();
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
    
    <p><a href="<?php echo admin_url('admin.php?page=cam-dashboard'); ?>" class="button">Return to Dashboard</a></p>
</body>
</html> 