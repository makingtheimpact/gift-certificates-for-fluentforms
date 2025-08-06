<?php
// Tests for calculating order totals with multiple payment fields and quantities.

// Define ABSPATH to satisfy plugin files.
define('ABSPATH', __DIR__ . '/../');

// Stub WordPress functions used in the method.
function add_filter() {}
function add_action() {}
function do_action() {}
function has_action() { return false; }
function gcff_log() {}

// Storage for settings that calculate_order_total reads.
$GLOBALS['gcff_test_settings'] = array();
function get_option($name, $default = array()) {
    global $gcff_test_settings;
    if ($name === 'gift_certificates_ff_settings') {
        return $gcff_test_settings;
    }
    return $default;
}

// Basic passthrough apply_filters implementation.
function apply_filters($tag, $value, ...$args) { return $value; }

require_once __DIR__ . '/../includes/class-gift-certificate-coupon.php';

use GiftCertificatesFluentForms\GiftCertificateCoupon;

// Helper subclass to expose the private calculate_order_total method.
class OrderTotalTestCoupon extends GiftCertificateCoupon {
    public function __construct() {}
    public function total($form_data) {
        $ref = new ReflectionClass(GiftCertificateCoupon::class);
        $method = $ref->getMethod('calculate_order_total');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
}

$coupon = new OrderTotalTestCoupon();

// --- Test single payment field with quantity ---
$gcff_test_settings = array('order_total_field_name' => 'payment_input');
$form_data = array(
    'payment_input' => '10',
    'payment_input_quantity' => '2',
);
assert($coupon->total($form_data) === '20.0000');

// --- Test multiple payment fields with individual quantities ---
$gcff_test_settings = array('order_total_field_name' => 'payment_input,additional_payment');
$form_data = array(
    'payment_input' => '10',
    'payment_input_quantity' => '2',
    'additional_payment' => '5',
    'additional_payment_quantity' => '3',
);
assert($coupon->total($form_data) === '35.0000');

// --- Test no matching payment fields ---
$gcff_test_settings = array('order_total_field_name' => 'payment_input');
$form_data = array();
assert($coupon->total($form_data) === '0.0000');

echo "All order total tests passed.\n";
