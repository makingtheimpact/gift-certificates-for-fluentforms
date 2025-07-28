/**
 * Gift Certificate Balance Check JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize balance check functionality
    function initBalanceCheck() {
        // Create balance check form if it doesn't exist
        if ($('#gift-certificate-balance-check').length === 0) {
            createBalanceCheckForm();
        }
        
        // Bind events
        bindEvents();
    }
    
    // Create balance check form
    function createBalanceCheckForm() {
        const formHtml = `
            <div id="gift-certificate-balance-check" class="gift-certificate-balance-form">
                <h3>Check Gift Certificate Balance</h3>
                <div class="balance-form-fields">
                    <input type="text" id="gift-certificate-code" placeholder="Enter your gift certificate code" maxlength="10">
                    <button type="button" id="check-balance-btn" class="balance-check-button">Check Balance</button>
                </div>
                <div id="balance-result" class="balance-result"></div>
            </div>
        `;
        
        // Insert form into page
        $('body').append(formHtml);
    }
    
    // Bind events
    function bindEvents() {
        // Check balance button click
        $(document).on('click', '#check-balance-btn', function() {
            checkBalance();
        });
        
        // Enter key press on input
        $(document).on('keypress', '#gift-certificate-code', function(e) {
            if (e.which === 13) {
                checkBalance();
            }
        });
        
        // Auto-format coupon code
        $(document).on('input', '#gift-certificate-code', function() {
            formatCouponCode(this);
        });
    }
    
    // Format coupon code input
    function formatCouponCode(input) {
        let value = input.value.toUpperCase();
        
        // Remove any non-alphanumeric characters
        value = value.replace(/[^A-Z0-9]/g, '');
        
        // Limit to 10 characters
        value = value.substring(0, 10);
        
        input.value = value;
    }
    
    // Check balance function
    function checkBalance() {
        const code = $('#gift-certificate-code').val().trim();
        const resultDiv = $('#balance-result');
        const button = $('#check-balance-btn');
        
        // Validate input
        if (!code) {
            showResult('Please enter a gift certificate code.', 'error');
            return;
        }
        
        if (!code.match(/^GC[A-Z0-9]{8}$/)) {
            showResult('Please enter a valid gift certificate code (format: GCXXXXXXXX).', 'error');
            return;
        }
        
        // Show loading state
        button.prop('disabled', true).text('Checking...');
        resultDiv.html('<div class="loading">Checking balance...</div>');
        
        // Make API request
        $.ajax({
            url: giftCertificateAPI.restUrl + '/balance',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': giftCertificateAPI.nonce
            },
            data: JSON.stringify({ code: code }),
            success: function(response) {
                if (response.balance !== undefined) {
                    showBalanceResult(response);
                } else {
                    showResult('Invalid or expired gift certificate.', 'error');
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while checking the balance.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                showResult(errorMessage, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text('Check Balance');
            }
        });
    }
    
    // Show balance result
    function showBalanceResult(data) {
        const resultHtml = `
            <div class="balance-success">
                <div class="balance-header">
                    <h4>Gift Certificate Balance</h4>
                </div>
                <div class="balance-details">
                    <div class="balance-row">
                        <span class="label">Current Balance:</span>
                        <span class="value">$${parseFloat(data.balance).toFixed(2)}</span>
                    </div>
                    <div class="balance-row">
                        <span class="label">Original Amount:</span>
                        <span class="value">$${parseFloat(data.original_amount).toFixed(2)}</span>
                    </div>
                    <div class="balance-row">
                        <span class="label">Status:</span>
                        <span class="value status-${data.status}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span>
                    </div>
                    ${data.recipient_name ? `
                    <div class="balance-row">
                        <span class="label">Recipient:</span>
                        <span class="value">${data.recipient_name}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        $('#balance-result').html(resultHtml);
    }
    
    // Show result message
    function showResult(message, type) {
        const resultHtml = `<div class="balance-${type}">${message}</div>`;
        $('#balance-result').html(resultHtml);
    }
    
    // Public API
    window.GiftCertificateBalanceCheck = {
        checkBalance: checkBalance,
        init: initBalanceCheck
    };
    
    // Auto-initialize if DOM is ready
    $(document).ready(function() {
        initBalanceCheck();
    });
    
})(jQuery);

// Shortcode support
jQuery(document).ready(function($) {
    // Handle shortcode initialization
    $('.gift-certificate-balance-shortcode').each(function() {
            const $container = $(this);
            
            // Create form in shortcode container
            const formHtml = `
                <div class="gift-certificate-balance-form">
                    <h3>Check Gift Certificate Balance</h3>
                    <div class="balance-form-fields">
                        <input type="text" class="gift-certificate-code" placeholder="Enter your gift certificate code" maxlength="10">
                        <button type="button" class="balance-check-button">Check Balance</button>
                    </div>
                    <div class="balance-result"></div>
                </div>
            `;
            
            $container.html(formHtml);
            
            // Bind events for this instance
            $container.find('.balance-check-button').on('click', function() {
                const code = $container.find('.gift-certificate-code').val().trim();
                const resultDiv = $container.find('.balance-result');
                const button = $(this);
                
                if (!code) {
                    resultDiv.html('<div class="balance-error">Please enter a gift certificate code.</div>');
                    return;
                }
                
                if (!code.match(/^GC[A-Z0-9]{8}$/)) {
                    resultDiv.html('<div class="balance-error">Please enter a valid gift certificate code (format: GCXXXXXXXX).</div>');
                    return;
                }
                
                button.prop('disabled', true).text('Checking...');
                resultDiv.html('<div class="loading">Checking balance...</div>');
                
                $.ajax({
                    url: giftCertificateAPI.restUrl + '/balance',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': giftCertificateAPI.nonce
                    },
                    data: JSON.stringify({ code: code }),
                    success: function(response) {
                        if (response.balance !== undefined) {
                            const resultHtml = `
                                <div class="balance-success">
                                    <div class="balance-header">
                                        <h4>Gift Certificate Balance</h4>
                                    </div>
                                    <div class="balance-details">
                                        <div class="balance-row">
                                            <span class="label">Current Balance:</span>
                                            <span class="value">$${parseFloat(response.balance).toFixed(2)}</span>
                                        </div>
                                        <div class="balance-row">
                                            <span class="label">Original Amount:</span>
                                            <span class="value">$${parseFloat(response.original_amount).toFixed(2)}</span>
                                        </div>
                                        <div class="balance-row">
                                            <span class="label">Status:</span>
                                            <span class="value status-${response.status}">${response.status.charAt(0).toUpperCase() + response.status.slice(1)}</span>
                                        </div>
                                        ${response.recipient_name ? `
                                        <div class="balance-row">
                                            <span class="label">Recipient:</span>
                                            <span class="value">${response.recipient_name}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                            resultDiv.html(resultHtml);
                        } else {
                            resultDiv.html('<div class="balance-error">Invalid or expired gift certificate.</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="balance-error">An error occurred while checking the balance.</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Check Balance');
                    }
                });
            });
            
            // Auto-format coupon code
            $container.find('.gift-certificate-code').on('input', function() {
                let value = this.value.toUpperCase();
                value = value.replace(/[^A-Z0-9]/g, '');
                value = value.substring(0, 10);
                this.value = value;
            });
        });
    }); 