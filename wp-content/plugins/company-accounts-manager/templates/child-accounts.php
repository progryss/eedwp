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

if (!$company) {
    echo '<p>' . __('Company details not found. Please contact the administrator.', 'company-accounts-manager') . '</p>';
    return;
}

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
?>

<div class="cam-child-accounts-page">
    <h2><?php echo esc_html($company->company_name); ?> - <?php _e('Child Accounts Management', 'company-accounts-manager'); ?></h2>
    
    <p class="cam-description">
        <?php _e('Child accounts allow employees of your company to place orders on behalf of the company. You can create and manage child accounts here.', 'company-accounts-manager'); ?>
    </p>
    
    <!-- Child Accounts Management -->
    <div class="cam-section">
        <div class="cam-section-header">
            <h3><?php _e('Child Accounts', 'company-accounts-manager'); ?></h3>
            <button type="button" class="button button-primary" id="cam-add-child-account">
                <?php _e('Add Child Account', 'company-accounts-manager'); ?>
            </button>
        </div>

        <?php if (empty($child_accounts)) : ?>
            <p class="cam-no-data"><?php _e('No child accounts found. Use the button above to create your first child account.', 'company-accounts-manager'); ?></p>
        <?php else : ?>
            <div class="cam-table-container">
                <table class="cam-child-accounts-table">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'company-accounts-manager'); ?></th>
                            <th><?php _e('Email', 'company-accounts-manager'); ?></th>
                            <th><?php _e('Created', 'company-accounts-manager'); ?></th>
                            <th><?php _e('Status', 'company-accounts-manager'); ?></th>
                            <th><?php _e('Actions', 'company-accounts-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($child_accounts as $account) : 
                            // Get the user's full name
                            $first_name = get_user_meta($account->user_id, 'first_name', true);
                            $last_name = get_user_meta($account->user_id, 'last_name', true);
                            $full_name = trim("$first_name $last_name");
                            
                            // If no name is set, use display name
                            if (empty($full_name)) {
                                $full_name = $account->display_name;
                            }
                            
                            // If display name is email, use "Child Account" as fallback
                            if ($full_name === $account->user_email) {
                                $full_name = __('Child Account', 'company-accounts-manager') . ' #' . $account->id;
                            }
                            
                            // Get account status
                            $status = isset($account->status) ? $account->status : 'active';
                        ?>
                            <tr>
                                <td><?php echo esc_html($full_name); ?></td>
                                <td><?php echo esc_html($account->user_email); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($account->created_date))); ?></td>
                                <td>
                                    <?php if ($status === 'active') : ?>
                                        <span class="cam-status cam-status-active"><?php _e('Active', 'company-accounts-manager'); ?></span>
                                    <?php else : ?>
                                        <span class="cam-status cam-status-suspended"><?php _e('Suspended', 'company-accounts-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'active') : ?>
                                        <button type="button" class="button button-small suspend-child-account" data-user-id="<?php echo $account->user_id; ?>" data-action="suspend">
                                            <?php _e('Suspend', 'company-accounts-manager'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="button button-small suspend-child-account" data-user-id="<?php echo $account->user_id; ?>" data-action="activate">
                                            <?php _e('Activate', 'company-accounts-manager'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Child Account Modal -->
<div id="cam-add-child-modal" class="cam-modal" style="display: none;">
    <div class="cam-modal-content">
        <h2><?php _e('Add Child Account', 'company-accounts-manager'); ?></h2>
        <form id="cam-add-child-form">
            <p>
                <label for="cam-child-email"><?php _e('Email Address', 'company-accounts-manager'); ?> <span class="required">*</span></label>
                <input type="email" id="cam-child-email" name="email" class="regular-text" required>
            </p>
            <p>
                <label for="cam-child-first-name"><?php _e('First Name', 'company-accounts-manager'); ?></label>
                <input type="text" id="cam-child-first-name" name="first_name" class="regular-text">
            </p>
            <p>
                <label for="cam-child-last-name"><?php _e('Last Name', 'company-accounts-manager'); ?></label>
                <input type="text" id="cam-child-last-name" name="last_name" class="regular-text">
            </p>
            <div class="cam-modal-actions">
                <button type="submit" class="button button-primary">
                    <?php _e('Create Account', 'company-accounts-manager'); ?>
                </button>
                <button type="button" class="button cam-close-modal">
                    <?php _e('Cancel', 'company-accounts-manager'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.cam-child-accounts-page {
    margin: 20px 0;
}

.cam-description {
    margin-bottom: 20px;
}

.cam-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.cam-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.cam-section-header h3 {
    margin: 0;
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

.cam-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.cam-modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    width: 50%;
    max-width: 500px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.cam-modal-content form p {
    margin-bottom: 15px;
}

.cam-modal-content label {
    display: block;
    margin-bottom: 5px;
}

.cam-modal-content input {
    width: 100%;
}

.cam-modal-actions {
    margin-top: 20px;
    text-align: right;
}

.cam-modal-actions .button {
    margin-left: 10px;
}

.cam-table-container {
    overflow-x: auto;
    margin-top: 15px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Add Child Account
    $('#cam-add-child-account').click(function() {
        $('#cam-add-child-modal').show();
    });

    $('.cam-close-modal').click(function() {
        $('#cam-add-child-modal').hide();
        $('#cam-add-child-form')[0].reset();
    });

    $('#cam-add-child-form').submit(function(e) {
        e.preventDefault();
        
        var formData = {
            email: $('#cam-child-email').val(),
            first_name: $('#cam-child-first-name').val(),
            last_name: $('#cam-child-last-name').val(),
            action: 'cam_create_child_account',
            security: camCompanyAdmin.nonce
        };
        
        $.post(camCompanyAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert(response.data);
            }
        });
    });
    
    // Handle suspend/activate child account
    $('.suspend-child-account').click(function() {
        var userId = $(this).data('user-id');
        var action = $(this).data('action');
        var confirmMessage = action === 'suspend' ? 
            '<?php _e('Are you sure you want to suspend this account? The user will not be able to log in until reactivated.', 'company-accounts-manager'); ?>' : 
            '<?php _e('Are you sure you want to activate this account?', 'company-accounts-manager'); ?>';
            
        if (confirm(confirmMessage)) {
            $('#cam_child_account_id').val(userId);
            $('#cam_child_account_action').val(action);
            $('#cam_suspend_child_form').submit();
        }
    });
});
</script>

<!-- Hidden form for suspend/activate actions -->
<form id="cam_suspend_child_form" method="post" style="display: none;">
    <input type="hidden" name="action" value="cam_toggle_child_account_status">
    <input type="hidden" id="cam_child_account_id" name="child_account_id" value="">
    <input type="hidden" id="cam_child_account_action" name="child_account_action" value="">
    <?php wp_nonce_field('cam_toggle_child_account_status', 'cam_toggle_child_nonce'); ?>
</form> 