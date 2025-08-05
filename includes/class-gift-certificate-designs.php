<?php
/**
 * Gift Certificate Design Templates Handler
 */
namespace GiftCertificatesFluentForms;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateDesigns {
    
    private $database;
    private $settings;
    private $designs_option = 'gift_certificate_designs';
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->settings = get_option('gift_certificates_ff_settings', array());
        
        add_action('admin_menu', array($this, 'add_designs_menu'));
        add_action('wp_ajax_save_gift_certificate_design', array($this, 'save_design_ajax'));
        add_action('wp_ajax_delete_gift_certificate_design', array($this, 'delete_design_ajax'));
        add_action('wp_ajax_get_gift_certificate_design', array($this, 'get_design_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_send_test_email', array($this, 'send_test_email_ajax'));
        
        // Fix corrupted templates on plugin load (only once)
        if (!get_option('gift_certificate_templates_fixed', false)) {
            $this->fix_corrupted_templates();
            update_option('gift_certificate_templates_fixed', true);
        }
    }
    
    public function add_designs_menu() {
        add_submenu_page(
            'gift-certificates-ff',
            __('Design Templates', 'gift-certificates-fluentforms'),
            __('Design Templates', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-designs',
            array($this, 'designs_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gift-certificates-designs') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style(
                'gift-certificate-designs-admin',
                GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/css/designs-admin.css',
                array(),
                GIFT_CERTIFICATES_FF_VERSION
            );
            wp_enqueue_script(
                'gift-certificate-designs-admin',
                GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/js/designs-admin.js',
                array('jquery', 'jquery-ui-sortable'),
                GIFT_CERTIFICATES_FF_VERSION,
                true
            );
            wp_localize_script('gift-certificate-designs-admin', 'giftCertificateDesigns', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gift_certificate_designs_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this design?', 'gift-certificates-fluentforms'),
                    'saving' => __('Saving...', 'gift-certificates-fluentforms'),
                    'saved' => __('Design saved successfully!', 'gift-certificates-fluentforms'),
                    'error' => __('Error saving design. Please try again.', 'gift-certificates-fluentforms')
                )
            ));
        }
    }
    
    public function designs_page() {
        $designs = $this->get_designs();
        $default_design = $this->get_design('default'); // This will get saved default or built-in default
        ?>
        <div class="wrap">
            <h1><?php _e('Gift Certificate Design Templates', 'gift-certificates-fluentforms'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Create and manage gift certificate design templates. Each design can have its own email template content, custom CSS, and image. The default design can be edited but not deleted.', 'gift-certificates-fluentforms'); ?></p>
            </div>

            <h2><?php _e('Available Designs', 'gift-certificates-fluentforms'); ?></h2>
            <div class="gift-certificate-designs-container">                
                <div class="designs-list">                    
                    
                    <div class="design-item default-design" data-design-id="default">
                        <h3><?php _e('Default Design', 'gift-certificates-fluentforms'); ?></h3>
                        <div class="design-preview">
                            <img src="<?php echo esc_url($default_design['image_url']); ?>" alt="Default Design" style="max-width: 200px; height: auto;">
                        </div>
                        <p><strong><?php _e('ID:', 'gift-certificates-fluentforms'); ?></strong> default</p>
                        <p><strong><?php _e('Status:', 'gift-certificates-fluentforms'); ?></strong> 
                            <span class="status-<?php echo $default_design['active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $default_design['active'] ? __('Active', 'gift-certificates-fluentforms') : __('Inactive', 'gift-certificates-fluentforms'); ?>
                            </span>
                        </p>
                        <div class="design-actions">
                            <button type="button" class="button edit-design" data-design-id="default">
                                <?php _e('Edit', 'gift-certificates-fluentforms'); ?>
                            </button>
                            <button type="button" class="button send-test-email" data-design-id="default">
                                <?php _e('Send Test Email', 'gift-certificates-fluentforms'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <?php foreach ($designs as $design): ?>
                    <div class="design-item" data-design-id="<?php echo esc_attr($design['id']); ?>">
                        <h3><?php echo esc_html($design['name']); ?></h3>
                        <div class="design-preview">
                            <img src="<?php echo esc_url($design['image_url']); ?>" alt="<?php echo esc_attr($design['name']); ?>" style="max-width: 200px; height: auto;">
                        </div>
                        <p><strong><?php _e('ID:', 'gift-certificates-fluentforms'); ?></strong> <?php echo esc_html($design['id']); ?></p>
                        <p><strong><?php _e('Status:', 'gift-certificates-fluentforms'); ?></strong> 
                            <span class="status-<?php echo $design['active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $design['active'] ? __('Active', 'gift-certificates-fluentforms') : __('Inactive', 'gift-certificates-fluentforms'); ?>
                            </span>
                        </p>
                        <div class="design-actions">
                            <button type="button" class="button edit-design" data-design-id="<?php echo esc_attr($design['id']); ?>">
                                <?php _e('Edit', 'gift-certificates-fluentforms'); ?>
                            </button>
                            <button type="button" class="button send-test-email" data-design-id="<?php echo esc_attr($design['id']); ?>">
                                <?php _e('Send Test Email', 'gift-certificates-fluentforms'); ?>
                            </button>
                            <button type="button" class="button button-link-delete delete-design" data-design-id="<?php echo esc_attr($design['id']); ?>">
                                <?php _e('Delete', 'gift-certificates-fluentforms'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="button" class="button button-primary add-new-design">
                        <?php _e('Add New Design', 'gift-certificates-fluentforms'); ?>
                    </button>
                </div>
                
                <div class="design-editor" style="display: none;">
                    <h2><?php _e('Design Editor', 'gift-certificates-fluentforms'); ?></h2>
                    <form id="design-form">
                        <input type="hidden" id="design-id" name="design_id" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Design Name', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <input type="text" id="design-name" name="design_name" class="regular-text" required>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Design Image', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <div class="image-upload-container">
                                        <input type="hidden" id="design-image-id" name="design_image_id" value="">
                                        <img id="design-image-preview" src="" style="max-width: 300px; height: auto; display: none;">
                                        <br>
                                        <button type="button" class="button upload-image">
                                            <?php _e('Upload Image', 'gift-certificates-fluentforms'); ?>
                                        </button>
                                        <button type="button" class="button remove-image" style="display: none;">
                                            <?php _e('Remove Image', 'gift-certificates-fluentforms'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Email Format', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <select id="email-format" name="email_format">
                                        <option value="html"><?php _e('HTML', 'gift-certificates-fluentforms'); ?></option>
                                        <option value="plain"><?php _e('Plain Text', 'gift-certificates-fluentforms'); ?></option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Choose whether to send HTML or plain text emails.', 'gift-certificates-fluentforms'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Email Template Content', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <textarea id="email-template" name="email_template" rows="12" cols="80" class="large-text"></textarea>
                                    <p class="description">
                                        <?php _e('Enter the main content of your email template. Available placeholders: {recipient_name}, {sender_name}, {amount}, {coupon_code}, {message}, {site_name}, {site_url}, {balance_check_url}, {design_image}', 'gift-certificates-fluentforms'); ?>
                                    </p>
                                    <p class="description">
                                        <?php _e('For HTML emails, you can use basic HTML tags like &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;table&gt;, etc. The email wrapper and CSS will be added automatically when sending.', 'gift-certificates-fluentforms'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Custom CSS (HTML emails only)', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <textarea id="custom-css" name="custom_css" rows="8" cols="80" class="large-text code"></textarea>
                                    <p class="description">
                                        <?php _e('Add custom CSS styles for your email template. These styles will be included in the email header. Leave empty to use default styles.', 'gift-certificates-fluentforms'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Active', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="design-active" name="design_active" value="1">
                                        <?php _e('Enable this design', 'gift-certificates-fluentforms'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Save Design', 'gift-certificates-fluentforms'); ?></button>
                            <button type="button" class="button cancel-edit"><?php _e('Cancel', 'gift-certificates-fluentforms'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save_design_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_designs_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $design_data = array(
            'id' => sanitize_text_field($_POST['design_id']),
            'name' => sanitize_text_field($_POST['design_name']),
            'image_id' => intval($_POST['design_image_id']),
            'image_url' => esc_url_raw($_POST['design_image_url']),
            'email_template' => $this->sanitize_email_template($_POST['email_template']),
            'custom_css' => sanitize_textarea_field($_POST['custom_css']),
            'email_format' => sanitize_text_field($_POST['email_format']),
            'active' => !empty($_POST['design_active']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $this->save_design($design_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Design saved successfully!', 'gift-certificates-fluentforms')));
        } else {
            wp_send_json_error(array('message' => __('Error saving design. Please try again.', 'gift-certificates-fluentforms')));
        }
    }
    
    /**
     * Sanitize email template content
     * This function allows basic HTML tags for email content while removing potentially dangerous content
     */
    private function sanitize_email_template($template) {
        if (empty($template)) {
            return '';
        }
        
        // Debug: Log the original template
        gcff_log('Gift Certificate: Original template length: ' . strlen($template));
        
        // Allow basic HTML tags for email content
        $allowed_html = array(
            'div' => array(
                'style' => array(),
                'class' => array()
            ),
            'p' => array(
                'style' => array()
            ),
            'h1' => array(
                'style' => array()
            ),
            'h2' => array(
                'style' => array()
            ),
            'h3' => array(
                'style' => array()
            ),
            'strong' => array(),
            'em' => array(),
            'br' => array(),
            'a' => array(
                'href' => array(),
                'style' => array(),
                'class' => array()
            ),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'style' => array(),
                'width' => array(),
                'height' => array()
            ),
            'span' => array(
                'style' => array(),
                'class' => array()
            ),
            'table' => array(
                'role' => array(),
                'cellspacing' => array(),
                'cellpadding' => array(),
                'border' => array(),
                'width' => array(),
                'align' => array(),
                'style' => array(),
                'class' => array()
            ),
            'tr' => array(
                'style' => array()
            ),
            'td' => array(
                'style' => array(),
                'class' => array(),
                'align' => array(),
                'width' => array()
            )
        );
        
        // Use wp_kses with our custom allowed HTML
        $sanitized = wp_kses($template, $allowed_html);
        
        // Debug: Log after wp_kses
        gcff_log('Gift Certificate: After wp_kses length: ' . strlen($sanitized));
        
        // Debug: Log final result
        gcff_log('Gift Certificate: Final sanitized length: ' . strlen($sanitized));
        
        return $sanitized;
    }
    
    public function delete_design_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_designs_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $design_id = sanitize_text_field($_POST['design_id']);
        
        if ($design_id === 'default') {
            wp_send_json_error(array('message' => __('The default design cannot be deleted, but you can edit it or disable it.', 'gift-certificates-fluentforms')));
        }
        
        $result = $this->delete_design($design_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Design deleted successfully!', 'gift-certificates-fluentforms')));
        } else {
            wp_send_json_error(array('message' => __('Error deleting design. Please try again.', 'gift-certificates-fluentforms')));
        }
    }
    
    public function get_designs() {
        $designs = get_option($this->designs_option, array());
        $designs = is_array($designs) ? $designs : array();
        
        // Decode email templates for all designs
        foreach ($designs as $design_id => $design) {
            if (isset($design['email_template'])) {
                $designs[$design_id]['email_template'] = $this->decode_email_template($design['email_template']);
            }
            // Ensure custom_css field exists
            if (!isset($design['custom_css'])) {
                $designs[$design_id]['custom_css'] = '';
            }
        }
        
        return $designs;
    }
    
    public function get_design($design_id) {
        if ($design_id === 'default') {
            // Check if there's a saved default design, otherwise return the built-in default
            $designs = $this->get_designs();
            if (isset($designs['default'])) {
                $design = $designs['default'];
                // Decode the email template
                if (isset($design['email_template'])) {
                    $design['email_template'] = $this->decode_email_template($design['email_template']);
                }
                // Ensure custom_css field exists
                if (!isset($design['custom_css'])) {
                    $design['custom_css'] = '';
                }
                return $design;
            }
            return $this->get_default_design();
        }
        
        $designs = $this->get_designs();
        if (isset($designs[$design_id])) {
            $design = $designs[$design_id];
            // Decode the email template
            if (isset($design['email_template'])) {
                $design['email_template'] = $this->decode_email_template($design['email_template']);
            }
            // Ensure custom_css field exists
            if (!isset($design['custom_css'])) {
                $design['custom_css'] = '';
            }
            return $design;
        }
        
        return false;
    }
    
    public function get_default_design() {
        $default_image_url = GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/images/default-gift-certificate.jpg';
        
        // Check if the default image exists, if not use a placeholder
        if (!file_exists(GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'assets/images/default-gift-certificate.jpg')) {
            $default_image_url = '';
        }
        
        return array(
            'id' => 'default',
            'name' => __('Default Design', 'gift-certificates-fluentforms'),
            'image_id' => 0,
            'image_url' => $default_image_url,
            'email_template' => $this->get_default_email_template(),
            'custom_css' => $this->get_default_css_template(),
            'email_format' => 'html',
            'active' => 1,
            'created_at' => current_time('mysql')
        );
    }
    
    private function get_default_plain_text_template() {
        return "Dear {recipient_name},\n\n" .
               "You have received a beautiful gift certificate from {sender_name}!\n\n" .
               "Gift Certificate Details:\n" .
               "Amount: \${amount}\n" .
               "Code: {coupon_code}\n\n" .
               "Message from {sender_name}:\n{message}\n\n" .
               "You can use this gift certificate on our website. Simply enter the coupon code during checkout to apply your discount.\n\n" .
               "You can check your balance at any time at: {balance_check_url}\n\n" .
               "Thank you for choosing {site_name}!\n\n" .
               "{site_name}\n" .
               "{site_url}";
    }
    
    private function get_default_css_template() {
        return "/* Reset styles */
body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

/* Base styles */
body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #333333; background-color: #f4f4f4; }

/* Container */
.email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }

/* Header */
.header { background-color: #6c757d; color: #ffffff; padding: 30px 20px; text-align: center; }
.header h1 { margin: 0; font-size: 28px; font-weight: bold; }

/* Content */
.content { padding: 30px 20px; }
.content p { margin: 0 0 15px 0; }

/* Gift details */
.gift-details { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #6c757d; }
.gift-details h3 { margin: 0 0 15px 0; color: #333333; }

/* Amount */
.amount { font-size: 32px; font-weight: bold; color: #6c757d; text-align: center; margin: 15px 0; }

/* Coupon code */
.coupon-code { font-size: 24px; font-weight: bold; color: #6c757d; text-align: center; padding: 15px; background-color: #e9ecef; margin: 15px 0; }

/* Message */
.message { font-style: italic; margin: 20px 0; padding: 20px; background-color: #fff3e0; border-left: 4px solid #ff9800; }

/* Button */
.button { display: inline-block; padding: 12px 24px; background-color: #6c757d; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }

/* Footer */
.footer { text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }

/* Responsive */
@media only screen and (max-width: 600px) {
    .email-container { width: 100% !important; }
    .content { padding: 20px 15px !important; }
    .header { padding: 20px 15px !important; }
    .header h1 { font-size: 24px !important; }
    .amount { font-size: 28px !important; }
    .coupon-code { font-size: 20px !important; }
}";
    }
    
    public function save_design($design_data) {
        $designs = $this->get_designs();
        
        // Generate unique ID if not provided
        if (empty($design_data['id'])) {
            $design_data['id'] = 'design_' . time() . '_' . rand(1000, 9999);
        }
        
        // Ensure the design has all required fields
        if ($design_data['id'] === 'default') {
            $default_design = $this->get_default_design();
            $design_data = wp_parse_args($design_data, $default_design);
        }
        
        $designs[$design_data['id']] = $design_data;
        
        return update_option($this->designs_option, $designs);
    }
    
    public function delete_design($design_id) {
        $designs = $this->get_designs();
        
        if (isset($designs[$design_id])) {
            unset($designs[$design_id]);
            return update_option($this->designs_option, $designs);
        }
        
        return false;
    }
    
    public function get_design_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_designs_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $design_id = sanitize_text_field($_POST['design_id']);
        $design = $this->get_design($design_id);
        
        if ($design) {
            // Decode the email template to prevent HTML entity issues
            if (isset($design['email_template'])) {
                $design['email_template'] = $this->decode_email_template($design['email_template']);
            }
            wp_send_json_success($design);
        } else {
            wp_send_json_error(array('message' => __('Design not found.', 'gift-certificates-fluentforms')));
        }
    }
    
    /**
     * Decode email template to prevent HTML entity issues
     */
    private function decode_email_template($template) {
        if (empty($template)) {
            return '';
        }
        
        // Decode common HTML entities that might have been escaped
        $decoded = html_entity_decode($template, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Fix any remaining escaped entities
        $decoded = str_replace(
            array('&lt;', '&gt;', '&amp;', '&quot;', '&#039;'),
            array('<', '>', '&', '"', "'"),
            $decoded
        );
        
        return $decoded;
    }
    
    public function get_active_designs() {
        $designs = $this->get_designs();
        $active_designs = array();
        
        // Get the default design (saved or built-in)
        $default_design = $this->get_design('default');
        if ($default_design['active']) {
            $active_designs['default'] = $default_design;
        }
        
        // Add other active designs
        foreach ($designs as $design) {
            if ($design['active'] && $design['id'] !== 'default') {
                $active_designs[$design['id']] = $design;
            }
        }
        
        return $active_designs;
    }
    
    /**
     * Get a list of available design IDs and names for form configuration
     */
    public function get_design_options_for_form() {
        $active_designs = $this->get_active_designs();
        $options = array();
        
        foreach ($active_designs as $design_id => $design) {
            $options[$design_id] = $design['name'];
        }
        
        return $options;
    }
    
    /**
     * Validate if a design ID exists and is active
     */
    public function is_valid_design_id($design_id) {
        $active_designs = $this->get_active_designs();
        return isset($active_designs[$design_id]);
    }
    
    private function get_default_email_template() {
        return '<p>Dear <strong>{recipient_name}</strong>,</p>
<p>You have received a beautiful gift certificate from <strong>{sender_name}</strong>!</p>

<div class="gift-details">
    <h3>Gift Certificate Details:</h3>
    <div class="amount">Amount: ${amount}</div>
    <div class="coupon-code">Code: {coupon_code}</div>
</div>

<div class="message">
    <strong>Message from {sender_name}:</strong><br>
    {message}
</div>

<p>You can use this gift certificate on {site_name} at {site_url}. To redeem, visit our website at {site_url} and enter the code in the coupon code field at checkout.</p>

<p style="text-align: center;">
    <a href="{balance_check_url}" class="button">Check Balance</a>
</p>

<p>If you have any questions or need further assistance, please contact us on our website and we will be happy to assist you.</p>';
    }

    /**
     * Fix existing corrupted email templates in the database
     * This method can be called to clean up templates that have HTML entities
     */
    public function fix_corrupted_templates() {
        $designs = get_option($this->designs_option, array());
        $updated = false;
        
        if (is_array($designs)) {
            foreach ($designs as $design_id => $design) {
                if (isset($design['email_template'])) {
                    $original_template = $design['email_template'];
                    $fixed_template = $this->decode_email_template($original_template);
                    
                    // If the template was corrupted, fix it
                    if ($original_template !== $fixed_template) {
                        $designs[$design_id]['email_template'] = $fixed_template;
                        $updated = true;
                    }
                }
            }
            
            if ($updated) {
                update_option($this->designs_option, $designs);
                return true;
            }
        }
        
        return false;
    }

    public function send_test_email_ajax() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_designs_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // Sanitize input
        $email = sanitize_email($_POST['email']);
        $design_id = sanitize_text_field($_POST['design_id']);

        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
        }

        // Use the email class to send a test email with the selected design
        $email_sender = GiftCertificateEmail::get_instance();

        $sent = $email_sender->send_test_email($email, $design_id);

        if ($sent) {
            wp_send_json_success(array('message' => 'Test email sent successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send test email.'));
        }
    }
} 