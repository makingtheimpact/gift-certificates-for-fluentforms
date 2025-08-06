<?php
/**
 * Database handler for gift certificates
 */
namespace GiftCertificatesFluentForms;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateDatabase {

    private $gift_certificates_table;
    private $transactions_table;
    private $scale = 4;
    
    public function __construct() {
        global $wpdb;
        $this->gift_certificates_table = $wpdb->prefix . 'gift_certificates';
        $this->transactions_table = $wpdb->prefix . 'gift_certificate_transactions';
        $this->scale = (int) apply_filters('gcff_decimal_scale', $this->scale);
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Gift certificates table
        $sql_gift_certificates = "CREATE TABLE {$this->gift_certificates_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            coupon_code varchar(50) NOT NULL,
            original_amount decimal(15,4) NOT NULL,
            current_balance decimal(15,4) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255) NOT NULL,
            sender_name varchar(255) NOT NULL,
            message text,
            delivery_date date DEFAULT NULL,
            design_id varchar(50) DEFAULT 'default',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY coupon_code (coupon_code),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY delivery_date (delivery_date),
            KEY design_id (design_id)
        ) $charset_collate;";
        
        // Transactions table for tracking usage
        $sql_transactions = "CREATE TABLE {$this->transactions_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            gift_certificate_id bigint(20) unsigned NOT NULL,
            amount_used decimal(15,4) NOT NULL,
            order_id varchar(255) DEFAULT NULL,
            form_submission_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY gift_certificate_id (gift_certificate_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_gift_certificates);
        dbDelta($sql_transactions);
        
        // Add foreign key constraint after table creation
        $this->add_foreign_key_constraint();
    }
    
    public function create_gift_certificate($data) {
        global $wpdb;
        
        $defaults = array(
            'coupon_code' => '',
            'original_amount' => '0',
            'current_balance' => '0',
            'recipient_email' => '',
            'recipient_name' => '',
            'sender_name' => '',
            'message' => '',
            'delivery_date' => null,
            'design_id' => 'default',
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $data = array_map('sanitize_text_field', $data);
        $data['message'] = sanitize_textarea_field($data['message']);
        $data['original_amount'] = $this->sanitize_amount($data['original_amount']);
        $data['current_balance'] = $this->sanitize_amount($data['current_balance']);
        
        $result = $wpdb->insert(
            $this->gift_certificates_table,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public function get_gift_certificate($id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->gift_certificates_table} WHERE id = %d",
                $id
            )
        );
    }
    
    public function get_gift_certificate_by_coupon_code($coupon_code) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->gift_certificates_table} WHERE coupon_code = %s",
                $coupon_code
            )
        );
    }
    
    public function get_active_gift_certificate_by_coupon_code($coupon_code) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->gift_certificates_table} WHERE coupon_code = %s AND status IN ('active', 'delivered')",
                $coupon_code
            )
        );
    }
    
    public function update_gift_certificate_balance($id, $amount) {
        global $wpdb;

        // Fetch current balance for the certificate
        $current_balance = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT current_balance FROM {$this->gift_certificates_table} WHERE id = %d",
                $id
            )
        );

        if ($current_balance === null) {
            return false;
        }

        $current_balance = $this->sanitize_amount($current_balance);
        $amount = $this->sanitize_amount($amount);

        if (bccomp($amount, $current_balance, $this->scale) === 1) {
            $amount = $current_balance;
        }

        $new_balance = bcsub($current_balance, $amount, $this->scale);

        $result = $wpdb->update(
            $this->gift_certificates_table,
            array('current_balance' => $new_balance),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return false;
        }

        if (bccomp($new_balance, '0', $this->scale) <= 0) {
            $this->update_gift_certificate_status($id, 'expired');

            $gift_certificate = $this->get_gift_certificate($id);
            if ($gift_certificate && !empty($gift_certificate->coupon_code)) {
                do_action('gcff_gift_certificate_balance_zero', $gift_certificate->coupon_code);
            }
        }

        return array(
            'rows_affected' => $result,
            'new_balance'   => $new_balance,
            'amount_used'   => $amount,
        );
    }
    
    public function update_gift_certificate_status($id, $status) {
        global $wpdb;
        
        return $wpdb->update(
            $this->gift_certificates_table,
            array('status' => $status),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }
    
    public function update_gift_certificate($id, $data) {
        global $wpdb;

        // Sanitize data
        $sanitized_data = array();
        $format = array();

        if (isset($data['coupon_code'])) {
            $sanitized_data['coupon_code'] = sanitize_text_field($data['coupon_code']);
            $format[] = '%s';
        }

        if (isset($data['original_amount'])) {
            $sanitized_data['original_amount'] = $this->sanitize_amount($data['original_amount']);
            $format[] = '%s';
        }

        if (isset($data['current_balance'])) {
            $sanitized_data['current_balance'] = $this->sanitize_amount($data['current_balance']);
            $format[] = '%s';
        }

        if (isset($data['recipient_email'])) {
            $sanitized_data['recipient_email'] = sanitize_email($data['recipient_email']);
            $format[] = '%s';
        }

        if (isset($data['recipient_name'])) {
            $sanitized_data['recipient_name'] = sanitize_text_field($data['recipient_name']);
            $format[] = '%s';
        }

        if (isset($data['sender_name'])) {
            $sanitized_data['sender_name'] = sanitize_text_field($data['sender_name']);
            $format[] = '%s';
        }

        if (isset($data['message'])) {
            $sanitized_data['message'] = sanitize_textarea_field($data['message']);
            $format[] = '%s';
        }

        if (isset($data['delivery_date'])) {
            $sanitized_data['delivery_date'] = sanitize_text_field($data['delivery_date']);
            $format[] = '%s';
        }

        if (isset($data['design_id'])) {
            $sanitized_data['design_id'] = sanitize_text_field($data['design_id']);
            $format[] = '%s';
        }

        if (isset($data['status'])) {
            $sanitized_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        if (empty($sanitized_data)) {
            return false;
        }

        return $wpdb->update(
            $this->gift_certificates_table,
            $sanitized_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }
    
    public function delete_gift_certificate($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->gift_certificates_table,
            array('id' => $id),
            array('%d')
        );
    }
    
    public function record_transaction($gift_certificate_id, $amount_used, $order_id = null, $form_submission_id = null) {
        global $wpdb;
        
        $amount_used = $this->sanitize_amount($amount_used);

        return $wpdb->insert(
            $this->transactions_table,
            array(
                'gift_certificate_id' => $gift_certificate_id,
                'amount_used' => $amount_used,
                'order_id' => $order_id,
                'form_submission_id' => $form_submission_id
            ),
            array('%d', '%s', '%s', '%d')
        );
    }
    
    public function get_gift_certificates($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'active',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clause .= " AND status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['recipient_email'])) {
            $where_clause .= " AND recipient_email = %s";
            $where_values[] = $args['recipient_email'];
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        $sql = "SELECT * FROM {$this->gift_certificates_table} 
                {$where_clause} 
                ORDER BY {$orderby} 
                LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    public function get_gift_certificates_count($args = array()) {
        global $wpdb;
        
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clause .= " AND status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['recipient_email'])) {
            $where_clause .= " AND recipient_email = %s";
            $where_values[] = $args['recipient_email'];
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->gift_certificates_table} {$where_clause}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_var($sql);
    }
    
    public function get_transactions($gift_certificate_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->transactions_table} 
                 WHERE gift_certificate_id = %d 
                 ORDER BY created_at DESC",
                $gift_certificate_id
            )
        );
    }
    
    public function get_pending_deliveries() {
        global $wpdb;

        $current_date = current_time('Y-m-d');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->gift_certificates_table}
                 WHERE delivery_date <= %s
                 AND status = 'pending_delivery'",
                $current_date
            )
        );
    }
    
    public function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total gift certificates
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->gift_certificates_table}");
        
        // Active gift certificates
        $stats['active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->gift_certificates_table} WHERE status = 'active'");
        
        // Total value issued
        $stats['total_value'] = (float) $wpdb->get_var("SELECT SUM(original_amount) FROM {$this->gift_certificates_table}");
        
        // Total value redeemed
        $stats['total_redeemed'] = (float) $wpdb->get_var("SELECT SUM(amount_used) FROM {$this->transactions_table}");
        
        // Pending deliveries
        $stats['pending_delivery'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->gift_certificates_table} WHERE status = 'pending_delivery'");
        
        // Ensure we have valid numeric values (not null)
        $stats['total'] = $stats['total'] ?: 0;
        $stats['active'] = $stats['active'] ?: 0;
        $stats['total_value'] = $stats['total_value'] ?: 0.0;
        $stats['total_redeemed'] = $stats['total_redeemed'] ?: 0.0;
        $stats['pending_delivery'] = $stats['pending_delivery'] ?: 0;
        
        return $stats;
    }

    private function sanitize_amount($amount) {
        $amount = preg_replace('/[^0-9.]/', '', (string) $amount);
        if ($amount === '') {
            $amount = '0';
        }
        return bcadd($amount, '0', $this->scale);
    }

    private function add_foreign_key_constraint() {
        global $wpdb;

        // Check if foreign key constraint already exists
        try {
            $constraint_exists = $wpdb->get_var("
                SELECT COUNT(*)
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$this->transactions_table}'
                AND REFERENCED_TABLE_NAME = '{$this->gift_certificates_table}'
            ");

            if ($constraint_exists === null) {
                gcff_log('Gift Certificate: Unable to verify existing foreign key constraint - skipping', 'warning');
                return;
            }
        } catch (\Throwable $e) {
            gcff_log('Gift Certificate: Error checking foreign key constraint - ' . $e->getMessage(), 'warning');
            return;
        }

        if (!$constraint_exists) {
            // Add foreign key constraint
            $wpdb->query("
                ALTER TABLE {$this->transactions_table}
                ADD CONSTRAINT fk_gift_certificate_transactions
                FOREIGN KEY (gift_certificate_id)
                REFERENCES {$this->gift_certificates_table}(id)
                ON DELETE CASCADE
            ");
        }
    }

    /**
     * Add the design_id column to the gift certificates table if it does not already exist
     */
    public function maybe_add_design_id_column() {
        global $wpdb;

        $column = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$this->gift_certificates_table} LIKE %s",
                'design_id'
            )
        );

        if (empty($column)) {
            $wpdb->query(
                "ALTER TABLE {$this->gift_certificates_table} ADD COLUMN design_id varchar(50) DEFAULT 'default' AFTER delivery_date"
            );
        }
    }
}

