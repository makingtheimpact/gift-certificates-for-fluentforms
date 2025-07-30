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
        
        // Check if this is the gift certificate creation form
        if ($form->id == $this->settings['gift_certificate_form_id']) {
            error_log("Gift Certificate Webhook: Processing gift certificate creation - Entry ID: {$entry_id}");
            $this->process_gift_certificate_submission($entry_id, $form_data, $form);
            return;
        }
        
        // Check if this is a redemption form
        $allowed_form_ids = $this->settings['allowed_form_ids'] ?? array();
        if (in_array(strval($form->id), $allowed_form_ids)) {
            error_log("Gift Certificate Webhook: Processing gift certificate redemption - Entry ID: {$entry_id}");
            $this->process_gift_certificate_redemption($entry_id, $form_data, $form);
            return;
        }
        
        error_log("Gift Certificate Webhook: Form ID {$form->id} not configured for gift certificate processing");
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
            $design_id = $this->get_field_value($form_data, $this->settings['design_field_name']);
            
            error_log("Gift Certificate Webhook: Extracted data - Amount: {$amount}, Email: {$recipient_email}, Name: {$recipient_name}, Design: {$design_id}");
            
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
            
            // Validate and process design ID
            $design_id = $this->validate_and_process_design_id($design_id);
            
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
                'design_id' => $design_id,
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
            error_log("Gift certificate created successfully: ID {$gift_certificate_id}, Coupon: {$coupon_code}, Design: {$design_id}");
            
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
    
    /**
     * Validate and process the design ID from form submission
     */
    private function validate_and_process_design_id($submitted_design_id) {
        // If no design field is configured or no value submitted, use default
        if (empty($this->settings['design_field_name']) || empty($submitted_design_id)) {
            error_log("Gift Certificate Webhook: No design field configured or no value submitted, using default design");
            return 'default';
        }
        
        // Clean the submitted design ID
        $design_id = trim($submitted_design_id);
        
        // Get available designs
        $designs = new GiftCertificateDesigns();
        $available_designs = $designs->get_active_designs();
        
        // Check if the submitted design ID exists and is active
        if (isset($available_designs[$design_id])) {
            error_log("Gift Certificate Webhook: Valid design ID submitted: {$design_id}");
            return $design_id;
        }
        
        // If design doesn't exist or is not active, log warning and use default
        error_log("Gift Certificate Webhook: Invalid or inactive design ID submitted: {$design_id}. Available designs: " . implode(', ', array_keys($available_designs)));
        error_log("Gift Certificate Webhook: Falling back to default design");
        
        return 'default';
    }
    
    /**
     * Process gift certificate redemption when a coupon is used
     */
    private function process_gift_certificate_redemption($entry_id, $form_data, $form) {
        try {
            error_log("Gift Certificate Webhook: Starting redemption processing - Entry ID: {$entry_id}");
            error_log("Gift Certificate Webhook: Redemption form data: " . print_r($form_data, true));
            
            // Look for applied coupons in the form data
            $applied_coupons = array();
            
            // Check for __ff_all_applied_coupons field (this is the primary source)
            if (isset($form_data['__ff_all_applied_coupons'])) {
                $coupons_json = $form_data['__ff_all_applied_coupons'];
                $decoded_coupons = json_decode($coupons_json, true);
                
                if (is_array($decoded_coupons)) {
                    $applied_coupons = $decoded_coupons;
                } else {
                    // If JSON decode fails, try to extract the coupon code manually
                    preg_match_all('/"([^"]+)"/', $coupons_json, $matches);
                    if (!empty($matches[1])) {
                        $applied_coupons = $matches[1];
                    }
                }
            }
            
            // If no coupons found in __ff_all_applied_coupons, check individual coupon fields
            if (empty($applied_coupons)) {
                foreach ($form_data as $key => $value) {
                    if (strpos($key, 'coupon') !== false && !empty($value)) {
                        // Clean the value to ensure it's just the coupon code
                        $clean_value = trim($value);
                        if (!empty($clean_value) && !in_array($clean_value, $applied_coupons)) {
                            $applied_coupons[] = $clean_value;
                        }
                    }
                }
            }
            
            error_log("Gift Certificate Webhook: Applied coupons found: " . print_r($applied_coupons, true));
            
            if (empty($applied_coupons)) {
                error_log("Gift Certificate Webhook: No coupons applied in this submission");
                return;
            }
            
            // Process each applied coupon
            foreach ($applied_coupons as $coupon_code) {
                $this->process_single_coupon_redemption($coupon_code, $entry_id, $form_data, $form);
            }
            
        } catch (Exception $e) {
            error_log("Gift certificate redemption processing failed: " . $e->getMessage());
            error_log("Gift Certificate Webhook: Redemption exception details - " . $e->getTraceAsString());
        }
    }
    
    /**
     * Process redemption for a single coupon code
     */
    private function process_single_coupon_redemption($coupon_code, $entry_id, $form_data, $form) {
        try {
            error_log("Gift Certificate Webhook: Processing coupon redemption - Code: {$coupon_code}");
            
            // Get the gift certificate
            error_log("Gift Certificate Webhook: Looking up gift certificate for coupon code: {$coupon_code}");
            $gift_certificate = $this->database->get_active_gift_certificate_by_coupon_code($coupon_code);
            
            if (!$gift_certificate) {
                error_log("Gift Certificate Webhook: Active gift certificate not found for coupon code: {$coupon_code}");
                
                // Let's also check if there are any gift certificates in the database
                global $wpdb;
                $table_name = $wpdb->prefix . 'gift_certificates_ff';
                $all_certificates = $wpdb->get_results("SELECT coupon_code, status FROM {$table_name} LIMIT 5");
                error_log("Gift Certificate Webhook: Available certificates in database: " . print_r($all_certificates, true));
                
                return;
            }
            
            error_log("Gift Certificate Webhook: Found gift certificate - ID: {$gift_certificate->id}, Status: {$gift_certificate->status}, Balance: {$gift_certificate->current_balance}");
            
            // Calculate the order total to determine how much to deduct
            $order_total = $this->calculate_order_total($form_data);
            
            if ($order_total <= 0) {
                error_log("Gift Certificate Webhook: Invalid order total: {$order_total}");
                return;
            }
            
            // Determine the amount to deduct (either the full order total or the remaining balance)
            $amount_to_deduct = min($order_total, $gift_certificate->current_balance);
            
            if ($amount_to_deduct <= 0) {
                error_log("Gift Certificate Webhook: No amount to deduct - Balance: {$gift_certificate->current_balance}, Order Total: {$order_total}");
                return;
            }
            
            // Update the gift certificate balance
            $new_balance = $gift_certificate->current_balance - $amount_to_deduct;
            $update_data = array('current_balance' => $new_balance);
            
            // If balance is now 0, mark as used
            if ($new_balance <= 0) {
                $update_data['status'] = 'used';
            }
            
            $updated = $this->database->update_gift_certificate($gift_certificate->id, $update_data);
            
            if (!$updated) {
                error_log("Gift Certificate Webhook: Failed to update gift certificate balance - ID: {$gift_certificate->id}");
                return;
            }
            
            // Record the transaction
            $this->database->record_transaction(
                $gift_certificate->id,
                $amount_to_deduct,
                null, // order_id
                $entry_id
            );
            
            // Update the Fluent Forms coupon amount to reflect the new balance
            $this->update_fluent_forms_coupon_amount($coupon_code, $new_balance);
            
            error_log("Gift Certificate Webhook: Coupon redemption successful - Code: {$coupon_code}, Amount deducted: {$amount_to_deduct}, New balance: {$new_balance}");
            
        } catch (Exception $e) {
            error_log("Gift Certificate Webhook: Single coupon redemption failed - Code: {$coupon_code}, Error: " . $e->getMessage());
        }
    }
    
    /**
     * Calculate the order total from form data
     */
    private function calculate_order_total($form_data) {
        $total = 0;
        
        // Look for common payment/total fields
        $total_fields = array('payment_input', 'total', 'amount', 'price', 'payment_amount');
        
        foreach ($total_fields as $field) {
            if (isset($form_data[$field])) {
                $value = floatval($form_data[$field]);
                if ($value > 0) {
                    $total = $value;
                    break;
                }
            }
        }
        
        // If no total found, try to calculate from quantity and price
        if ($total <= 0) {
            if (isset($form_data['item-quantity']) && isset($form_data['payment_input'])) {
                $quantity = intval($form_data['item-quantity']);
                $price = floatval($form_data['payment_input']);
                $total = $quantity * $price;
            }
        }
        
        error_log("Gift Certificate Webhook: Calculated order total: {$total}");
        return $total;
    }
    
    /**
     * Update the Fluent Forms coupon amount to reflect the new gift certificate balance
     */
    private function update_fluent_forms_coupon_amount($coupon_code, $new_amount) {
        // Get the coupon table name
        $coupon_table_name = $this->get_coupon_table_name();
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            error_log("Gift Certificate Webhook: Coupon table '{$coupon_table_name}' does not exist - cannot update coupon amount");
            return false;
        }
        
        try {
            // Get current coupon to update settings
            $coupon = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->first();
            
            if (!$coupon) {
                error_log("Gift Certificate Webhook: Coupon not found for update - Code: {$coupon_code}");
                return false;
            }
            
            // Update settings to reflect new amount
            $settings = unserialize($coupon->settings);
            if (is_array($settings)) {
                // Update any amount-related settings if needed
                $settings['success_message'] = '{coupon.code} - {coupon.amount}';
            }
            
            // Update coupon amount and settings
            $result = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->update(array(
                    'amount' => $new_amount,
                    'settings' => serialize($settings),
                    'updated_at' => current_time('mysql')
                ));
            
            if ($result) {
                error_log("Gift Certificate Webhook: Fluent Forms coupon amount updated successfully - Code: {$coupon_code}, New Amount: {$new_amount}");
                return true;
            } else {
                error_log("Gift Certificate Webhook: Failed to update Fluent Forms coupon amount - Code: {$coupon_code}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Gift Certificate Webhook: Exception updating Fluent Forms coupon amount: " . $e->getMessage());
            return false;
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
            // Get allowed form IDs from settings
            $allowed_form_ids = $this->settings['allowed_form_ids'] ?? array();
            
            // Prepare settings array based on the actual table structure
            $settings = array(
                'allowed_form_ids' => $allowed_form_ids, // Use selected forms or empty array for all forms
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
            // Remove wp_ prefix if present since wpFluent() adds it automatically
            return str_replace('wp_', '', $custom_table_name);
        }
        
        // Default table name (without wp_ prefix since wpFluent adds it)
        return 'fluentform_coupons';
    }
    
    /**
     * Check if a table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        
        try {
            // For checking existence, we need the full table name with prefix
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
            error_log("Gift Certificate Webhook: Table '{$full_table_name}' exists: " . ($table_exists ? 'Yes' : 'No'));
            return $table_exists;
        } catch (Exception $e) {
            error_log("Gift Certificate Webhook: Error checking table '{$full_table_name}': " . $e->getMessage());
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