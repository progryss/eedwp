<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php _e('Pending Company Registrations', 'company-accounts-manager'); ?></h1>

    <?php if (empty($pending_companies)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No pending company registrations at this time.', 'company-accounts-manager'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Company Name', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Admin', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Email', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Registration Date', 'company-accounts-manager'); ?></th>
                    <th><?php _e('Actions', 'company-accounts-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_companies as $company) : ?>
                    <tr>
                        <td><?php echo esc_html($company->company_name); ?></td>
                        <td><?php echo esc_html($company->display_name); ?></td>
                        <td><?php echo esc_html($company->user_email); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($company->registration_date))); ?></td>
                        <td>
                            <button type="button" class="button button-primary cam-approve-company" data-company-id="<?php echo esc_attr($company->id); ?>">
                                <?php _e('Approve', 'company-accounts-manager'); ?>
                            </button>
                            <button type="button" class="button cam-reject-company" data-company-id="<?php echo esc_attr($company->id); ?>">
                                <?php _e('Reject', 'company-accounts-manager'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Rejection Modal -->
        <div id="cam-reject-modal" class="cam-modal" style="display: none;">
            <div class="cam-modal-content">
                <h2><?php _e('Reject Company Registration', 'company-accounts-manager'); ?></h2>
                <p>
                    <label for="cam-rejection-reason"><?php _e('Reason for rejection:', 'company-accounts-manager'); ?></label>
                    <textarea id="cam-rejection-reason" rows="4" class="large-text"></textarea>
                </p>
                <div class="cam-modal-actions">
                    <button type="button" class="button button-primary" id="cam-confirm-reject">
                        <?php _e('Confirm Rejection', 'company-accounts-manager'); ?>
                    </button>
                    <button type="button" class="button cam-close-modal">
                        <?php _e('Cancel', 'company-accounts-manager'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
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

.cam-modal-actions {
    margin-top: 20px;
    text-align: right;
}

.cam-modal-actions .button {
    margin-left: 10px;
}

.cam-approve-company,
.cam-reject-company {
    margin-right: 5px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Approve company
    $('.cam-approve-company').click(function() {
        var button = $(this);
        var companyId = button.data('company-id');
        
        if (confirm('<?php _e('Are you sure you want to approve this company?', 'company-accounts-manager'); ?>')) {
            button.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'cam_approve_company',
                company_id: companyId,
                nonce: camAdmin.nonce
            }, function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut();
                } else {
                    alert(response.data);
                    button.prop('disabled', false);
                }
            });
        }
    });

    // Reject company
    var currentCompanyId = null;
    
    $('.cam-reject-company').click(function() {
        currentCompanyId = $(this).data('company-id');
        $('#cam-reject-modal').show();
    });

    $('.cam-close-modal').click(function() {
        $('#cam-reject-modal').hide();
        $('#cam-rejection-reason').val('');
        currentCompanyId = null;
    });

    $('#cam-confirm-reject').click(function() {
        var reason = $('#cam-rejection-reason').val();
        
        if (!reason) {
            alert('<?php _e('Please provide a reason for rejection.', 'company-accounts-manager'); ?>');
            return;
        }

        var button = $(this);
        button.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'cam_reject_company',
            company_id: currentCompanyId,
            reason: reason,
            nonce: camAdmin.nonce
        }, function(response) {
            if (response.success) {
                $('button[data-company-id="' + currentCompanyId + '"]').closest('tr').fadeOut();
                $('#cam-reject-modal').hide();
                $('#cam-rejection-reason').val('');
            } else {
                alert(response.data);
            }
            button.prop('disabled', false);
        });
    });
});
</script> 