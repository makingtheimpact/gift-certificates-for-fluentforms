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
        currentDesign = null;
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