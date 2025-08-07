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

// Provide minimal WordPress option and filter handling for tests.
function get_option($name, $default = array()) {
    return $default;
}

$GLOBALS['gcff_order_total_fields'] = null;
function apply_filters($tag, $value, ...$args) {
    global $gcff_order_total_fields;
    if ($tag === 'gcff_order_total_fields' && is_array($gcff_order_total_fields)) {
        return $gcff_order_total_fields;
    }
    return $value;
}

require_once __DIR__ . '/../includes/class-gift-certificate-coupon.php';
require_once __DIR__ . '/../includes/class-gift-certificate-webhook.php';

use GiftCertificatesFluentForms\GiftCertificateCoupon;
use GiftCertificatesFluentForms\GiftCertificateWebhook;

// Helper subclass to expose the private calculate_order_total method.
class OrderTotalTestCoupon extends GiftCertificateCoupon {
    public function __construct() {}
    public function total($form_data) {
        $ref = new ReflectionClass(GiftCertificateCoupon::class);
        $method = $ref->getMethod('calculate_order_total');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
    public function discount($form_data) {
        $ref = new ReflectionClass(GiftCertificateCoupon::class);
        $method = $ref->getMethod('get_payment_summary_discount');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
}

$coupon = new OrderTotalTestCoupon();

// Helper subclass to expose the private calculate_order_total method on the webhook.
class OrderTotalTestWebhook extends GiftCertificateWebhook {
    public function __construct() {}
    public function total($form_data) {
        $ref = new ReflectionClass(GiftCertificateWebhook::class);
        $method = $ref->getMethod('calculate_order_total');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
    public function discount($form_data) {
        $ref = new ReflectionClass(GiftCertificateWebhook::class);
        $method = $ref->getMethod('get_payment_summary_discount');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
}

$webhook = new OrderTotalTestWebhook();

// --- Test single payment field with quantity ---
$gcff_order_total_fields = null;
$form_data = array(
    'payment_input' => '10',
    'payment_input_quantity' => '2',
);
assert($coupon->total($form_data) === '20.0000');

// --- Test multiple payment fields with individual quantities ---
$gcff_order_total_fields = array('payment_input', 'additional_payment');
$form_data = array(
    'payment_input' => '10',
    'payment_input_quantity' => '2',
    'additional_payment' => '5',
    'additional_payment_quantity' => '3',
);
assert($coupon->total($form_data) === '35.0000');

// --- Test multiple payment fields with global quantity fallback ---
$gcff_order_total_fields = array('payment_input', 'additional_payment');
$form_data = array(
    'payment_input' => '10',
    'additional_payment' => '5',
    'quantity' => '2',
);
assert($coupon->total($form_data) === '30.0000');

// --- Test zero quantity ---
$gcff_order_total_fields = null;
$form_data = array(
    'payment_input' => '10',
    'payment_input_quantity' => '0',
);
assert($coupon->total($form_data) === '0.0000');

// --- Test payment summary subtotal ---
$gcff_order_total_fields = null;
$form_data = array(
    'payment_summary' => array(
        'items' => array(
            array('label' => 'Product A', 'quantity' => 1, 'price' => 50),
            array('label' => 'Product B', 'quantity' => 2, 'price' => 30),
        ),
        'subtotal' => 110,
        'discount' => 10,
        'total' => 100,
    ),
);
assert($coupon->total($form_data) === '110.0000');
assert($webhook->total($form_data) === '110.0000');
assert($coupon->discount($form_data) === '10.0000');
assert($webhook->discount($form_data) === '10.0000');

// --- Webhook: test array values with quantity ---
$gcff_order_total_fields = null;
$form_data = array(
    'payment_input' => array('Item A - $5', 'Item B - $2.50'),
    'payment_input_quantity' => '3',
);
assert($webhook->total($form_data) === '22.5000');

// --- Webhook: multiple fields with global quantity fallback ---
$gcff_order_total_fields = array('payment_input', 'additional_payment');
$form_data = array(
    'payment_input' => 'Price $10',
    'additional_payment' => 'Fee $5',
    'qty' => '2',
);
assert($webhook->total($form_data) === '30.0000');

// --- Webhook: ignore numeric identifiers in labels ---
$gcff_order_total_fields = null;
$form_data = array(
    'payment_input' => array('Payment Item 2 $50', 'Payment Item 3 $30'),
);
assert($webhook->total($form_data) === '80.0000');

// --- Test no matching payment fields ---
$gcff_order_total_fields = null;
$form_data = array();
assert($coupon->total($form_data) === '0.0000');

echo "All order total tests passed.\n";
