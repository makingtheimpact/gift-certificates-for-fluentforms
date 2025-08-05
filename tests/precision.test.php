<?php
// Basic assertions to verify precision handling.

// Define ABSPATH to satisfy plugin files.
define('ABSPATH', __DIR__ . '/../');

// Stub WordPress functions used in constructors or methods.
function add_filter() {}
function add_action() {}
function do_action() {}
function gcff_log() {}

// Setup a stub wpdb implementation.
class WPDBStub {
    public $prefix = 'wp_';
    public $updates = array();
    public function update($table, $data, $where, $format_data = array(), $format_where = array()) {
        $this->updates[] = array('table' => $table, 'data' => $data, 'where' => $where);
        return true;
    }
    public function get_results($query) { return array('design_id'); }
    public function prepare($query) { return $query; }
    public function query($sql) { return true; }
}

global $wpdb;
$wpdb = new WPDBStub();

require_once __DIR__ . '/../includes/class-gift-certificate-database.php';
require_once __DIR__ . '/../includes/class-gift-certificate-coupon.php';

use GiftCertificatesFluentForms\GiftCertificateDatabase;
use GiftCertificatesFluentForms\GiftCertificateCoupon;

// Helper subclass to access protected precision calculation.
class CouponTest extends GiftCertificateCoupon {
    public function __construct() {}
    public function calc($balance, $amount) {
        return $this->calculate_new_balance($balance, $amount);
    }
}

// Test precision subtraction with large values.
$coupon = new CouponTest();
$result = $coupon->calc(999999999.99, 0.01);
assert($result['new_balance'] == 999999999.98);

// Test precision with repeating decimals.
$result = $coupon->calc(0.3, 0.1);
assert($result['new_balance'] == 0.2);

// Test rounding of fractional cents.
$result = $coupon->calc(10.00, 0.333333);
assert($result['new_balance'] == 9.67);

// Test overdraft protection.
$result = $coupon->calc(5, 10);
assert($result['new_balance'] == 0.0);
assert($result['amount_used'] == 5.0);

// Test database rounding behaviour.
$db = new GiftCertificateDatabase();
$db->update_gift_certificate_balance(1, 10.555);
assert($wpdb->updates[0]['data']['current_balance'] == 10.56);

$db->update_gift_certificate_balance(2, 10.554);
assert($wpdb->updates[1]['data']['current_balance'] == 10.55);

echo "All precision tests passed.\n";
