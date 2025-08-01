<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

// Delete plugin options
delete_option('gift_certificates_ff_settings');

// Remove custom tables
global $wpdb;
$gift_certificates_table = $wpdb->prefix . 'gift_certificates';
$transactions_table = $wpdb->prefix . 'gift_certificate_transactions';

$wpdb->query("DROP TABLE IF EXISTS {$gift_certificates_table}");
$wpdb->query("DROP TABLE IF EXISTS {$transactions_table}");

