<?php
if (!defined('ABSPATH')) {
    exit;
}

// Force refresh of data
global $wpdb;
$total_companies = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_companies WHERE status = 'active'");
$total_child_accounts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_child_accounts WHERE status = 'active'");
$total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cam_company_orders");
$total_revenue = $wpdb->get_var("SELECT SUM(order_total) FROM {$wpdb->prefix}cam_company_orders");

// Set to 0 if null
$total_companies = $total_companies ?: 0;
$total_child_accounts = $total_child_accounts ?: 0;
$total_orders = $total_orders ?: 0;
$total_revenue = $total_revenue ?: 0;
?>
<div class="wrap">
    <h1><?php _e('Company Accounts Dashboard', 'company-accounts-manager'); ?></h1>

    <div class="cam-dashboard-stats">
        <div class="cam-stat-box">
            <h3><?php _e('Total Companies', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo esc_html($total_companies); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Total Child Accounts', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo esc_html($total_child_accounts); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Total Orders', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo esc_html($total_orders); ?></p>
        </div>

        <div class="cam-stat-box">
            <h3><?php _e('Total Revenue', 'company-accounts-manager'); ?></h3>
            <p class="cam-stat-number"><?php echo wc_price($total_revenue); ?></p>
        </div>
    </div>

    <div class="cam-dashboard-actions">
        <a href="<?php echo admin_url('admin.php?page=cam-pending-companies'); ?>" class="button button-primary">
            <?php _e('Manage Pending Companies', 'company-accounts-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=cam-all-companies'); ?>" class="button">
            <?php _e('View All Companies', 'company-accounts-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=cam-settings'); ?>" class="button">
            <?php _e('Settings', 'company-accounts-manager'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=cam-clear-data'); ?>" id="clear-data-button" class="button button-secondary" style="background-color: #d63638; color: white; border-color: #d63638;">
            <?php _e('Clear All Data', 'company-accounts-manager'); ?>
        </a>
    </div>
</div>

<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var clearDataButton = document.getElementById('clear-data-button');
        if (clearDataButton) {
            clearDataButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                var confirmMessage = '<?php _e('WARNING: This will permanently delete all company data, child accounts, and order records. This action cannot be undone. Are you sure you want to proceed?', 'company-accounts-manager'); ?>';
                
                if (confirm(confirmMessage)) {
                    window.location.href = this.href;
                }
            });
        }
    });
</script>

<style>
.cam-dashboard-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin: 20px 0;
}

.cam-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    min-width: 200px;
    flex: 1;
}

.cam-stat-box h3 {
    margin: 0 0 10px;
    color: #23282d;
}

.cam-stat-number {
    font-size: 24px;
    font-weight: 600;
    margin: 0;
    color: #0073aa;
}

.cam-dashboard-actions {
    margin-top: 30px;
}

.cam-dashboard-actions .button {
    margin-right: 10px;
}
</style> 