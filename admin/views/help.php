<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('How to Use Gift Certificates for Fluent Forms', 'gift-certificates-fluentforms'); ?></h1>
    
    <div class="gift-certificate-help">
        
        <div class="help-section">
            <h2><?php _e('Setup Instructions', 'gift-certificates-fluentforms'); ?></h2>
            
            <h3><?php _e('Step 1: Create a Fluent Forms Form', 'gift-certificates-fluentforms'); ?></h3>
            <ol>
                <li><?php _e('Go to Fluent Forms → All Forms', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Create a new form or use an existing one', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Add the following fields to your form:', 'gift-certificates-fluentforms'); ?>
                    <ul>
                        <li><strong><?php _e('Amount Field:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Number or input field for gift certificate amount', 'gift-certificates-fluentforms'); ?></li>
                        <li><strong><?php _e('Recipient Email:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Email field for the gift certificate recipient', 'gift-certificates-fluentforms'); ?></li>
                        <li><strong><?php _e('Recipient Name:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Text field for recipient name', 'gift-certificates-fluentforms'); ?></li>
                        <li><strong><?php _e('Sender Name:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Text field for sender name', 'gift-certificates-fluentforms'); ?></li>
                        <li><strong><?php _e('Message:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Textarea for personal message (optional)', 'gift-certificates-fluentforms'); ?></li>
                        <li><strong><?php _e('Delivery Date:', 'gift-certificates-fluentforms'); ?></strong> <?php _e('Date field for scheduled delivery (optional)', 'gift-certificates-fluentforms'); ?></li>
                    </ul>
                </li>
                <li><?php _e('Add payment integration (Stripe, PayPal, etc.) to handle the purchase', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Save the form and note the Form ID', 'gift-certificates-fluentforms'); ?></li>
            </ol>
            
            <h3><?php _e('Step 2: Configure Plugin Settings', 'gift-certificates-fluentforms'); ?></h3>
            <ol>
                <li><?php _e('Go to Gift Certificates → Settings', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Select your gift certificate form from the dropdown', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Map the form fields to the corresponding gift certificate fields', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Configure email settings and template', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Save settings', 'gift-certificates-fluentforms'); ?></li>
            </ol>
        </div>
        
        <div class="help-section">
            <h2><?php _e('How It Works', 'gift-certificates-fluentforms'); ?></h2>
            
            <h3><?php _e('Purchase Process', 'gift-certificates-fluentforms'); ?></h3>
            <ol>
                <li><?php _e('Customer fills out the gift certificate form', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Payment is processed through Fluent Forms payment integration', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Form submission triggers the webhook', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Plugin creates a gift certificate record in the database', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Plugin creates a Fluent Forms Pro coupon code', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Gift certificate email is sent to recipient (immediate or scheduled)', 'gift-certificates-fluentforms'); ?></li>
            </ol>
            
            <h3><?php _e('Redemption Process', 'gift-certificates-fluentforms'); ?></h3>
            <ol>
                <li><?php _e('Recipient receives email with coupon code', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Recipient uses coupon code in any Fluent Forms form', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Plugin validates the coupon and checks balance', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Discount is applied to the order', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Gift certificate balance is updated', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Coupon is deactivated when balance reaches zero', 'gift-certificates-fluentforms'); ?></li>
            </ol>
        </div>
        
        <div class="help-section">
            <h2><?php _e('Balance Checking', 'gift-certificates-fluentforms'); ?></h2>
            
            <h3><?php _e('API Endpoints', 'gift-certificates-fluentforms'); ?></h3>
            <p><?php _e('The plugin provides REST API endpoints for balance checking:', 'gift-certificates-fluentforms'); ?></p>
            
            <ul>
                <li><strong>GET:</strong> <code><?php echo rest_url('gift-certificates/v1/balance/{code}'); ?></code></li>
                <li><strong>POST:</strong> <code><?php echo rest_url('gift-certificates/v1/balance'); ?></code></li>
            </ul>
            
            <h3><?php _e('JavaScript Integration', 'gift-certificates-fluentforms'); ?></h3>
            <p><?php _e('Use the provided JavaScript to create a balance check form:', 'gift-certificates-fluentforms'); ?></p>
            
            <pre><code>&lt;div id="gift-certificate-balance-check"&gt;
    &lt;input type="text" id="coupon-code" placeholder="Enter gift certificate code"&gt;
    &lt;button onclick="checkBalance()"&gt;Check Balance&lt;/button&gt;
    &lt;div id="balance-result"&gt;&lt;/div&gt;
&lt;/div&gt;

&lt;script&gt;
function checkBalance() {
    const code = document.getElementById('coupon-code').value;
    const resultDiv = document.getElementById('balance-result');
    
    fetch('<?php echo rest_url('gift-certificates/v1/balance'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
        },
        body: JSON.stringify({ code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.balance !== undefined) {
            resultDiv.innerHTML = `Balance: $${data.balance}`;
        } else {
            resultDiv.innerHTML = 'Invalid or expired gift certificate';
        }
    });
}
&lt;/script&gt;</code></pre>
        </div>
        
        <div class="help-section">
            <h2><?php _e('Shortcodes', 'gift-certificates-fluentforms'); ?></h2>
            
            <h3><?php _e('Balance Check Shortcode', 'gift-certificates-fluentforms'); ?></h3>
            <p><?php _e('Use this shortcode to display a balance check form on any page:', 'gift-certificates-fluentforms'); ?></p>
            <code>[gift_certificate_balance_check]</code>
            
            <h4><?php _e('Available Options:', 'gift-certificates-fluentforms'); ?></h4>
            <ul>
                <li><code>title</code> - <?php _e('Custom title for the form', 'gift-certificates-fluentforms'); ?></li>
                <li><code>placeholder</code> - <?php _e('Custom placeholder text', 'gift-certificates-fluentforms'); ?></li>
                <li><code>button_text</code> - <?php _e('Custom button text', 'gift-certificates-fluentforms'); ?></li>
                <li><code>show_instructions</code> - <?php _e('Show/hide instructions (true/false)', 'gift-certificates-fluentforms'); ?></li>
            </ul>
            
            <h4><?php _e('Example:', 'gift-certificates-fluentforms'); ?></h4>
            <code>[gift_certificate_balance_check title="Check Your Balance" button_text="Check Now" show_instructions="false"]</code>
            
            <h3><?php _e('Gift Certificate Purchase Form', 'gift-certificates-fluentforms'); ?></h3>
            <p><?php _e('Display your Fluent Forms gift certificate form:', 'gift-certificates-fluentforms'); ?></p>
            <code>[gift_certificate_purchase_form form_id="YOUR_FORM_ID"]</code>
            <p class="description"><?php _e('If no form_id is specified, the form ID from settings will be used.', 'gift-certificates-fluentforms'); ?></p>
        </div>
        
        <div class="help-section">
            <h2><?php _e('Troubleshooting', 'gift-certificates-fluentforms'); ?></h2>
            
            <h3><?php _e('Common Issues', 'gift-certificates-fluentforms'); ?></h3>
            
            <h4><?php _e('Gift certificates not being created', 'gift-certificates-fluentforms'); ?></h4>
            <ul>
                <li><?php _e('Check that the form ID is correctly set in settings', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Verify field mapping matches your form field names', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Check WordPress error logs for any PHP errors', 'gift-certificates-fluentforms'); ?></li>
            </ul>
            
            <h4><?php _e('Coupons not working', 'gift-certificates-fluentforms'); ?></h4>
            <ul>
                <li><?php _e('Ensure Fluent Forms Pro is installed and activated', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Check that coupon functionality is enabled in Fluent Forms Pro', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Verify the coupon code format (should start with GC)', 'gift-certificates-fluentforms'); ?></li>
            </ul>
            
            <h4><?php _e('Emails not sending', 'gift-certificates-fluentforms'); ?></h4>
            <ul>
                <li><?php _e('Check WordPress email configuration', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Verify email template settings', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Test email functionality using the Test Email button', 'gift-certificates-fluentforms'); ?></li>
            </ul>
        </div>
        
        <div class="help-section">
            <h2><?php _e('Support', 'gift-certificates-fluentforms'); ?></h2>
            
            <p><?php _e('For additional support:', 'gift-certificates-fluentforms'); ?></p>
            <ul>
                <li><?php _e('Check the WordPress error logs for detailed error messages', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Ensure all required plugins are installed and up to date', 'gift-certificates-fluentforms'); ?></li>
                <li><?php _e('Test with a default WordPress theme to rule out theme conflicts', 'gift-certificates-fluentforms'); ?></li>
            </ul>
        </div>
        
    </div>
</div>

<style>
.gift-certificate-help {
    max-width: 1200px;
}

.help-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin: 20px 0;
}

.help-section h2 {
    color: #0073aa;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.help-section h3 {
    color: #333;
    margin-top: 20px;
}

.help-section h4 {
    color: #666;
    margin-top: 15px;
}

.help-section ol, .help-section ul {
    margin-left: 20px;
}

.help-section li {
    margin-bottom: 5px;
}

.help-section code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}

.help-section pre {
    background: #f1f1f1;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
    margin: 10px 0;
}

.help-section pre code {
    background: none;
    padding: 0;
}
</style> 