<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['gift_certificates_ff_nonce'], 'gift_certificates_ff_settings')) {
    $input = $_POST['gift_certificates_ff_settings'];
    
    // Sanitize the input
    $sanitized = array();
    $sanitized['gift_certificate_form_id'] = intval($input['gift_certificate_form_id']);
    $sanitized['amount_field_name'] = sanitize_text_field($input['amount_field_name']);
    $sanitized['recipient_email_field_name'] = sanitize_text_field($input['recipient_email_field_name']);
    $sanitized['recipient_name_field_name'] = sanitize_text_field($input['recipient_name_field_name']);
    $sanitized['sender_name_field_name'] = sanitize_text_field($input['sender_name_field_name']);
    $sanitized['message_field_name'] = sanitize_text_field($input['message_field_name']);
    $sanitized['delivery_date_field_name'] = sanitize_text_field($input['delivery_date_field_name']);
    $sanitized['balance_check_page_id'] = intval($input['balance_check_page_id']);
    $sanitized['email_template'] = wp_kses_post($input['email_template']);
    $sanitized['email_format'] = sanitize_text_field($input['email_format']);
    
    $updated = update_option('gift_certificates_ff_settings', $sanitized);
    if ($updated) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'gift-certificates-fluentforms') . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Error saving settings. Please try again.', 'gift-certificates-fluentforms') . '</p></div>';
    }
}

$settings = get_option('gift_certificates_ff_settings', array());
?>

<div class="wrap">
    <h1><?php _e('Gift Certificate Settings', 'gift-certificates-fluentforms'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('gift_certificates_ff_settings', 'gift_certificates_ff_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Gift Certificate Form ID', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <select name="gift_certificates_ff_settings[gift_certificate_form_id]">
                        <option value=""><?php _e('Select a form', 'gift-certificates-fluentforms'); ?></option>
                        <?php
                        if (class_exists('wpFluent')) {
                            $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();
                            foreach ($forms as $form) {
                                $selected = selected($settings['gift_certificate_form_id'] ?? '', $form->id, false);
                                echo '<option value="' . esc_attr($form->id) . '" ' . $selected . '>' . esc_html($form->title) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the Fluent Forms form that will be used for gift certificate purchases.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Amount Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[amount_field_name]" value="<?php echo esc_attr($settings['amount_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the gift certificate amount.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Recipient Email Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[recipient_email_field_name]" value="<?php echo esc_attr($settings['recipient_email_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the recipient email.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Recipient Name Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[recipient_name_field_name]" value="<?php echo esc_attr($settings['recipient_name_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the recipient name.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Sender Name Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[sender_name_field_name]" value="<?php echo esc_attr($settings['sender_name_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the sender name.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Message Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[message_field_name]" value="<?php echo esc_attr($settings['message_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the personal message.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Delivery Date Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[delivery_date_field_name]" value="<?php echo esc_attr($settings['delivery_date_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the delivery date (optional).', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Balance Check Page', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <select name="gift_certificates_ff_settings[balance_check_page_id]">
                        <option value=""><?php _e('Select a page...', 'gift-certificates-fluentforms'); ?></option>
                        <?php
                        $pages = get_pages(array(
                            'sort_column' => 'post_title',
                            'sort_order' => 'ASC'
                        ));
                        foreach ($pages as $page) {
                            $selected = selected($settings['balance_check_page_id'] ?? '', $page->ID, false);
                            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the page where users can check their gift certificate balance. You can use the shortcode [gift_certificate_balance_check] on this page.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Email Template', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <textarea name="gift_certificates_ff_settings[email_template]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($settings['email_template'] ?? ''); ?></textarea>
                    <p class="description"><?php _e('Available placeholders: {recipient_name}, {sender_name}, {amount}, {coupon_code}, {message}, {site_name}, {site_url}, {balance_check_url}', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Email Format', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <select name="gift_certificates_ff_settings[email_format]">
                        <option value="text" <?php selected($settings['email_format'] ?? 'text', 'text'); ?>><?php _e('Plain Text', 'gift-certificates-fluentforms'); ?></option>
                        <option value="html" <?php selected($settings['email_format'] ?? 'text', 'html'); ?>><?php _e('HTML', 'gift-certificates-fluentforms'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <div class="gift-certificate-debug-section">
        <h3><?php _e('Debug Information', 'gift-certificates-fluentforms'); ?></h3>
        <p><?php _e('Use this section to test and debug the gift certificate functionality.', 'gift-certificates-fluentforms'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Test Webhook', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <button type="button" id="test-webhook" class="button"><?php _e('Test Webhook Connection', 'gift-certificates-fluentforms'); ?></button>
                    <p class="description"><?php _e('Click this button to test if the webhook is properly connected to Fluent Forms.', 'gift-certificates-fluentforms'); ?></p>
                    <div id="webhook-test-result"></div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Form Field Debug', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <button type="button" id="debug-form-fields" class="button"><?php _e('Debug Form Fields', 'gift-certificates-fluentforms'); ?></button>
                    <p class="description"><?php _e('Click this button to see the actual field names from your selected form.', 'gift-certificates-fluentforms'); ?></p>
                    <div id="form-fields-debug"></div>
                </td>
            </tr>
        </table>
    </div>
    
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

.gift-certificate-debug-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin-top: 30px;
}

.gift-certificate-debug-section h3 {
    color: #0073aa;
    margin-top: 0;
}

#webhook-test-result,
#form-fields-debug {
    margin-top: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
}

#form-fields-debug pre {
    margin: 0;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 12px;
}
</style> 