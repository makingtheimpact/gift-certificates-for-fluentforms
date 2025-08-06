<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gcff_log')) {
    function gcff_log($message, $level = 'debug') {
        $settings = get_option('gift_certificates_ff_settings', array());
        $enabled  = $settings['enable_logging'] ?? (defined('WP_DEBUG') && WP_DEBUG);
        $enabled  = apply_filters('gcff_enable_logging', $enabled, $level);

        if ($enabled) {
            error_log($message);
        }
    }
}

if (!function_exists('gcff_mask_email')) {
    function gcff_mask_email($email) {
        if (strpos($email, '@') !== false) {
            list($user, $domain) = explode('@', $email, 2);
            $user = substr($user, 0, 2) . str_repeat('*', max(0, strlen($user) - 2));
            return $user . '@' . $domain;
        }
        return $email;
    }
}

if (!function_exists('gcff_mask_string')) {
    function gcff_mask_string($string) {
        $string = (string) $string;
        if ($string === '') {
            return '';
        }
        return substr($string, 0, 1) . str_repeat('*', max(0, strlen($string) - 1));
    }
}

if (!function_exists('gcff_mask_coupon_code')) {
    function gcff_mask_coupon_code($code) {
        $code = (string) $code;
        return substr($code, 0, 4) . str_repeat('*', max(0, strlen($code) - 4));
    }
}

// Provide simple bcmath polyfills if the extension isn't available
if (!function_exists('bcadd')) {
    function bcadd($left_operand, $right_operand, $scale = 0) {
        $result = (float) $left_operand + (float) $right_operand;
        return number_format($result, (int) $scale, '.', '');
    }
}

if (!function_exists('bcsub')) {
    function bcsub($left_operand, $right_operand, $scale = 0) {
        $result = (float) $left_operand - (float) $right_operand;
        return number_format($result, (int) $scale, '.', '');
    }
}

if (!function_exists('bccomp')) {
    function bccomp($left_operand, $right_operand, $scale = 0) {
        $left  = round((float) $left_operand, (int) $scale);
        $right = round((float) $right_operand, (int) $scale);

        if ($left < $right) {
            return -1;
        }
        if ($left > $right) {
            return 1;
        }
        return 0;
    }
}

