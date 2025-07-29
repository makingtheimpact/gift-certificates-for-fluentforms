<?php
/**
 * Webhook handler for Fluent Forms submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateWebhook {
    
    private $database;
    private $settings;
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->settings = get_option('gift_certificates_ff_settings', array());
        
        // Try multiple hooks to ensure compatibility
        add_action('fluentform_submission_inserted', array($this, 'handle_form_submission'), 10, 3);
        add_action('fluentform/form_submission_completed', array($this, 'handle_form_submission'), 10, 3);
        add_action('wp_ajax_gift_certificate_webhook', array($this, 'handle_ajax_webhook'));
        add_action('wp_ajax_nopriv_gift_certificate_webhook', array($this, 'handle_ajax_webhook'));
    }
    
    public function handle_form_submission($entry_id, $form_data, $form) {
        // Prevent duplicate processing
        static $processed_entries = array();
        if (in_array($entry_id, $processed_entries)) {
            error_log("Gift Certificate Webhook: Entry {$entry_id} already processed, skipping");
            return;
        }
        $processed_entries[] = $entry_id;
        
        // Log the submission for debugging
        error_log("Gift Certificate Webhook: Form submission received - Entry ID: {$entry_id}, Form ID: {$form->id}");
        
        // Check if this is the gift certificate form
        if ($form->id != $this->settings['gift_certificate_form_id']) {
            error_log("Gift Certificate Webhook: Form ID mismatch - Expected: {$this->settings['gift_certificate_form_id']}, Got: {$form->id}");
            return;
        }
        
        error_log("Gift Certificate Webhook: Processing gift certificate submission - Entry ID: {$entry_id}");
        
        // Process the submission
        $this->process_gift_certificate_submission($entry_id, $form_data, $form);
    }
    
    public function handle_ajax_webhook() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'gift_certificate_webhook')) {
            wp_die('Security check failed');
        }
        
        $entry_id = intval($_POST['entry_id']);
        $form_id = intval($_POST['form_id']);
        
        // Get form data from Fluent Forms
        $entry = wpFluent()->table('fluentform_submissions')
            ->where('id', $entry_id)
            ->first();
            
        if (!$entry) {
            wp_die('Entry not found');
        }
        
        $form_data = json_decode($entry->response, true);
        $form = wpFluent()->table('fluentform_forms')->where('id', $form_id)->first();
        
        $this->process_gift_certificate_submission($entry_id, $form_data, $form);
        
        wp_die('Success');
    }
    
    private function process_gift_certificate_submission($entry_id, $form_data, $form) {
        try {
            error_log("Gift Certificate Webhook: Starting processing - Entry ID: {$entry_id}");
            error_log("Gift Certificate Webhook: Form data: " . print_r($form_data, true));
            
            // Extract form data using configured field names
            $amount = $this->get_field_value($form_data, $this->settings['amount_field_name']);
            $recipient_email = $this->get_field_value($form_data, $this->settings['recipient_email_field_name']);
            $recipient_name = $this->get_field_value($form_data, $this->settings['recipient_name_field_name']);
            $sender_name = $this->get_field_value($form_data, $this->settings['sender_name_field_name']);
            $message = $this->get_field_value($form_data, $this->settings['message_field_name']);
            $delivery_date = $this->get_field_value($form_data, $this->settings['delivery_date_field_name']);
            
            error_log("Gift Certificate Webhook: Extracted data - Amount: {$amount}, Email: {$recipient_email}, Name: {$recipient_name}");
            
            // Validate required fields
            if (empty($amount) || empty($recipient_email) || empty($recipient_name)) {
                throw new Exception('Required fields are missing - Amount: ' . ($amount ?: 'empty') . ', Email: ' . ($recipient_email ?: 'empty') . ', Name: ' . ($recipient_name ?: 'empty'));
            }
            
            // Validate amount
            $amount = floatval($amount);
            if ($amount <= 0) {
                throw new Exception('Invalid gift certificate amount');
            }
            
            // Validate email
            if (!is_email($recipient_email)) {
                throw new Exception('Invalid recipient email address');
            }
            
            // Generate unique coupon code
            $coupon_code = $this->generate_coupon_code();
            
            // Create gift certificate record
            $gift_certificate_data = array(
                'coupon_code' => $coupon_code,
                'original_amount' => $amount,
                'current_balance' => $amount,
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'sender_name' => $sender_name,
                'message' => $message,
                'delivery_date' => $delivery_date ? date('Y-m-d', strtotime($delivery_date)) : null,
                'status' => $delivery_date ? 'pending_delivery' : 'active'
            );
            
            $gift_certificate_id = $this->database->create_gift_certificate($gift_certificate_data);
            
            if (!$gift_certificate_id) {
                throw new Exception('Failed to create gift certificate record');
            }
            
            // Create Fluent Forms Pro coupon
            $coupon_created = $this->create_fluent_forms_coupon($coupon_code, $amount, $gift_certificate_id);
            
            if (!$coupon_created) {
                error_log("Gift Certificate Webhook: Failed to create Fluent Forms coupon for code: {$coupon_code}");
                error_log("Gift Certificate Webhook: Gift certificate created successfully without coupon - ID: {$gift_certificate_id}");
            } else {
                error_log("Gift Certificate Webhook: Fluent Forms coupon created successfully for code: {$coupon_code}");
            }
            
            // Send gift certificate email
            $email_sent = $this->send_gift_certificate_email($gift_certificate_id, $entry_id);
            error_log("Gift Certificate Webhook: Email sent: " . ($email_sent ? 'Yes' : 'No'));
            
            // Log success
            error_log("Gift certificate created successfully: ID {$gift_certificate_id}, Coupon: {$coupon_code}");
            
        } catch (Exception $e) {
            error_log("Gift certificate creation failed: " . $e->getMessage());
            error_log("Gift Certificate Webhook: Exception details - " . $e->getTraceAsString());
            
            // Update entry with error message
            wpFluent()->table('fluentform_submissions')
                ->where('id', $entry_id)
                ->update(array(
                    'status' => 'failed',
                    'response' => json_encode(array_merge(
                        is_array($form_data) ? $form_data : (json_decode($form_data, true) ?: array()),
                        array('gift_certificate_error' => $e->getMessage())
                    ))
                ));
        }
    }
    
    private function get_field_value($form_data, $field_name) {
        // Handle different field name formats
        if (isset($form_data[$field_name])) {
            return $form_data[$field_name];
        }
        
        // Try with field name as key
        foreach ($form_data as $key => $value) {
            if (strpos($key, $field_name) !== false) {
                return $value;
            }
        }
        
        return '';
    }
    
    private function generate_coupon_code() {
        $prefix = 'GC';
        $length = 8;
        $max_attempts = 10;
        
        for ($i = 0; $i < $max_attempts; $i++) {
            $code = $prefix . strtoupper(substr(md5(uniqid()), 0, $length));
            
            // Check if code already exists
            $existing = $this->database->get_gift_certificate_by_coupon_code($code);
            if (!$existing) {
                return $code;
            }
        }
        
        throw new Exception('Unable to generate unique coupon code');
    }
    
    private function create_fluent_forms_coupon($coupon_code, $amount, $gift_certificate_id) {
        error_log("Gift Certificate Webhook: Attempting to create Fluent Forms coupon - Code: {$coupon_code}, Amount: {$amount}");
        
        // Check if Fluent Forms Pro is active
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        if (!is_plugin_active('fluentformpro/fluentformpro.php') && !is_plugin_active('fluentformpro-addon-pack/fluentformpro-addon-pack.php')) {
            error_log("Gift Certificate Webhook: Fluent Forms Pro is not active - skipping coupon creation");
            return false;
        }
        
        // Get the coupon table name (configurable)
        $coupon_table_name = $this->get_coupon_table_name();
        error_log("Gift Certificate Webhook: Using coupon table: {$coupon_table_name}");
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            error_log("Gift Certificate Webhook: Coupon table '{$coupon_table_name}' does not exist - coupon module may not be installed");
            return false;
        }
        
        try {
            // Prepare settings array based on the actual table structure
            $settings = array(
                'allowed_form_ids' => array(), // Empty array means all forms
                'coupon_limit' => '0', // No limit per user
                'success_message' => '{coupon.code} - {coupon.amount}',
                'failed_message' => array(
                    'inactive' => 'The provided coupon is not valid',
                    'min_amount' => 'The provided coupon does not meet the requirements',
                    'stackable' => 'Sorry, you can not apply this coupon with other coupon code',
                    'date_expire' => 'The provided coupon is not valid',
                    'allowed_form' => 'The provided coupon is not valid',
                    'limit' => 'The provided coupon is not valid'
                )
            );
            
            // Use the correct table structure based on the actual wp_fluentform_coupons table
            $coupon_data = array(
                'title' => "Gift Certificate - {$coupon_code}",
                'code' => $coupon_code,
                'coupon_type' => 'fixed',
                'amount' => $amount,
                'status' => 'active',
                'stackable' => 'no',
                'settings' => serialize($settings),
                'created_by' => get_current_user_id() ?: 1,
                'min_amount' => 0,
                'max_use' => 1, // Can only be used once
                'start_date' => current_time('Y-m-d'),
                'expire_date' => date('Y-m-d', strtotime('+1 year')),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            error_log("Gift Certificate Webhook: Coupon data prepared: " . print_r($coupon_data, true));
            
            // Insert coupon directly into the correct table
            $coupon_id = wpFluent()->table($coupon_table_name)->insert($coupon_data);
            
            if ($coupon_id) {
                error_log("Gift Certificate Webhook: Fluent Forms coupon created successfully - ID: {$coupon_id}, Code: {$coupon_code}");
                return true;
            } else {
                error_log("Gift Certificate Webhook: Fluent Forms coupon creation failed - no ID returned");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Gift Certificate Webhook: Exception creating Fluent Forms coupon: " . $e->getMessage());
            error_log("Gift Certificate Webhook: Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Get the coupon table name (configurable)
     */
    private function get_coupon_table_name() {
        $settings = get_option('gift_certificates_ff_settings', array());
        $custom_table_name = $settings['coupon_table_name'] ?? '';
        
        if (!empty($custom_table_name)) {
            return $custom_table_name;
        }
        
        // Default table name
        global $wpdb;
        return $wpdb->prefix . 'fluentform_coupons';
    }
    
    /**
     * Check if a table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        
        try {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            error_log("Gift Certificate Webhook: Table '{$table_name}' exists: " . ($table_exists ? 'Yes' : 'No'));
            return $table_exists;
        } catch (Exception $e) {
            error_log("Gift Certificate Webhook: Error checking table '{$table_name}': " . $e->getMessage());
            return false;
        }
    }
    
    private function send_gift_certificate_email($gift_certificate_id, $entry_id) {
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        if (!$gift_certificate) {
            return false;
        }
        
        // Check if delivery should be delayed
        if ($gift_certificate->delivery_date && $gift_certificate->delivery_date > current_time('Y-m-d')) {
            // Schedule delivery for later
            wp_schedule_single_event(
                strtotime($gift_certificate->delivery_date . ' 09:00:00'),
                'gift_certificate_scheduled_delivery',
                array($gift_certificate_id)
            );
            return true;
        }
        
        // Send email immediately
        $email_handler = new GiftCertificateEmail();
        return $email_handler->send_gift_certificate_email($gift_certificate_id);
    }
    

} 