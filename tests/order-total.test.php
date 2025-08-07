<?php
namespace {
// Tests for coupon redemptions via public methods without using reflection.

// Define ABSPATH to satisfy plugin files.
define('ABSPATH', __DIR__ . '/../');

// Stub WordPress functions used by the plugin.
function add_filter() {}
function add_action() {}
function do_action() {}
function has_action() { return false; }
function gcff_log() {}
function current_time($type) { return '2024-01-01 00:00:00'; }
function delete_transient($name) {}
function sanitize_key($key) { return $key; }
function gcff_mask_coupon_code($code) { return $code; }
function gcff_mask_email($email) { return $email; }
function gcff_mask_string($string) { return $string; }

// Minimal filter handler for order total fields.
$GLOBALS['gcff_order_total_fields'] = null;
function apply_filters($tag, $value, ...$args) {
    global $gcff_order_total_fields;
    if ($tag === 'gcff_order_total_fields' && is_array($gcff_order_total_fields)) {
        return $gcff_order_total_fields;
    }
    return $value;
}

// Simple options store.
$options = array();
function get_option($name, $default = array()) {
    global $options;
    return $options[$name] ?? $default;
}

// Stub wpdb implementation.
$GLOBALS['wpdb'] = new class {
    public $prefix = 'wp_';
    public function get_var($query) { return null; }
    public function prepare($query, $arg = null) { return $query; }
    public function get_results($query) { return array(); }
    public function query($query) { return 0; }
};
}

// Stub Submission model used in coupon processing.
namespace FluentForm\App\Models {
    class Submission {
        public static function find($id) { return null; }
    }
}

// Stub database layer shared by coupon and webhook classes.
namespace GiftCertificatesFluentForms {
    class GiftCertificateDatabase {
        public static $gift_certificate;
        public static $last_transaction;

        public function __construct() {
            if (self::$gift_certificate === null) {
                self::reset();
            }
        }

        public static function reset($balance = '50.0000') {
            self::$gift_certificate = (object) array(
                'id' => 1,
                'current_balance' => $balance,
                'status' => 'active',
            );
            self::$last_transaction = null;
        }

        public function get_gift_certificate_by_coupon_code($code) {
            return self::$gift_certificate;
        }

        public function get_active_gift_certificate_by_coupon_code($code) {
            return self::$gift_certificate;
        }

        public function update_gift_certificate_balance($id, $amount) {
            $amount = bcadd((string) $amount, '0', 4);
            $new_balance = bcsub(self::$gift_certificate->current_balance, $amount, 4);
            self::$gift_certificate->current_balance = $new_balance;
            return array(
                'rows_affected' => 1,
                'new_balance'   => $new_balance,
                'amount_used'   => $amount,
            );
        }

        public function record_transaction($gift_certificate_id, $amount, $order_id, $submission_id) {
            self::$last_transaction = array(
                'gift_certificate_id' => $gift_certificate_id,
                'amount'              => $amount,
                'order_id'            => $order_id,
                'submission_id'       => $submission_id,
            );
        }
    }
}

namespace {
// Include plugin classes now that stubs are defined.
require_once __DIR__ . '/../includes/class-gift-certificate-coupon.php';
require_once __DIR__ . '/../includes/class-gift-certificate-webhook.php';

use GiftCertificatesFluentForms\GiftCertificateCoupon;
use GiftCertificatesFluentForms\GiftCertificateWebhook;
use GiftCertificatesFluentForms\GiftCertificateDatabase;

// --- Coupon integration: track_coupon_usage processes discount ---
GiftCertificateDatabase::reset('50.0000');

$coupon_instance = new GiftCertificateCoupon();

$coupon = (object) array('code' => 'GC12345678');
$form_data = array(
    'gc_discount_applied' => '10',
    'payment_summary' => array(
        'items'    => array(),
        'subtotal' => 50,
        'discount' => 10,
        'total'    => 40,
    ),
);

$coupon_instance->track_coupon_usage($coupon, $form_data, 1);

assert(GiftCertificateDatabase::$last_transaction['amount'] === '10.0000');
assert(GiftCertificateDatabase::$gift_certificate->current_balance === '40.0000');

// --- Webhook: successful redemption with valid hidden field ---
GiftCertificateDatabase::reset('50.0000');
$options['gift_certificates_ff_settings'] = array(
    'allowed_form_ids'    => array('123'),
    'discount_field_name' => 'gc_discount_applied',
    'gift_certificate_form_id' => '999',
);

$webhook_instance = new GiftCertificateWebhook();
$form = (object) array('id' => 123);
$form_data = array(
    '__ff_all_applied_coupons' => json_encode(array('GC12345678')),
    'gc_discount_applied'      => '15',
);

$webhook_instance->handle_form_submission(1, $form_data, $form);

assert(GiftCertificateDatabase::$last_transaction['amount'] === '15.0000');
assert(GiftCertificateDatabase::$gift_certificate->current_balance === '35.0000');

// --- Webhook: missing hidden field ---
GiftCertificateDatabase::reset('50.0000');
$webhook_instance = new GiftCertificateWebhook();
$form_data = array(
    '__ff_all_applied_coupons' => json_encode(array('GC12345678')),
);
GiftCertificateDatabase::$last_transaction = null;
$webhook_instance->handle_form_submission(2, $form_data, $form);
assert(GiftCertificateDatabase::$last_transaction === null);
assert(GiftCertificateDatabase::$gift_certificate->current_balance === '50.0000');

// --- Webhook: zero discount value ---
GiftCertificateDatabase::reset('50.0000');
$webhook_instance = new GiftCertificateWebhook();
$form_data = array(
    '__ff_all_applied_coupons' => json_encode(array('GC12345678')),
    'gc_discount_applied'      => '0',
);
GiftCertificateDatabase::$last_transaction = null;
$webhook_instance->handle_form_submission(3, $form_data, $form);
assert(GiftCertificateDatabase::$last_transaction === null);
assert(GiftCertificateDatabase::$gift_certificate->current_balance === '50.0000');

echo "All order total tests passed.\n";
}
