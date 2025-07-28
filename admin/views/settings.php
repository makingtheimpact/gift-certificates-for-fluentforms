<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Gift Certificate Settings', 'gift-certificates-fluentforms'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('gift_certificates_ff_settings');
        do_settings_sections('gift_certificates_ff_settings');
        submit_button();
        ?>
    </form>
    
    <div class="gift-certificate-settings-help">
        <h3><?php _e('Field Mapping Instructions', 'gift-certificates-fluentforms'); ?></h3>
        <p><?php _e('Enter the exact field names from your Fluent Forms form. You can find these in the form builder under each field\'s settings.', 'gift-certificates-fluentforms'); ?></p>
        
        <h3><?php _e('Email Template Variables', 'gift-certificates-fluentforms'); ?></h3>
        <p><?php _e('You can use these variables in your email template:', 'gift-certificates-fluentforms'); ?></p>
        <ul>
            <li><code>{recipient_name}</code> - <?php _e('Recipient\'s name', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{sender_name}</code> - <?php _e('Sender\'s name', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{amount}</code> - <?php _e('Gift certificate amount', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{coupon_code}</code> - <?php _e('Gift certificate coupon code', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{message}</code> - <?php _e('Personal message from sender', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{site_name}</code> - <?php _e('Website name', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{site_url}</code> - <?php _e('Website URL', 'gift-certificates-fluentforms'); ?></li>
            <li><code>{balance_check_url}</code> - <?php _e('URL to check gift certificate balance', 'gift-certificates-fluentforms'); ?></li>
        </ul>
    </div>
</div>

<style>
.gift-certificate-settings-help {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin-top: 30px;
}

.gift-certificate-settings-help h3 {
    color: #0073aa;
    margin-top: 0;
}

.gift-certificate-settings-help ul {
    margin-left: 20px;
}

.gift-certificate-settings-help code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}
</style> 