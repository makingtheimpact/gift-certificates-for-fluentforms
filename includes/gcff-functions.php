<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gcff_log')) {
    function gcff_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
}

