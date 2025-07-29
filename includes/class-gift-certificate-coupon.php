<?php
/**
 * Coupon integration with Fluent Forms Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateCoupon {
    
    private $database;
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        
        // Hook into Fluent Forms Pro coupon validation
        add_filter('fluentformpro_coupon_validation', array($this, 'validate_gift_certificate_coupon'), 10, 3);
        add_action('fluentformpro_coupon_applied', array($this, 'handle_coupon_applied'), 10, 3);
        add_action('fluentformpro_coupon_removed', array($this, 'handle_coupon_removed'), 10, 2);
        
        // Hook into coupon usage tracking
        add_action('fluentformpro_coupon_used', array($this, 'track_coupon_usage'), 10, 3);
    }
    
    public function validate_gift_certificate_coupon($is_valid, $coupon, $form_data) {
        // Check if this is a gift certificate coupon
        if (!$this->is_gift_certificate_coupon($coupon)) {
            return $is_valid;
        }
        
        // Get gift certificate data
        $gift_certificate = $this->database->get_gift_certificate_by_coupon_code($coupon->code);
        
        if (!$gift_certificate) {
            return false;
        }
        
        // Check if gift certificate is active
        if ($gift_certificate->status !== 'active') {
            return false;
        }
        
        // Check if there's sufficient balance
        if ($gift_certificate->current_balance <= 0) {
            return false;
        }
        
        // Check if the order amount is within the available balance
        $order_total = $this->calculate_order_total($form_data);
        
        if ($order_total > $gift_certificate->current_balance) {
            // Adjust coupon amount to available balance
            $coupon->amount = $gift_certificate->current_balance;
        }
        
        return true;
    }
    
    public function handle_coupon_applied($coupon, $form_data, $submission_id) {
        // Check if this is a gift certificate coupon
        if (!$this->is_gift_certificate_coupon($coupon)) {
            return;
        }
        
        // Store gift certificate info in session for later processing
        $gift_certificate = $this->database->get_gift_certificate_by_coupon_code($coupon->code);
        
        if ($gift_certificate) {
            WC()->session->set("gift_certificate_{$submission_id}", array(
                'gift_certificate_id' => $gift_certificate->id,
                'coupon_code' => $coupon->code,
                'amount_applied' => $coupon->amount
            ));
        }
    }
    
    public function handle_coupon_removed($coupon, $submission_id) {
        // Remove gift certificate info from session
        WC()->session->__unset("gift_certificate_{$submission_id}");
    }
    
    public function track_coupon_usage($coupon, $form_data, $submission_id) {
        // Check if this is a gift certificate coupon
        if (!$this->is_gift_certificate_coupon($coupon)) {
            return;
        }
        
        // Get gift certificate info from session
        $gift_certificate_info = WC()->session->get("gift_certificate_{$submission_id}");
        
        if (!$gift_certificate_info) {
            return;
        }
        
        $gift_certificate_id = $gift_certificate_info['gift_certificate_id'];
        $amount_used = $gift_certificate_info['amount_applied'];
        
        // Get current gift certificate
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        if (!$gift_certificate) {
            return;
        }
        
        // Calculate new balance
        $new_balance = $gift_certificate->current_balance - $amount_used;
        
        // Update gift certificate balance
        $this->database->update_gift_certificate_balance($gift_certificate_id, $new_balance);
        
        // Record transaction
        $this->database->record_transaction(
            $gift_certificate_id,
            $amount_used,
            null,
            $submission_id
        );
        
        // Update Fluent Forms Pro coupon if balance is zero
        if ($new_balance <= 0) {
            $this->deactivate_fluent_forms_coupon($coupon->code);
        } else {
            // Update coupon amount to remaining balance
            $this->update_fluent_forms_coupon_amount($coupon->code, $new_balance);
        }
        
        // Remove from session
        WC()->session->__unset("gift_certificate_{$submission_id}");
        
        // Log transaction
        error_log("Gift certificate used: ID {$gift_certificate_id}, Amount: {$amount_used}, New Balance: {$new_balance}");
    }
    
    private function is_gift_certificate_coupon($coupon) {
        // Check if coupon code matches gift certificate pattern
        if (preg_match('/^GC[A-Z0-9]{8}$/', $coupon->code)) {
            return true;
        }
        
        // Check if coupon title indicates it's a gift certificate
        if (isset($coupon->title) && strpos($coupon->title, 'Gift Certificate') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function calculate_order_total($form_data) {
        // This method should calculate the total order amount from form data
        // Implementation depends on your specific form structure
        
        $total = 0;
        
        // Look for common total fields
        $total_fields = array('total', 'amount', 'price', 'order_total', 'cart_total');
        
        foreach ($total_fields as $field) {
            if (isset($form_data[$field])) {
                $total = floatval($form_data[$field]);
                break;
            }
        }
        
        return $total;
    }
    
    private function deactivate_fluent_forms_coupon($coupon_code) {
        // Get the coupon table name
        $coupon_table_name = $this->get_coupon_table_name();
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            error_log("Gift Certificate: Coupon table '{$coupon_table_name}' does not exist - cannot deactivate coupon");
            return false;
        }
        
        try {
            // Update coupon status directly in database
            $result = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->update(array(
                    'status' => 'inactive',
                    'updated_at' => current_time('mysql')
                ));
            
            if ($result) {
                error_log("Gift Certificate: Fluent Forms coupon deactivated successfully - Code: {$coupon_code}");
                return true;
            } else {
                error_log("Gift Certificate: Failed to deactivate Fluent Forms coupon - Code: {$coupon_code}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Failed to deactivate Fluent Forms coupon: " . $e->getMessage());
            return false;
        }
    }
    
    private function update_fluent_forms_coupon_amount($coupon_code, $new_amount) {
        // Get the coupon table name
        $coupon_table_name = $this->get_coupon_table_name();
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            error_log("Gift Certificate: Coupon table '{$coupon_table_name}' does not exist - cannot update coupon amount");
            return false;
        }
        
        try {
            // Get current coupon to update settings
            $coupon = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->first();
            
            if (!$coupon) {
                error_log("Gift Certificate: Coupon not found for update - Code: {$coupon_code}");
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
                error_log("Gift Certificate: Fluent Forms coupon amount updated successfully - Code: {$coupon_code}, New Amount: {$new_amount}");
                return true;
            } else {
                error_log("Gift Certificate: Failed to update Fluent Forms coupon amount - Code: {$coupon_code}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Failed to update Fluent Forms coupon amount: " . $e->getMessage());
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
            return $table_exists;
        } catch (Exception $e) {
            error_log("Gift Certificate: Error checking table '{$table_name}': " . $e->getMessage());
            return false;
        }
    }
    
    public function get_gift_certificate_balance($coupon_code) {
        $gift_certificate = $this->database->get_gift_certificate_by_coupon_code($coupon_code);
        
        if (!$gift_certificate) {
            return false;
        }
        
        return array(
            'balance' => $gift_certificate->current_balance,
            'original_amount' => $gift_certificate->original_amount,
            'status' => $gift_certificate->status,
            'recipient_name' => $gift_certificate->recipient_name
        );
    }
    
    public function validate_coupon_for_balance_check($coupon_code) {
        // Additional validation for balance checking
        if (empty($coupon_code)) {
            return false;
        }
        
        // Check format
        if (!preg_match('/^GC[A-Z0-9]{8}$/', $coupon_code)) {
            return false;
        }
        
        // Check if exists and is active
        $gift_certificate = $this->database->get_gift_certificate_by_coupon_code($coupon_code);
        
        return $gift_certificate && $gift_certificate->status === 'active';
    }
} 