jQuery(document).ready(function($) {
    var mediaUploader;
    var currentDesign = null;
    
    // Add new design button
    $('.add-new-design').on('click', function() {
        currentDesign = null;
        resetDesignForm();
        $('.design-editor').show();
        $('.designs-list').hide();
    });
    
    // Edit design button
    $('.edit-design').on('click', function() {
        var designId = $(this).data('design-id');
        loadDesign(designId);
    });
    
    // Delete design button
    $('.delete-design').on('click', function() {
        var designId = $(this).data('design-id');
        if (confirm(giftCertificateDesigns.strings.confirm_delete)) {
            deleteDesign(designId);
        }
    });
    
    // Cancel edit button
    $('.cancel-edit').on('click', function() {
        $('.design-editor').hide();
        $('.designs-list').show();
        resetDesignForm();
    });
    
    // Email format change handler
    $('#email-format').on('change', function() {
        if (!currentDesign) {
            // Only change template for new designs
            var format = $(this).val();
            if (format === 'html') {
                $('#email-template').val(getDefaultHtmlTemplate());
            } else {
                $('#email-template').val(getDefaultPlainTextTemplate());
            }
        }
    });
    
    // Upload image button
    $('.upload-image').on('click', function() {
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select Gift Certificate Design Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#design-image-id').val(attachment.id);
            $('#design-image-preview').attr('src', attachment.url).show();
            $('.upload-image').hide();
            $('.remove-image').show();
        });
        
        mediaUploader.open();
    });
    
    // Remove image button
    $('.remove-image').on('click', function() {
        $('#design-image-id').val('');
        $('#design-image-preview').attr('src', '').hide();
        $('.upload-image').show();
        $('.remove-image').hide();
    });
    
    // Form submission
    $('#design-form').on('submit', function(e) {
        e.preventDefault();
        saveDesign();
    });
    
    function loadDesign(designId) {
        $.ajax({
            url: giftCertificateDesigns.ajax_url,
            type: 'POST',
            data: {
                action: 'get_gift_certificate_design',
                design_id: designId,
                nonce: giftCertificateDesigns.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentDesign = response.data;
                    populateDesignForm(currentDesign);
                    $('.design-editor').show();
                    $('.designs-list').hide();
                } else {
                    alert('Error loading design: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error loading design. Please try again.');
            }
        });
    }
    
    function populateDesignForm(design) {
        $('#design-id').val(design.id);
        $('#design-name').val(design.name);
        $('#design-image-id').val(design.image_id);
        $('#email-template').val(design.email_template);
        $('#custom-css').val(design.custom_css || '');
        $('#email-format').val(design.email_format);
        $('#design-active').prop('checked', design.active == 1);
        
        if (design.image_url) {
            $('#design-image-preview').attr('src', design.image_url).show();
            $('.upload-image').hide();
            $('.remove-image').show();
        } else {
            $('#design-image-preview').hide();
            $('.upload-image').show();
            $('.remove-image').hide();
        }
    }
    
    function resetDesignForm() {
        $('#design-form')[0].reset();
        $('#design-id').val('');
        $('#design-image-preview').hide();
        $('.upload-image').show();
        $('.remove-image').hide();
        $('#email-format').val('html'); // Set default format
        $('#design-active').prop('checked', true); // Set default to active
        
        // Set default email template for new designs
        $('#email-template').val(getDefaultHtmlTemplate());
        $('#custom-css').val(getDefaultCssTemplate());
        currentDesign = null;
    }
    
    function getDefaultHtmlTemplate() {
        return '<p>Dear <strong>{recipient_name}</strong>,</p>\n<p>You have received a beautiful gift certificate from <strong>{sender_name}</strong>!</p>\n\n<div class="gift-details">\n    <h3>Gift Certificate Details:</h3>\n    <div class="amount">${amount}</div>\n    <div class="coupon-code">{coupon_code}</div>\n</div>\n\n<div class="message">\n    <strong>Message from {sender_name}:</strong><br>\n    {message}\n</div>\n\n<p>You can use this gift certificate on our website. Simply enter the coupon code during checkout to apply your discount.</p>\n\n<p style="text-align: center;">\n    <a href="{balance_check_url}" class="button">Check Balance</a>\n</p>';
    }
    
    function getDefaultPlainTextTemplate() {
        return 'Dear {recipient_name},\n\n' +
               'You have received a beautiful gift certificate from {sender_name}!\n\n' +
               'Gift Certificate Details:\n' +
               'Amount: ${amount}\n' +
               'Code: {coupon_code}\n\n' +
               'Message from {sender_name}:\n{message}\n\n' +
               'You can use this gift certificate on our website. Simply enter the coupon code during checkout to apply your discount.\n\n' +
               'You can check your balance at any time at: {balance_check_url}\n\n' +
               'Thank you for choosing {site_name}!\n\n' +
               '{site_name}\n' +
               '{site_url}';
    }
    
    function getDefaultCssTemplate() {
        return '/* Reset styles */\nbody, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }\ntable, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }\nimg { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }\n\n/* Base styles */\nbody { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #333333; background-color: #f4f4f4; }\n\n/* Container */\n.email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }\n\n/* Header */\n.header { background-color: #6c757d; color: #ffffff; padding: 30px 20px; text-align: center; }\n.header h1 { margin: 0; font-size: 28px; font-weight: bold; }\n\n/* Content */\n.content { padding: 30px 20px; }\n.content p { margin: 0 0 15px 0; }\n\n/* Gift details */\n.gift-details { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #6c757d; }\n.gift-details h3 { margin: 0 0 15px 0; color: #333333; }\n\n/* Amount */\n.amount { font-size: 32px; font-weight: bold; color: #6c757d; text-align: center; margin: 15px 0; }\n\n/* Coupon code */\n.coupon-code { font-size: 24px; font-weight: bold; color: #6c757d; text-align: center; padding: 15px; background-color: #e9ecef; margin: 15px 0; }\n\n/* Message */\n.message { font-style: italic; margin: 20px 0; padding: 20px; background-color: #fff3e0; border-left: 4px solid #ff9800; }\n\n/* Button */\n.button { display: inline-block; padding: 12px 24px; background-color: #6c757d; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }\n\n/* Footer */\n.footer { text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }\n\n/* Responsive */\n@media only screen and (max-width: 600px) {\n    .email-container { width: 100% !important; }\n    .content { padding: 20px 15px !important; }\n    .header { padding: 20px 15px !important; }\n    .header h1 { font-size: 24px !important; }\n    .amount { font-size: 28px !important; }\n    .coupon-code { font-size: 20px !important; }\n}';
    }
    
    function saveDesign() {
        var formData = {
            action: 'save_gift_certificate_design',
            nonce: giftCertificateDesigns.nonce,
            design_id: $('#design-id').val(),
            design_name: $('#design-name').val(),
            design_image_id: $('#design-image-id').val(),
            design_image_url: $('#design-image-preview').attr('src') || '',
            email_template: $('#email-template').val(),
            custom_css: $('#custom-css').val(),
            email_format: $('#email-format').val(),
            design_active: $('#design-active').is(':checked') ? 1 : 0
        };
        
        $('.submit button').prop('disabled', true).text(giftCertificateDesigns.strings.saving);
        
        $.ajax({
            url: giftCertificateDesigns.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert(giftCertificateDesigns.strings.saved);
                    location.reload(); // Reload to show updated designs
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert(giftCertificateDesigns.strings.error);
            },
            complete: function() {
                $('.submit button').prop('disabled', false).text('Save Design');
            }
        });
    }

    // Event listener for the 'Send Test Email' buttons
    $('.send-test-email').on('click', function () {
        var designId = $(this).data('design-id');
        var emailAddress = prompt('Please enter the email address to send the test email to:');

        if (emailAddress) {
            $.post(giftCertificateDesigns.ajax_url, {
                action: 'send_test_email',
                design_id: designId,
                email: emailAddress,
                nonce: giftCertificateDesigns.nonce
            })
            .done(function (response) {
                alert(response.data.message);
            })
            .fail(function () {
                alert('An error occurred while sending the test email.');
            });
        } else {
            alert('Email address is required to send a test email.');
        }
    });
    
    function deleteDesign(designId) {
        $.ajax({
            url: giftCertificateDesigns.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_gift_certificate_design',
                design_id: designId,
                nonce: giftCertificateDesigns.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Design deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error deleting design. Please try again.');
            }
        });
    }
}); 