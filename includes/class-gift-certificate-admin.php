<?php
/**
 * Admin interface for gift certificates
 */

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateAdmin {
    
    private $database;
    private $settings;
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->settings = get_option('gift_certificates_ff_settings', array());
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_gift_certificate_admin_action', array($this, 'handle_admin_ajax'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Gift Certificates', 'gift-certificates-fluentforms'),
            __('Gift Certificates', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff',
            array($this, 'dashboard_page'),
            'dashicons-tickets-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'gift-certificates-ff',
            __('Dashboard', 'gift-certificates-fluentforms'),
            __('Dashboard', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('All Certificates', 'gift-certificates-fluentforms'),
            __('All Certificates', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-list',
            array($this, 'certificates_list_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('Settings', 'gift-certificates-fluentforms'),
            __('Settings', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('How to Use', 'gift-certificates-fluentforms'),
            __('How to Use', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-help',
            array($this, 'help_page')
        );
    }
    
    public function init_settings() {
        register_setting('gift_certificates_ff_settings', 'gift_certificates_ff_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        add_settings_section(
            'gift_certificates_ff_general',
            __('General Settings', 'gift-certificates-fluentforms'),
            array($this, 'general_settings_section'),
            'gift_certificates_ff_settings'
        );
        
        add_settings_field(
            'gift_certificate_form_id',
            __('Gift Certificate Form ID', 'gift-certificates-fluentforms'),
            array($this, 'form_id_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );
        
        add_settings_field(
            'field_mapping',
            __('Form Field Mapping', 'gift-certificates-fluentforms'),
            array($this, 'field_mapping_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );
        
        add_settings_section(
            'gift_certificates_ff_email',
            __('Email Settings', 'gift-certificates-fluentforms'),
            array($this, 'email_settings_section'),
            'gift_certificates_ff_settings'
        );
        
        add_settings_field(
            'email_template',
            __('Email Template', 'gift-certificates-fluentforms'),
            array($this, 'email_template_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_email'
        );
        
        add_settings_field(
            'email_format',
            __('Email Format', 'gift-certificates-fluentforms'),
            array($this, 'email_format_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_email'
        );
    }
    
    public function dashboard_page() {
        $stats = $this->database->get_stats();
        $recent_certificates = $this->database->get_gift_certificates(array('limit' => 5));
        $pending_deliveries = $this->database->get_pending_deliveries();
        
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function certificates_list_page() {
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $args = array(
            'limit' => 20,
            'offset' => ($page - 1) * 20,
            'status' => $status
        );
        
        $certificates = $this->database->get_gift_certificates($args);
        $total = $this->database->get_gift_certificates_count($args);
        
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/certificates-list.php';
    }
    
    public function settings_page() {
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function help_page() {
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/help.php';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gift-certificates-ff') === false) {
            return;
        }
        
        wp_enqueue_script(
            'gift-certificate-admin',
            GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GIFT_CERTIFICATES_FF_VERSION,
            true
        );
        
        wp_enqueue_style(
            'gift-certificate-admin',
            GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GIFT_CERTIFICATES_FF_VERSION
        );
        
        wp_localize_script('gift-certificate-admin', 'giftCertificateAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gift_certificate_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this gift certificate?', 'gift-certificates-fluentforms'),
                'confirmResend' => __('Are you sure you want to resend this gift certificate?', 'gift-certificates-fluentforms')
            )
        ));
    }
    
    public function handle_admin_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        $certificate_id = intval($_POST['certificate_id']);
        
        switch ($action) {
            case 'delete':
                $this->delete_certificate($certificate_id);
                break;
                
            case 'resend':
                $this->resend_certificate($certificate_id);
                break;
                
            case 'update_status':
                $status = sanitize_text_field($_POST['status']);
                $this->update_certificate_status($certificate_id, $status);
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    private function delete_certificate($certificate_id) {
        $certificate = $this->database->get_gift_certificate($certificate_id);
        
        if (!$certificate) {
            wp_send_json_error('Certificate not found');
        }
        
        // Delete from database
        $result = $this->database->delete_gift_certificate($certificate_id);
        
        if ($result) {
            // Also delete the Fluent Forms coupon
            $this->delete_fluent_forms_coupon($certificate->coupon_code);
            
            wp_send_json_success('Certificate deleted successfully');
        } else {
            wp_send_json_error('Failed to delete certificate');
        }
    }
    
    private function resend_certificate($certificate_id) {
        $email_handler = new GiftCertificateEmail();
        $result = $email_handler->send_gift_certificate_email($certificate_id);
        
        if ($result) {
            wp_send_json_success('Certificate resent successfully');
        } else {
            wp_send_json_error('Failed to resend certificate');
        }
    }
    
    private function update_certificate_status($certificate_id, $status) {
        $result = $this->database->update_gift_certificate_status($certificate_id, $status);
        
        if ($result) {
            wp_send_json_success('Status updated successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    private function delete_fluent_forms_coupon($coupon_code) {
        if (!class_exists('FluentFormPro\Classes\Coupon\CouponService')) {
            return false;
        }
        
        try {
            $coupon_service = new FluentFormPro\Classes\Coupon\CouponService();
            
            $coupon = wpFluent()->table('fluentform_coupons')
                ->where('code', $coupon_code)
                ->first();
                
            if ($coupon) {
                $coupon_service->delete($coupon->id);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to delete Fluent Forms coupon: " . $e->getMessage());
            return false;
        }
    }
    
    // Settings field callbacks
    public function general_settings_section() {
        echo '<p>' . __('Configure the basic settings for gift certificate functionality.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function form_id_field() {
        $form_id = $this->settings['gift_certificate_form_id'] ?? '';
        
        // Get Fluent Forms
        $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();
        
        echo '<select name="gift_certificates_ff_settings[gift_certificate_form_id]">';
        echo '<option value="">' . __('Select a form', 'gift-certificates-fluentforms') . '</option>';
        
        foreach ($forms as $form) {
            $selected = ($form->id == $form_id) ? 'selected' : '';
            echo "<option value='{$form->id}' {$selected}>{$form->title}</option>";
        }
        
        echo '</select>';
        echo '<p class="description">' . __('Select the Fluent Forms form that will be used for gift certificate purchases.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function field_mapping_field() {
        $field_names = array(
            'amount_field_name' => __('Amount Field', 'gift-certificates-fluentforms'),
            'recipient_email_field_name' => __('Recipient Email Field', 'gift-certificates-fluentforms'),
            'recipient_name_field_name' => __('Recipient Name Field', 'gift-certificates-fluentforms'),
            'sender_name_field_name' => __('Sender Name Field', 'gift-certificates-fluentforms'),
            'message_field_name' => __('Message Field', 'gift-certificates-fluentforms'),
            'delivery_date_field_name' => __('Delivery Date Field', 'gift-certificates-fluentforms')
        );
        
        foreach ($field_names as $field_key => $field_label) {
            $value = $this->settings[$field_key] ?? '';
            echo "<p><label>{$field_label}:</label><br>";
            echo "<input type='text' name='gift_certificates_ff_settings[{$field_key}]' value='" . esc_attr($value) . "' class='regular-text'>";
            echo "</p>";
        }
        
        echo '<p class="description">' . __('Enter the field names from your Fluent Forms form that correspond to each gift certificate field.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_settings_section() {
        echo '<p>' . __('Configure email settings for gift certificate delivery.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_template_field() {
        $template = $this->settings['email_template'] ?? '';
        
        echo '<textarea name="gift_certificates_ff_settings[email_template]" rows="10" cols="50" class="large-text">' . esc_textarea($template) . '</textarea>';
        echo '<p class="description">' . __('Available placeholders: {recipient_name}, {sender_name}, {amount}, {coupon_code}, {message}, {site_name}, {site_url}, {balance_check_url}', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_format_field() {
        $format = $this->settings['email_format'] ?? 'text';
        
        echo '<select name="gift_certificates_ff_settings[email_format]">';
        echo '<option value="text" ' . selected($format, 'text', false) . '>' . __('Plain Text', 'gift-certificates-fluentforms') . '</option>';
        echo '<option value="html" ' . selected($format, 'html', false) . '>' . __('HTML', 'gift-certificates-fluentforms') . '</option>';
        echo '</select>';
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize form ID
        $sanitized['gift_certificate_form_id'] = intval($input['gift_certificate_form_id']);
        
        // Sanitize field names
        $field_names = array(
            'amount_field_name', 'recipient_email_field_name', 'recipient_name_field_name',
            'sender_name_field_name', 'message_field_name', 'delivery_date_field_name'
        );
        
        foreach ($field_names as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field]);
        }
        
        // Sanitize email settings
        $sanitized['email_template'] = wp_kses_post($input['email_template']);
        $sanitized['email_format'] = sanitize_text_field($input['email_format']);
        $sanitized['email_subject'] = sanitize_text_field($input['email_subject']);
        $sanitized['from_email'] = sanitize_email($input['from_email']);
        $sanitized['from_name'] = sanitize_text_field($input['from_name']);
        
        return $sanitized;
    }
} 