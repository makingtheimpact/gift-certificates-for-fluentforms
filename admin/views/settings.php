<?php
if (!defined('ABSPATH')) {
    exit;
}

// Load plugin functions if not already loaded
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
    $sanitized['design_field_name'] = sanitize_text_field($input['design_field_name']);
    $sanitized['balance_check_page_id'] = intval($input['balance_check_page_id']);
    $sanitized['email_template'] = wp_kses_post($input['email_template']);
    $sanitized['email_format'] = sanitize_text_field($input['email_format']);
    
    // Handle allowed form IDs for redemption
    $sanitized['allowed_form_ids'] = array();
    if (isset($input['allowed_form_ids']) && is_array($input['allowed_form_ids'])) {
        foreach ($input['allowed_form_ids'] as $form_id) {
            $form_id = intval($form_id);
            if ($form_id > 0) {
                $sanitized['allowed_form_ids'][] = strval($form_id);
            }
        }
    }
    
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
        
        <?php
        // Check if Fluent Forms Pro coupon module is available
        global $wpdb;
        $coupons_table = $wpdb->prefix . 'fluentform_coupons';
        $coupon_module_available = $wpdb->get_var("SHOW TABLES LIKE '{$coupons_table}'") === $coupons_table;
        
        if ($coupon_module_available): ?>
            <div class="notice notice-success">
                <p><strong><?php _e('Coupon Module Active', 'gift-certificates-fluentforms'); ?></strong></p>
                <p><?php _e('Fluent Forms Pro Coupon module is installed and active. Gift certificates will automatically create coupon codes that can be used in your forms.', 'gift-certificates-fluentforms'); ?></p>
                <p><?php _e('Using table:', 'gift-certificates-fluentforms'); ?> <code><?php echo esc_html($coupons_table); ?></code></p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Coupon Module Not Found', 'gift-certificates-fluentforms'); ?></strong></p>
                <p><?php _e('The Fluent Forms Pro Coupon module is not installed. Gift certificates will be created but coupon codes will not be generated.', 'gift-certificates-fluentforms'); ?></p>
                <p><?php _e('To enable coupon functionality, please install the Fluent Forms Pro Coupon addon from Fluent Forms → Add-ons.', 'gift-certificates-fluentforms'); ?></p>
                <p><?php _e('Expected table:', 'gift-certificates-fluentforms'); ?> <code><?php echo esc_html($coupons_table); ?></code></p>
            </div>
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Gift Certificate Form ID', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <select name="gift_certificates_ff_settings[gift_certificate_form_id]">
                        <option value=""><?php _e('Select a form', 'gift-certificates-fluentforms'); ?></option>
                        <?php
                        // More flexible Fluent Forms detection
                        $fluent_forms_active = false;
                        $wp_fluent_available = false;
                        
                        // Check multiple ways Fluent Forms might be available
                        if (class_exists('FluentForm\Framework\Foundation\Bootstrap')) {
                            $fluent_forms_active = true;
                        } elseif (class_exists('FluentFormPro\Framework\Foundation\Bootstrap')) {
                            $fluent_forms_active = true;
                        } elseif (function_exists('wpFluent')) {
                            $fluent_forms_active = true;
                            $wp_fluent_available = true;
                        } elseif (is_plugin_active('fluentform/fluentform.php')) {
                            $fluent_forms_active = true;
                        } elseif (is_plugin_active('fluentformpro/fluentformpro.php')) {
                            $fluent_forms_active = true;
                        }
                        
                        if ($fluent_forms_active && function_exists('wpFluent')) {
                            try {
                                $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();
                                if (!empty($forms)) {
                                    foreach ($forms as $form) {
                                        $selected = selected($settings['gift_certificate_form_id'] ?? '', $form->id, false);
                                        echo '<option value="' . esc_attr($form->id) . '" ' . $selected . '>' . esc_html($form->title) . '</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>' . __('No forms found', 'gift-certificates-fluentforms') . '</option>';
                                }
                            } catch (Exception $e) {
                                echo '<option value="" disabled>' . __('Error loading forms: ' . esc_html($e->getMessage()), 'gift-certificates-fluentforms') . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>' . __('Fluent Forms not found or not active', 'gift-certificates-fluentforms') . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the Fluent Forms form that will be used for gift certificate purchases.', 'gift-certificates-fluentforms'); ?></p>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <p class="description" style="color: #666;">
                            Debug: Fluent Forms active: <?php echo $fluent_forms_active ? 'Yes' : 'No'; ?>, 
                            wpFluent function: <?php echo function_exists('wpFluent') ? 'Yes' : 'No'; ?><br>
                            Classes found: 
                            FluentForm\Framework\Foundation\Bootstrap: <?php echo class_exists('FluentForm\Framework\Foundation\Bootstrap') ? 'Yes' : 'No'; ?>,
                            FluentFormPro\Framework\Foundation\Bootstrap: <?php echo class_exists('FluentFormPro\Framework\Foundation\Bootstrap') ? 'Yes' : 'No'; ?>
                        </p>
                    <?php endif; ?>
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
                <th scope="row"><?php _e('Design Selection Field', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[design_field_name]" value="<?php echo esc_attr($settings['design_field_name'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Enter the field name for the gift certificate design selection (radio/select field). Leave empty to use default design.', 'gift-certificates-fluentforms'); ?></p>
                    <p class="description"><?php _e('This field should contain the design ID as the value (e.g., "default", "design_123").', 'gift-certificates-fluentforms'); ?></p>
                    
                    <div class="design-mapping-instructions" style="background: #f9f9f9; padding: 15px; margin-top: 10px; border-left: 4px solid #0073aa;">
                        <h4><?php _e('Design Field Setup Instructions:', 'gift-certificates-fluentforms'); ?></h4>
                        <ol>
                            <li><?php _e('In your Fluent Forms form, add a Radio or Select field for design selection', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('Set the field name to match what you enter above', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('Configure the options with these exact values:', 'gift-certificates-fluentforms'); ?>
                                <ul>
                                    <li><strong>default</strong> - <?php _e('Default design (always available)', 'gift-certificates-fluentforms'); ?></li>
                                    <?php
                                    // Get available designs for reference
                                    $designs = new GiftCertificateDesigns();
                                    $available_designs = $designs->get_active_designs();
                                    foreach ($available_designs as $design_id => $design) {
                                        if ($design_id !== 'default') {
                                            echo '<li><strong>' . esc_html($design_id) . '</strong> - ' . esc_html($design['name']) . '</li>';
                                        }
                                    }
                                    ?>
                                </ul>
                            </li>
                            <li><?php _e('Set the option labels to user-friendly names (e.g., "Classic Design", "Holiday Theme")', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('The option values must exactly match the design IDs shown above', 'gift-certificates-fluentforms'); ?></li>
                        </ol>
                        
                        <h4><?php _e('Example Configuration:', 'gift-certificates-fluentforms'); ?></h4>
                        <table class="widefat" style="margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Option Label', 'gift-certificates-fluentforms'); ?></th>
                                    <th><?php _e('Option Value', 'gift-certificates-fluentforms'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php _e('Classic Design', 'gift-certificates-fluentforms'); ?></td>
                                    <td><code>default</code></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Holiday Theme', 'gift-certificates-fluentforms'); ?></td>
                                    <td><code>design_123</code></td>
                                </tr>
                                <tr>
                                    <td><?php _e('Birthday Special', 'gift-certificates-fluentforms'); ?></td>
                                    <td><code>design_456</code></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p><strong><?php _e('Important Notes:', 'gift-certificates-fluentforms'); ?></strong></p>
                        <ul>
                            <li><?php _e('Only active designs will be accepted by the webhook', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('If an invalid design ID is submitted, the system will automatically use the default design', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('Design IDs are case-sensitive and must match exactly', 'gift-certificates-fluentforms'); ?></li>
                            <li><?php _e('You can manage designs in Gift Certificates → Design Templates', 'gift-certificates-fluentforms'); ?></li>
                        </ul>
                        
                        <div class="available-designs-reference" style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #ddd;">
                            <h4><?php _e('Available Design IDs for Reference:', 'gift-certificates-fluentforms'); ?></h4>
                            <?php
                            $designs = new GiftCertificateDesigns();
                            $design_options = $designs->get_design_options_for_form();
                            
                            if (!empty($design_options)) {
                                echo '<table class="widefat" style="margin-top: 10px;">';
                                echo '<thead><tr><th>' . __('Design ID', 'gift-certificates-fluentforms') . '</th><th>' . __('Design Name', 'gift-certificates-fluentforms') . '</th><th>' . __('Status', 'gift-certificates-fluentforms') . '</th></tr></thead>';
                                echo '<tbody>';
                                
                                foreach ($design_options as $design_id => $design_name) {
                                    $design = $designs->get_design($design_id);
                                    $status = $design && $design['active'] ? __('Active', 'gift-certificates-fluentforms') : __('Inactive', 'gift-certificates-fluentforms');
                                    $status_class = $design && $design['active'] ? 'status-active' : 'status-inactive';
                                    
                                    echo '<tr>';
                                    echo '<td><code>' . esc_html($design_id) . '</code></td>';
                                    echo '<td>' . esc_html($design_name) . '</td>';
                                    echo '<td><span class="' . $status_class . '">' . $status . '</span></td>';
                                    echo '</tr>';
                                }
                                
                                echo '</tbody></table>';
                            } else {
                                echo '<p>' . __('No active designs found. Please create designs in Gift Certificates → Design Templates.', 'gift-certificates-fluentforms') . '</p>';
                            }
                            ?>
                        </div>
                    </div>
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
                <th scope="row"><?php _e('Forms for Redemption', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <select name="gift_certificates_ff_settings[allowed_form_ids][]" multiple style="width: 100%; min-height: 100px;">
                        <?php
                        if ($fluent_forms_active && function_exists('wpFluent')) {
                            try {
                                $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();
                                $selected_forms = $settings['allowed_form_ids'] ?? array();
                                
                                if (!empty($forms)) {
                                    foreach ($forms as $form) {
                                        $selected = in_array(strval($form->id), $selected_forms) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($form->id) . '" ' . $selected . '>' . esc_html($form->title) . '</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>' . __('No forms found', 'gift-certificates-fluentforms') . '</option>';
                                }
                            } catch (Exception $e) {
                                echo '<option value="" disabled>' . __('Error loading forms: ' . esc_html($e->getMessage()), 'gift-certificates-fluentforms') . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>' . __('Fluent Forms not found or not active', 'gift-certificates-fluentforms') . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the forms where gift certificates can be redeemed. Hold Ctrl/Cmd to select multiple forms. Leave empty to allow redemption on all forms.', 'gift-certificates-fluentforms'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Coupon Table Name', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="text" name="gift_certificates_ff_settings[coupon_table_name]" value="<?php echo esc_attr($settings['coupon_table_name'] ?? ''); ?>" class="regular-text" placeholder="fluentform_coupons">
                    <p class="description"><?php _e('Leave empty to use the default table name. Only change this if your Fluent Forms coupon table has a different name.', 'gift-certificates-fluentforms'); ?></p>
                    <p class="description"><?php _e('Note: The table prefix is automatically added by Fluent Forms.', 'gift-certificates-fluentforms'); ?></p>
                    <p class="description"><?php _e('Current default:', 'gift-certificates-fluentforms'); ?> <code>fluentform_coupons</code></p>
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
            
            <tr>
                <th scope="row"><?php _e('Test Email', 'gift-certificates-fluentforms'); ?></th>
                <td>
                    <input type="email" id="test-email-address" placeholder="Enter email address to test" style="width: 300px;">
                    <button type="button" id="test-email" class="button"><?php _e('Send Test Email', 'gift-certificates-fluentforms'); ?></button>
                    <p class="description"><?php _e('Test if email sending is working with your current configuration.', 'gift-certificates-fluentforms'); ?></p>
                    <div id="test-email-result"></div>
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

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-inactive {
    color: #dc3232;
    font-weight: bold;
}

.design-mapping-instructions {
    background: #f9f9f9;
    padding: 15px;
    margin-top: 10px;
    border-left: 4px solid #0073aa;
    border-radius: 3px;
}

.available-designs-reference {
    margin-top: 15px;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
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

#test-email-address {
    margin-right: 10px;
}
</style> 