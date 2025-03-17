<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current values
$current_search = isset($_GET['cam_search']) ? sanitize_text_field($_GET['cam_search']) : '';
$current_tier = isset($_GET['cam_tier']) ? intval($_GET['cam_tier']) : 0;
$current_sort = isset($_GET['cam_sort']) ? sanitize_text_field($_GET['cam_sort']) : 'date_desc';
?>
<div class="wrap">
    <h1><?php _e('All Companies', 'company-accounts-manager'); ?></h1>
    
    <!-- Admin Actions -->
    <div class="cam-admin-actions" style="margin-bottom: 15px;">
        <form method="post" action="">
            <?php wp_nonce_field('cam_admin_populate_orders', 'cam_admin_populate_nonce'); ?>
            <input type="hidden" name="action" value="cam_admin_populate_orders">
            <button type="submit" class="button button-secondary">
                <?php _e('Refresh Order Statistics', 'company-accounts-manager'); ?>
            </button>
            <span class="description" style="margin-left: 10px;">
                <?php _e('Use this button to refresh order statistics if they are not displaying correctly.', 'company-accounts-manager'); ?>
            </span>
        </form>
    </div>
    
    <!-- Search, Filter, and Sort Form -->
    <div class="cam-filter-box">
        <form method="get" action="">
            <input type="hidden" name="page" value="cam-all-companies">
            
            <div class="cam-filter-row">
                <!-- Search -->
                <div class="cam-search-box">
                    <label for="cam_search"><?php _e('Search Companies:', 'company-accounts-manager'); ?></label>
                    <input type="text" id="cam_search" name="cam_search" value="<?php echo esc_attr($current_search); ?>" placeholder="<?php _e('Company name or email', 'company-accounts-manager'); ?>">
                </div>
                
                <!-- Tier Filter -->
                <div class="cam-tier-filter">
                    <label for="cam_tier"><?php _e('Filter by Tier:', 'company-accounts-manager'); ?></label>
                    <select id="cam_tier" name="cam_tier">
                        <option value="0"><?php _e('All Tiers', 'company-accounts-manager'); ?></option>
                        <option value="-1" <?php selected($current_tier, -1); ?>><?php _e('Unassigned', 'company-accounts-manager'); ?></option>
                        <?php foreach ($tiers as $tier) : ?>
                            <option value="<?php echo esc_attr($tier->id); ?>" <?php selected($current_tier, $tier->id); ?>>
                                <?php echo esc_html($tier->tier_name); ?> (<?php echo esc_html($tier->discount_percentage); ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort By -->
                <div class="cam-sort-by">
                    <label for="cam_sort"><?php _e('Sort By:', 'company-accounts-manager'); ?></label>
                    <select id="cam_sort" name="cam_sort">
                        <option value="date_desc" <?php selected($current_sort, 'date_desc'); ?>><?php _e('Registration Date (Newest First)', 'company-accounts-manager'); ?></option>
                        <option value="date_asc" <?php selected($current_sort, 'date_asc'); ?>><?php _e('Registration Date (Oldest First)', 'company-accounts-manager'); ?></option>
                        <option value="spend_desc" <?php selected($current_sort, 'spend_desc'); ?>><?php _e('Total Spent (High to Low)', 'company-accounts-manager'); ?></option>
                        <option value="spend_asc" <?php selected($current_sort, 'spend_asc'); ?>><?php _e('Total Spent (Low to High)', 'company-accounts-manager'); ?></option>
                    </select>
                </div>
                
                <div class="cam-filter-actions">
                    <button type="submit" class="button"><?php _e('Apply Filters', 'company-accounts-manager'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=cam-all-companies')); ?>" class="button-link"><?php _e('Reset', 'company-accounts-manager'); ?></a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($companies)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No companies found.', 'company-accounts-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Company Name', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Admin', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Email', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Tier', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Child Accounts', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Total Orders', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Total Spent', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Registration Date', 'company-accounts-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company) : 
                    $company_tier = CAM_Tiers::get_company_tier($company->id);
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($company->company_name); ?></strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=cam-company-details&company_id=' . $company->id)); ?>">
                                        <?php _e('View Details', 'company-accounts-manager'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($company->display_name); ?></td>
                        <td><?php echo esc_html($company->user_email); ?></td>
                        <td>
                            <span class="cam-status cam-status-<?php echo esc_attr($company->status); ?>">
                                <?php echo esc_html(ucfirst($company->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($company_tier) : ?>
                                <?php echo esc_html($company_tier->tier_name); ?> (<?php echo esc_html($company_tier->discount_percentage); ?>%)
                            <?php else : ?>
                                <span class="cam-no-tier"><?php _e('Unassigned', 'company-accounts-manager'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($company->child_accounts); ?></td>
                        <td><?php echo esc_html($company->total_orders); ?></td>
                        <td><?php echo wc_price($company->total_spent); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($company->registration_date))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
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

.cam-status-rejected, .cam-status-suspended {
    background-color: #f2dede;
    color: #a94442;
}

.cam-filter-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.cam-filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 15px;
}

.cam-search-box, .cam-tier-filter, .cam-sort-by {
    margin-bottom: 10px;
}

.cam-search-box label, .cam-tier-filter label, .cam-sort-by label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.cam-search-box input {
    width: 250px;
}

.cam-filter-actions {
    margin-bottom: 10px;
}

.cam-no-tier {
    color: #999;
    font-style: italic;
}
</style> 