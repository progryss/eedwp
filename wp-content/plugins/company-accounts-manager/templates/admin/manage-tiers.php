<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get all tiers
$tiers = CAM_Tiers::get_all_tiers();
?>
<div class="wrap">
    <h1><?php _e('Manage Discount Tiers', 'company-accounts-manager'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Create and manage discount tiers for company accounts. Assign tiers to companies to provide automatic discounts on all products.', 'company-accounts-manager'); ?></p>
    </div>
    
    <!-- Add New Tier Form -->
    <div class="cam-add-tier-form">
        <h2><?php _e('Add New Tier', 'company-accounts-manager'); ?></h2>
        <form id="cam-add-tier-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tier_name"><?php _e('Tier Name', 'company-accounts-manager'); ?></label></th>
                    <td>
                        <input type="text" id="tier_name" name="tier_name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="discount_percentage"><?php _e('Discount Percentage', 'company-accounts-manager'); ?></label></th>
                    <td>
                        <input type="number" id="discount_percentage" name="discount_percentage" class="small-text" min="0" max="100" step="0.01" required>
                        <span class="description">%</span>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Add Tier', 'company-accounts-manager'); ?>">
                <?php wp_nonce_field('cam_tier_nonce', 'cam_tier_nonce'); ?>
            </p>
        </form>
    </div>
    
    <!-- Existing Tiers Table -->
    <div class="cam-tiers-table">
        <h2><?php _e('Existing Tiers', 'company-accounts-manager'); ?></h2>
        
        <?php if (empty($tiers)) : ?>
            <div class="notice notice-warning">
                <p><?php _e('No discount tiers found. Add your first tier above.', 'company-accounts-manager'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Tier Name', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Discount Percentage', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Created Date', 'company-accounts-manager'); ?></th>
                        <th><?php _e('Actions', 'company-accounts-manager'); ?></th>
                    </tr>
                </thead>
                <tbody id="cam-tiers-list">
                    <?php foreach ($tiers as $tier) : ?>
                        <tr id="tier-<?php echo esc_attr($tier->id); ?>">
                            <td><?php echo esc_html($tier->id); ?></td>
                            <td>
                                <span class="tier-name"><?php echo esc_html($tier->tier_name); ?></span>
                                <div class="hidden tier-edit-form">
                                    <input type="text" class="edit-tier-name" value="<?php echo esc_attr($tier->tier_name); ?>">
                                </div>
                            </td>
                            <td>
                                <span class="tier-discount"><?php echo esc_html($tier->discount_percentage); ?>%</span>
                                <div class="hidden tier-edit-form">
                                    <input type="number" class="edit-tier-discount" value="<?php echo esc_attr($tier->discount_percentage); ?>" min="0" max="100" step="0.01">
                                </div>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tier->created_date))); ?></td>
                            <td>
                                <div class="tier-actions">
                                    <a href="#" class="edit-tier" data-tier-id="<?php echo esc_attr($tier->id); ?>"><?php _e('Edit', 'company-accounts-manager'); ?></a> | 
                                    <a href="#" class="delete-tier" data-tier-id="<?php echo esc_attr($tier->id); ?>"><?php _e('Delete', 'company-accounts-manager'); ?></a>
                                </div>
                                <div class="hidden tier-edit-actions">
                                    <a href="#" class="save-tier" data-tier-id="<?php echo esc_attr($tier->id); ?>"><?php _e('Save', 'company-accounts-manager'); ?></a> | 
                                    <a href="#" class="cancel-edit"><?php _e('Cancel', 'company-accounts-manager'); ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Add new tier
    $('#cam-add-tier-form').on('submit', function(e) {
        e.preventDefault();
        
        var tierName = $('#tier_name').val();
        var discountPercentage = $('#discount_percentage').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cam_add_tier',
                tier_name: tierName,
                discount_percentage: discountPercentage,
                security: $('#cam_tier_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Edit tier
    $('.edit-tier').on('click', function(e) {
        e.preventDefault();
        
        var row = $(this).closest('tr');
        row.find('.tier-name, .tier-discount, .tier-actions').hide();
        row.find('.tier-edit-form, .tier-edit-actions').show();
    });
    
    // Cancel edit
    $('.cancel-edit').on('click', function(e) {
        e.preventDefault();
        
        var row = $(this).closest('tr');
        row.find('.tier-edit-form, .tier-edit-actions').hide();
        row.find('.tier-name, .tier-discount, .tier-actions').show();
    });
    
    // Save tier
    $('.save-tier').on('click', function(e) {
        e.preventDefault();
        
        var tierId = $(this).data('tier-id');
        var row = $(this).closest('tr');
        var tierName = row.find('.edit-tier-name').val();
        var discountPercentage = row.find('.edit-tier-discount').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cam_update_tier',
                tier_id: tierId,
                tier_name: tierName,
                discount_percentage: discountPercentage,
                security: $('#cam_tier_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    row.find('.tier-name').text(tierName);
                    row.find('.tier-discount').text(discountPercentage + '%');
                    row.find('.tier-edit-form, .tier-edit-actions').hide();
                    row.find('.tier-name, .tier-discount, .tier-actions').show();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Delete tier
    $('.delete-tier').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to delete this tier? Any companies assigned to this tier will be unassigned.', 'company-accounts-manager'); ?>')) {
            return;
        }
        
        var tierId = $(this).data('tier-id');
        var row = $('#tier-' + tierId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cam_delete_tier',
                tier_id: tierId,
                security: $('#cam_tier_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Show notice if no tiers left
                        if ($('#cam-tiers-list tr').length === 0) {
                            $('.cam-tiers-table table').replaceWith(
                                '<div class="notice notice-warning"><p><?php _e('No discount tiers found. Add your first tier above.', 'company-accounts-manager'); ?></p></div>'
                            );
                        }
                    });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
});
</script>

<style>
.hidden {
    display: none;
}
.cam-add-tier-form {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.tier-edit-form input {
    width: 100%;
    max-width: 250px;
}
</style> 