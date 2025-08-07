<?php
/**
 * Coupon integration with Fluent Forms Pro
 */
namespace GiftCertificatesFluentForms;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateCoupon {

    private $database;
    protected $scale = 4;

    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->scale = (int) apply_filters('gcff_decimal_scale', $this->scale);
        
        // Hook into Fluent Forms Pro coupon validation
        add_filter('fluentformpro_coupon_validation', array($this, 'validate_gift_certificate_coupon'), 10, 3);

        // Register coupon usage hook once all plugins are loaded so we can
        // detect which Fluent Forms coupon hook is available.
        add_action('plugins_loaded', array($this, 'register_coupon_usage_hooks'));

        // Listen for gift certificates reaching zero balance to deactivate coupons
        add_action('gcff_gift_certificate_balance_zero', array($this, 'deactivate_fluent_forms_coupon'));
    }
    
    public function validate_gift_certificate_coupon($is_valid, $coupon, $form_data) {
        // Check if this is a gift certificate coupon
        if (!$this->is_gift_certificate_coupon($coupon)) {
            return $is_valid;
        }

        gcff_log('Gift certificate validation for code: ' . $coupon->code);
        gcff_log('Gift certificate form data: ' . json_encode($form_data));

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
        if (bccomp($gift_certificate->current_balance, '0', $this->scale) <= 0) {
            return false;
        }
        
        // Check if the current form is allowed for redemption
        $current_form_id = $this->get_current_form_id($form_data);
        if ($current_form_id && !$this->is_form_allowed_for_redemption($current_form_id)) {
            return false;
        }

        // Check the order total and set coupon amount accordingly
        $order_total = $this->calculate_order_total($form_data);
        gcff_log('Gift certificate order total: ' . $order_total . ', Current balance: ' . $gift_certificate->current_balance);
        $coupon->amount = bccomp($order_total, $gift_certificate->current_balance, $this->scale) === 1
            ? $gift_certificate->current_balance
            : $order_total;

        return true;
    }

    /**
     * Register coupon usage hooks based on available Fluent Forms actions.
     * Falls back to the free version hook and logs a warning when neither is
     * present so site owners know to upgrade or enable the coupon module.
     */
    public function register_coupon_usage_hooks() {
        if (has_action('fluentformpro_coupon_used')) {
            add_action('fluentformpro_coupon_used', array($this, 'track_coupon_usage'), 10, 3);
        } elseif (has_action('fluentform_coupon_used')) {
            add_action('fluentform_coupon_used', array($this, 'track_coupon_usage'), 10, 3);
        } else {
            gcff_log('Gift Certificate: Fluent Forms coupon usage hooks not detected. Update Fluent Forms Pro to version 5.0 or later or ensure the coupon module is active.');
        }
    }
    
    
    public function track_coupon_usage($coupon, $form_data, $submission_id) {
        // Check if this is a gift certificate coupon
        if (!$this->is_gift_certificate_coupon($coupon)) {
            return;
        }

        gcff_log('Tracking gift certificate coupon: ' . $coupon->code);
        gcff_log('Gift certificate form data: ' . json_encode($form_data));
        gcff_log('Gift certificate coupon amount: ' . (isset($coupon->amount) ? $coupon->amount : ''));

        // Look up the related gift certificate
        $gift_certificate = $this->database->get_gift_certificate_by_coupon_code($coupon->code);

        if (!$gift_certificate) {
            return;
        }

        // Determine the amount used. Prefer discount recorded in submission meta,
        // then fall back to any payment summary field or coupon/order totals
        $amount_used = $this->get_submission_discount($submission_id);
        if ($amount_used !== null) {
            gcff_log('Gift certificate discount from submission: ' . $amount_used);
        } else {
            $amount_used = $this->get_payment_summary_discount($form_data);
            if ($amount_used !== null) {
                gcff_log('Gift certificate discount from payment summary: ' . $amount_used);
            } else {
                $amount_used = '0';
                if (isset($coupon->amount)) {
                    $amount_used = $this->sanitize_amount($coupon->amount);
                }

                if (bccomp($amount_used, '0', $this->scale) !== 1) {
                    $amount_used = $this->calculate_order_total($form_data);
                }
                gcff_log('Gift certificate amount used after recalculation: ' . $amount_used);
            }
        }

        // Update balance in the database and retrieve the actual amount used
        $update_result = $this->database->update_gift_certificate_balance($gift_certificate->id, $amount_used);

        if ($update_result === false || empty($update_result['rows_affected'])) {
            gcff_log("Gift certificate balance update conflict: ID {$gift_certificate->id}");
            return;
        }

        $new_balance = $update_result['new_balance'];
        $amount_used = $update_result['amount_used'];
        gcff_log('Gift certificate new balance: ' . $new_balance);

        // Record transaction
        $this->database->record_transaction(
            $gift_certificate->id,
            $amount_used,
            null,
            $submission_id
        );

        // Update Fluent Forms Pro coupon amount and reset usage if there's remaining balance
        if (bccomp($new_balance, '0', $this->scale) === 1) {
            $this->update_fluent_forms_coupon_amount($coupon->code, $new_balance);

            $coupon_table_name = $this->get_coupon_table_name();
            if ($this->table_exists($coupon_table_name)) {
                try {
                    wpFluent()->table($coupon_table_name)
                        ->where('code', $coupon->code)
                        ->update(array(
                            'usage_count' => 0,
                            'max_use'     => 0,
                            'updated_at'  => current_time('mysql')
                        ));
                } catch (\Exception $e) {
                    gcff_log("Gift Certificate: Failed to reset coupon usage: " . $e->getMessage());
                }
            }
        }

        // Remove from transient
        delete_transient("gift_certificate_{$submission_id}");

        // Log transaction
        gcff_log("Gift certificate used: ID {$gift_certificate->id}, Amount: {$amount_used}, New Balance: {$new_balance}");
    }

    /**
     * Perform subtraction using bcmath to maintain precision.
     *
     * @param string|float $balance Current balance.
     * @param string|float $amount  Amount to subtract.
     *
     * @return array{amount_used: string, new_balance: string}
     */
    protected function calculate_new_balance($balance, $amount) {
        $balance = $this->sanitize_amount($balance);
        $amount  = $this->sanitize_amount($amount);

        if (bccomp($amount, $balance, $this->scale) === 1) {
            $amount = $balance;
        }

        $new_balance = bcsub($balance, $amount, $this->scale);

        return array(
            'amount_used' => $amount,
            'new_balance' => $new_balance,
        );
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
        // Determine the total order amount from form data.
        // First, check if a payment summary field is present and use its subtotal.
        foreach ($form_data as $key => $value) {
            if (is_array($value) && isset($value['subtotal']) && isset($value['items'])) {
                $subtotal = $this->sanitize_amount($value['subtotal']);
                if (bccomp($subtotal, '0', $this->scale) === 1) {
                    gcff_log("Gift certificate: Using payment summary field '{$key}' subtotal {$subtotal}");
                    return $subtotal;
                }
            }
        }

        $total = '0';

        // Allow admin configuration of total fields
        $settings = get_option('gift_certificates_ff_settings', array());
        $fields = array();

        if (!empty($settings['order_total_field_name'])) {
            $fields = array_map('trim', explode(',', $settings['order_total_field_name']));
        }

        // Allow developers to override or provide additional fields.
        // Multiple fields are supported; positive values across all fields will be summed.
        $fields = apply_filters('gcff_order_total_fields', $fields, $form_data);

        // Backwards compatibility - fallback to common field names if no configuration
        if (empty($fields)) {
            $fields = array('total', 'order_total', 'cart_total', 'amount', 'price', 'payment_input');
        }

        gcff_log('Gift certificate order total fields: ' . json_encode($fields));

        foreach ($fields as $field) {
            if (!isset($form_data[$field])) {
                continue;
            }

            $raw_value = $form_data[$field];
            $values    = is_array($raw_value) ? $raw_value : array($raw_value);

            $value_total = '0';
            foreach ($values as $item) {
                if (is_array($item)) {
                    $item = implode(' ', $item);
                }

                preg_match_all('/[0-9]+(?:\.[0-9]{2})?/', (string) $item, $matches);
                if (empty($matches[0])) {
                    continue;
                }

                $last_match  = end($matches[0]);
                $value_total = bcadd($value_total, $this->sanitize_amount($last_match), $this->scale);
            }

            if (bccomp($value_total, '0', $this->scale) !== 1) {
                continue;
            }

            // Determine quantity for this specific field. Look for field-specific quantity
            // inputs first, then fall back to common quantity field names.
            $quantity = null;
            $quantity_patterns = array(
                $field . '_quantity',
                $field . '_qty',
                $field . '-quantity',
                $field . '-qty',
            );
            foreach ($quantity_patterns as $qfield) {
                if (isset($form_data[$qfield])) {
                    $quantity = max(0, intval($form_data[$qfield]));
                    break;
                }
            }

            if ($quantity === null) {
                $global_quantity_fields = array('item-quantity', 'quantity', 'qty');
                foreach ($global_quantity_fields as $gfield) {
                    if (isset($form_data[$gfield])) {
                        $quantity = max(0, intval($form_data[$gfield]));
                        break;
                    }
                }
            }

            if ($quantity === null) {
                $quantity = 1;
            }

            if ($quantity === 0) {
                // Skip fields where quantity is explicitly zero.
                continue;
            }

            $field_total = bcmul($value_total, (string) $quantity, $this->scale);
            $total = bcadd($total, $field_total, $this->scale);
        }

        $total = $this->sanitize_amount($total);

        if (bccomp($total, '0', $this->scale) !== 1) {
            gcff_log('Gift Certificate: Unable to detect order total. Checked fields: ' . implode(', ', $fields));
        }

        return $total;
    }

    /**
     * Extract discount amount from a payment summary field
     */
    private function get_payment_summary_discount($form_data) {
        foreach ($form_data as $key => $value) {
            if (is_array($value) && isset($value['discount']) && isset($value['items'])) {
                gcff_log("Gift certificate: Payment summary field '{$key}': " . json_encode($value));
                $discount = $this->sanitize_amount($value['discount']);
                if (bccomp($discount, '0', $this->scale) === 1) {
                    return $discount;
                }
            }
        }
        return null;
    }

    /**
     * Retrieve discount amount from the stored submission payment summary meta
     */
    private function get_submission_discount($submission_id) {
        try {
            $submission = \FluentForm\App\Models\Submission::find($submission_id);
            if (!$submission) {
                return null;
            }

            $payment_meta = $submission->payment_summary;
            if (is_string($payment_meta)) {
                $payment_meta = json_decode($payment_meta, true);
            }

            if (!is_array($payment_meta)) {
                return null;
            }

            $discount = $this->sanitize_amount($payment_meta['discount'] ?? 0);

            if (bccomp($discount, '0', $this->scale) === 1) {
                return $discount;
            }
        } catch (\Throwable $e) {
            gcff_log('Gift certificate: Error fetching submission discount - ' . $e->getMessage());
        }

        return null;
    }

    private function sanitize_amount($amount) {
        $amount = preg_replace('/[^0-9.]/', '', (string) $amount);
        if ($amount === '') {
            $amount = '0';
        }
        return bcadd($amount, '0', $this->scale);
    }
    
    private function get_current_form_id($form_data) {
        // Try to get form ID from form data
        if (isset($form_data['form_id'])) {
            return intval($form_data['form_id']);
        }
        
        // Try to get from global variables
        global $fluentform_current_form_id;
        if (isset($fluentform_current_form_id)) {
            return intval($fluentform_current_form_id);
        }
        
        return null;
    }
    
    private function is_form_allowed_for_redemption($form_id) {
        $settings = get_option('gift_certificates_ff_settings', array());
        $allowed_form_ids = $settings['allowed_form_ids'] ?? array();
        
        // If no forms are specified, allow all forms
        if (empty($allowed_form_ids)) {
            return true;
        }
        
        // Convert form_id to string for comparison since allowed_form_ids are stored as strings
        $form_id_str = strval($form_id);
        
        // Check if the current form is in the allowed list
        return in_array($form_id_str, $allowed_form_ids);
    }
    
    public function deactivate_fluent_forms_coupon($coupon_code) {
        // Get the coupon table name
        $coupon_table_name = $this->get_coupon_table_name();
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            gcff_log("Gift Certificate: Coupon table '{$coupon_table_name}' does not exist - cannot deactivate coupon");
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
                gcff_log("Gift Certificate: Fluent Forms coupon deactivated successfully - Code: {$coupon_code}");
                return true;
            } else {
                gcff_log("Gift Certificate: Failed to deactivate Fluent Forms coupon - Code: {$coupon_code}");
                return false;
            }
            
        } catch (\Exception $e) {
            gcff_log("Failed to deactivate Fluent Forms coupon: " . $e->getMessage());
            return false;
        }
    }
    
    private function update_fluent_forms_coupon_amount($coupon_code, $new_amount) {
        // Get the coupon table name
        $coupon_table_name = $this->get_coupon_table_name();
        
        // Check if coupon table exists
        if (!$this->table_exists($coupon_table_name)) {
            gcff_log("Gift Certificate: Coupon table '{$coupon_table_name}' does not exist - cannot update coupon amount");
            return false;
        }
        
        try {
            // Get current coupon to update settings
            $coupon = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->first();
            
            if (!$coupon) {
                gcff_log("Gift Certificate: Coupon not found for update - Code: {$coupon_code}");
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
                gcff_log("Gift Certificate: Fluent Forms coupon amount updated successfully - Code: {$coupon_code}, New Amount: {$new_amount}");
                return true;
            } else {
                gcff_log("Gift Certificate: Failed to update Fluent Forms coupon amount - Code: {$coupon_code}");
                return false;
            }
            
        } catch (\Exception $e) {
            gcff_log("Failed to update Fluent Forms coupon amount: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the coupon table name (configurable)
     */
    private function get_coupon_table_name() {
        $settings = get_option('gift_certificates_ff_settings', array());
        $custom_table_name = isset($settings['coupon_table_name'])
            ? sanitize_key($settings['coupon_table_name'])
            : '';

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
            $table_exists = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $full_table_name)
            ) === $full_table_name;
            return $table_exists;
        } catch (\Exception $e) {
            gcff_log("Gift Certificate: Error checking table '{$full_table_name}': " . $e->getMessage());
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
            'status' => $gift_certificate->status
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
