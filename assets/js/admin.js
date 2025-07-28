/**
 * Gift Certificates for Fluent Forms - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Test webhook functionality
        $('#test-webhook').on('click', function() {
            const button = $(this);
            const resultDiv = $('#webhook-test-result');
            
            button.prop('disabled', true).text('Testing...');
            resultDiv.removeClass('success error').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gift_certificate_admin_ajax',
                    nonce: giftCertificateAdmin.nonce,
                    sub_action: 'test_webhook'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.addClass('success').html(response.data).show();
                    } else {
                        resultDiv.addClass('error').html(response.data).show();
                    }
                },
                error: function() {
                    resultDiv.addClass('error').html('An error occurred while testing the webhook.').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Webhook Connection');
                }
            });
        });
        
        // Debug form fields functionality
        $('#debug-form-fields').on('click', function() {
            const button = $(this);
            const resultDiv = $('#form-fields-debug');
            const formId = $('select[name="gift_certificates_ff_settings[gift_certificate_form_id]"]').val();
            
            if (!formId) {
                resultDiv.addClass('error').html('Please select a form first.').show();
                return;
            }
            
            button.prop('disabled', true).text('Debugging...');
            resultDiv.removeClass('success error').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gift_certificate_admin_ajax',
                    nonce: giftCertificateAdmin.nonce,
                    sub_action: 'debug_form_fields',
                    form_id: formId
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.addClass('success').html('<pre>' + response.data + '</pre>').show();
                    } else {
                        resultDiv.addClass('error').html(response.data).show();
                    }
                },
                error: function() {
                    resultDiv.addClass('error').html('An error occurred while debugging form fields.').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Debug Form Fields');
                }
            });
        });
        
        // Test email functionality
        $('#test-email').on('click', function() {
            const button = $(this);
            const resultDiv = $('#test-email-result');
            const emailAddress = $('#test-email-address').val();
            
            if (!emailAddress) {
                resultDiv.addClass('error').html('Please enter an email address.').show();
                return;
            }
            
            if (!isValidEmail(emailAddress)) {
                resultDiv.addClass('error').html('Please enter a valid email address.').show();
                return;
            }
            
            button.prop('disabled', true).text('Sending...');
            resultDiv.removeClass('success error').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gift_certificate_admin_ajax',
                    nonce: giftCertificateAdmin.nonce,
                    sub_action: 'test_email',
                    email_address: emailAddress
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.addClass('success').html(response.data).show();
                    } else {
                        resultDiv.addClass('error').html(response.data).show();
                    }
                },
                error: function() {
                    resultDiv.addClass('error').html('An error occurred while sending the test email.').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Send Test Email');
                }
            });
        });
        
        // Certificate actions
        $('.gift-certificate-action').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const action = button.data('action');
            const certificateId = button.data('certificate-id');
            const row = button.closest('tr');
            
            if (!confirm('Are you sure you want to perform this action?')) {
                return;
            }
            
            button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gift_certificate_admin_ajax',
                    nonce: giftCertificateAdmin.nonce,
                    sub_action: action,
                    certificate_id: certificateId
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated data
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while performing the action.');
                    button.prop('disabled', false);
                }
            });
        });
        
        // Bulk actions
        $('#doaction, #doaction2').on('click', function(e) {
            const action = $(this).prev('select').val();
            const checkedBoxes = $('.gift-certificate-checkbox:checked');
            
            if (action === '' || action === '-1') {
                e.preventDefault();
                alert('Please select an action.');
                return;
            }
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one certificate.');
                return;
            }
            
            if (!confirm('Are you sure you want to perform this action on the selected certificates?')) {
                e.preventDefault();
                return;
            }
        });
        
        // Select all functionality
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.gift-certificate-checkbox').prop('checked', isChecked);
        });
        
        // Helper function to validate email
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    });
    
})(jQuery); 