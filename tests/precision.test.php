<?php
// Basic assertions to verify precision handling.

// Define ABSPATH to satisfy plugin files and enable logging.
define('ABSPATH', __DIR__ . '/../');
define('WP_DEBUG', true);

// Stub WordPress functions used in constructors or methods.
function add_filter() {}
function add_action() {}
function do_action() {}
function has_action() { return false; }
function apply_filters($tag, $value) { return $value; }
function get_option($name, $default = array()) { return array(); }

require_once __DIR__ . '/../includes/gcff-functions.php';

// Setup a stub wpdb implementation.
class WPDBStub {
    public $prefix = 'wp_';
    public $balances = array();
    public $rows_affected = 0;

    public function update($table, $data, $where, $format_data = array(), $format_where = array()) {
        $id = $where['id'];
        if (isset($data['current_balance'])) {
            $this->balances[$id] = $data['current_balance'];
        }
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

    public function query($sql) {
        if (preg_match('/current_balance\s*=\s*current_balance\s*-\s*([0-9.]+).*WHERE id\s*=\s*(\d+)\s+AND current_balance\s*>=\s*([0-9.]+)/', $sql, $m)) {
            $amount = $m[1];
            $id = intval($m[2]);
            $threshold = $m[3];
            $balance = $this->balances[$id] ?? null;

            if ($balance !== null && bccomp($balance, $threshold, 4) >= 0) {
                $this->balances[$id] = bcsub($balance, $amount, 4);
                $this->rows_affected = 1;
                return 1;
            }

            $this->rows_affected = 0;
            return 0;
        }

        return false;
    }
}

global $wpdb;
$wpdb = new WPDBStub();
$wpdb->balances = array(
    1 => '100.0000',
    2 => '50.0000',
);

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
$result = $coupon->calc('999999999.99', '0.01');
assert($result['new_balance'] === '999999999.9800');

// Test precision with repeating decimals.
$result = $coupon->calc('0.3', '0.1');
assert($result['new_balance'] === '0.2000');

// Test rounding of fractional cents (0.3333).
$result = $coupon->calc('10.00', '0.3333');
assert($result['new_balance'] === '9.6667');
assert($result['amount_used'] === '0.3333');

// Test overdraft protection.
$result = $coupon->calc('5', '10');
assert($result['new_balance'] === '0.0000');
assert($result['amount_used'] === '5.0000');

// Test handling of values like 1.275.
$result = $coupon->calc('2', '1.275');
assert($result['new_balance'] === '0.7250');

// Test database subtraction logic.
$db = new GiftCertificateDatabase();
$result = $db->update_gift_certificate_balance(1, '10.5555');
assert($wpdb->balances[1] === '89.4445');
assert($result['amount_used'] === '10.5555');

$result = $db->update_gift_certificate_balance(2, '0.3333');
assert($wpdb->balances[2] === '49.6667');
assert($result['amount_used'] === '0.3333');

$wpdb->balances[3] = '1.2750';
$result = $db->update_gift_certificate_balance(3, '0.3333');
assert($wpdb->balances[3] === '0.9417');

echo "All precision tests passed.\n";
