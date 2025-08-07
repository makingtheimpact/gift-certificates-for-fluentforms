# PHP Tests

This directory contains lightweight PHP scripts that exercise key coupon and balance logic without requiring a full WordPress environment.

## Requirements

- PHP 7.4 or higher
- [BCMath](https://www.php.net/manual/en/book.bc.php) extension for precise arithmetic. The plugin provides polyfills in `includes/gcff-functions.php`, but having the extension installed matches production behavior.

## Hidden discount field

In production forms a hidden field (`gc_discount_applied` by default) records how much a gift certificate reduced the order total. The webhook uses this value to deduct the correct amount from a certificate's balance. These tests emulate the hidden field by injecting the discount directly into the `$form_data` array (via `payment_summary['discount']`) just as the field would during a real submission.

## Running tests

Execute each script from the project root:

```bash
php tests/order-total.test.php
php tests/precision.test.php
```

To run everything at once:

```bash
for file in tests/*.test.php; do
    php "$file"
done
```

Each script prints a success message when all assertions pass.
