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
    public $balances = array();

    public function update($table, $data, $where, $format_data = array(), $format_where = array()) {
        $id = $where['id'];
        $this->updates[] = array('table' => $table, 'data' => $data, 'where' => $where);
        $this->balances[$id] = $data['current_balance'];
        return 1;
    }

    public function get_var($query) {
        if (preg_match('/WHERE id = (\d+)/', $query, $m)) {
            $id = intval($m[1]);
            return $this->balances[$id] ?? null;
        }
        return null;
    }

    public function get_results($query) { return array('design_id'); }

    public function prepare($query, ...$args) {
        if ($args) {
            foreach ($args as $arg) {
                $query = preg_replace('/%[dsf]/', is_numeric($arg) ? $arg : $arg, $query, 1);
            }
        }
        return $query;
    }

    public function query($sql) { return true; }
}

global $wpdb;
$wpdb = new WPDBStub();
$wpdb->balances = array(1 => 100.00, 2 => 50.00);

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

// Test database rounding behaviour and subtraction logic.
$db = new GiftCertificateDatabase();
$result = $db->update_gift_certificate_balance(1, 10.555);
assert(abs($wpdb->balances[1] - 89.44) < 0.001);
assert(abs($result['amount_used'] - 10.56) < 0.001);

$result = $db->update_gift_certificate_balance(2, 10.554);
assert(abs($wpdb->balances[2] - 39.45) < 0.001);
assert(abs($result['amount_used'] - 10.55) < 0.001);

echo "All precision tests passed.\n";
