<?php
// Verify GiftCertificateWebhook::calculate_order_total uses configured subtotal field.

define('ABSPATH', __DIR__ . '/../');

function gcff_log() {}

require_once __DIR__ . '/../includes/class-gift-certificate-webhook.php';

use GiftCertificatesFluentForms\GiftCertificateWebhook;

class WebhookOrderTotalTest extends GiftCertificateWebhook {
    public function __construct() {}
    public function total($form_data) {
        $ref = new ReflectionClass(GiftCertificateWebhook::class);
        $method = $ref->getMethod('calculate_order_total');
        $method->setAccessible(true);
        return $method->invoke($this, $form_data);
    }
}

$webhook = new WebhookOrderTotalTest();
$ref     = new ReflectionClass(GiftCertificateWebhook::class);
$prop    = $ref->getProperty('settings');
$prop->setAccessible(true);
$prop->setValue($webhook, array('order_total_field_name' => 'cart_total'));

$form_data = array(
    'cart_total' => '50',
    'payment_input' => '100',
    'total' => '30',
);

assert($webhook->total($form_data) === 20.0);

echo "Webhook order total test passed.\n";
