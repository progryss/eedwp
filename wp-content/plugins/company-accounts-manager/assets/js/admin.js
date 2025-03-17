jQuery(document).ready(function($) {
    // Approve company
    $('.cam-approve-company').click(function() {
        var button = $(this);
        var companyId = button.data('company-id');
        
        if (confirm(camAdmin.i18n.confirmApprove)) {
            button.prop('disabled', true);
            
            $.post(camAdmin.ajaxurl, {
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
            alert(camAdmin.i18n.provideReason);
            return;
        }

        var button = $(this);
        button.prop('disabled', true);

        $.post(camAdmin.ajaxurl, {
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

    // View company info
    $('.cam-view-info').click(function() {
        var info = $(this).data('info');
        $('#cam-company-info-content').text(info);
        $('#cam-info-modal').show();
    });

    // Close modals when clicking outside
    $('.cam-modal').click(function(e) {
        if (e.target === this) {
            $(this).hide();
            $('#cam-rejection-reason').val('');
            currentCompanyId = null;
        }
    });

    // Close modals with Escape key
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('.cam-modal').hide();
            $('#cam-rejection-reason').val('');
            currentCompanyId = null;
        }
    });

    // Initialize datepicker if available
    if ($.fn.datepicker) {
        $('.cam-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            maxDate: '0'
        });
    }

    // Export functionality
    $('#cam-export-orders').click(function(e) {
        e.preventDefault();
        var companyId = $(this).data('company-id');
        var startDate = $('#cam-export-start-date').val();
        var endDate = $('#cam-export-end-date').val();

        window.location.href = camAdmin.ajaxurl + '?' + $.param({
            action: 'cam_export_orders',
            company_id: companyId,
            start_date: startDate,
            end_date: endDate,
            nonce: camAdmin.nonce
        });
    });
}); 