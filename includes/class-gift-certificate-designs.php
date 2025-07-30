<?php
/**
 * Gift Certificate Design Templates Handler
 */

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
                <p><?php _e('Create and manage gift certificate design templates. Each design can have its own email template and image. The default design can be edited but not deleted.', 'gift-certificates-fluentforms'); ?></p>
            </div>
            
            <div class="gift-certificate-designs-container">
                <div class="designs-list">
                    <h2><?php _e('Available Designs', 'gift-certificates-fluentforms'); ?></h2>
                    
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
                                <th scope="row"><?php _e('Email Template', 'gift-certificates-fluentforms'); ?></th>
                                <td>
                                    <select id="email-format" name="email_format">
                                        <option value="html"><?php _e('HTML', 'gift-certificates-fluentforms'); ?></option>
                                        <option value="plain"><?php _e('Plain Text', 'gift-certificates-fluentforms'); ?></option>
                                    </select>
                                    <br><br>
                                    <textarea id="email-template" name="email_template" rows="15" cols="80" class="large-text"></textarea>
                                    <p class="description">
                                        <?php _e('Available placeholders: {recipient_name}, {sender_name}, {amount}, {coupon_code}, {message}, {site_name}, {site_url}, {balance_check_url}', 'gift-certificates-fluentforms'); ?>
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
            'email_template' => wp_kses_post($_POST['email_template']),
            'email_format' => sanitize_text_field($_POST['email_format']),
            'active' => isset($_POST['design_active']) ? 1 : 0,
            'created_at' => current_time('mysql')
        );
        
        $result = $this->save_design($design_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Design saved successfully!', 'gift-certificates-fluentforms')));
        } else {
            wp_send_json_error(array('message' => __('Error saving design. Please try again.', 'gift-certificates-fluentforms')));
        }
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
        return is_array($designs) ? $designs : array();
    }
    
    public function get_design($design_id) {
        if ($design_id === 'default') {
            // Check if there's a saved default design, otherwise return the built-in default
            $designs = $this->get_designs();
            if (isset($designs['default'])) {
                return $designs['default'];
            }
            return $this->get_default_design();
        }
        
        $designs = $this->get_designs();
        return isset($designs[$design_id]) ? $designs[$design_id] : false;
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
            'email_format' => 'html',
            'active' => 1,
            'created_at' => current_time('mysql')
        );
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
            wp_send_json_success($design);
        } else {
            wp_send_json_error(array('message' => __('Design not found.', 'gift-certificates-fluentforms')));
        }
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
    
    private function get_default_email_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Certificate</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .gift-details { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
        .coupon-code { font-size: 24px; font-weight: bold; color: #667eea; text-align: center; padding: 15px; background-color: #e3f2fd; border-radius: 5px; margin: 15px 0; }
        .message { font-style: italic; margin: 20px 0; padding: 20px; background-color: #fff3e0; border-radius: 5px; border-left: 4px solid #ff9800; }
        .footer { text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
        .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 25px; font-weight: bold; }
        .amount { font-size: 32px; font-weight: bold; color: #667eea; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎁 Gift Certificate</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{recipient_name}</strong>,</p>
            <p>You have received a beautiful gift certificate from <strong>{sender_name}</strong>!</p>
            
            <div class="gift-details">
                <h3>Gift Certificate Details:</h3>
                <div class="amount">${amount}</div>
                <div class="coupon-code">{coupon_code}</div>
            </div>
            
            <div class="message">
                <strong>Message from {sender_name}:</strong><br>
                {message}
            </div>
            
            <p>You can use this gift certificate on our website. Simply enter the coupon code during checkout to apply your discount.</p>
            
            <p style="text-align: center;">
                <a href="{balance_check_url}" class="button">Check Balance</a>
            </p>
        </div>
        <div class="footer">
            <p>Thank you for choosing {site_name}!</p>
            <p><strong>{site_name}</strong></p>
        </div>
    </div>
</body>
</html>';
    }
} 