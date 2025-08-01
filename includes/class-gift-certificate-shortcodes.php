<?php
/**
 * Shortcode handler for gift certificates
 */
namespace GiftCertificatesFluentForms;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateShortcodes {
    
    public function __construct() {
        add_shortcode('gift_certificate_balance_check', array($this, 'balance_check_shortcode'));
        add_shortcode('gift_certificate_purchase_form', array($this, 'purchase_form_shortcode'));
        
        // Enqueue scripts when shortcodes are used
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Balance check shortcode
     * Usage: [gift_certificate_balance_check]
     */
    public function balance_check_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Check Gift Certificate Balance',
            'placeholder' => 'Enter your gift certificate code',
            'button_text' => 'Check Balance',
            'show_instructions' => 'true'
        ), $atts);
        
        // Enqueue required scripts
        wp_enqueue_script('gift-certificate-balance-check');
        
        $output = '<div class="gift-certificate-balance-shortcode">';
        
        if ($atts['show_instructions'] === 'true') {
            $output .= '<div class="balance-instructions">';
            $output .= '<p>Enter your gift certificate code to check the current balance. Gift certificate codes start with "GC" followed by 8 characters.</p>';
            $output .= '</div>';
        }
        
        $output .= '<div class="gift-certificate-balance-form">';
        $output .= '<h3>' . esc_html($atts['title']) . '</h3>';
        $output .= '<div class="balance-form-fields">';
        $output .= '<input type="text" class="gift-certificate-code" placeholder="' . esc_attr($atts['placeholder']) . '" maxlength="10">';
        $output .= '<button type="button" class="balance-check-button">' . esc_html($atts['button_text']) . '</button>';
        $output .= '</div>';
        $output .= '<div class="balance-result"></div>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Purchase form shortcode
     * Usage: [gift_certificate_purchase_form form_id="123"]
     */
    public function purchase_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'form_id' => '',
            'title' => 'Purchase Gift Certificate'
        ), $atts);
        
        if (empty($atts['form_id'])) {
            // Get form ID from settings
            $settings = get_option('gift_certificates_ff_settings', array());
            $atts['form_id'] = $settings['gift_certificate_form_id'] ?? '';
        }
        
        if (empty($atts['form_id'])) {
            return '<p class="error">Gift certificate form not configured. Please contact the administrator.</p>';
        }
        
        $output = '<div class="gift-certificate-purchase-form">';
        
        if (!empty($atts['title'])) {
            $output .= '<h2>' . esc_html($atts['title']) . '</h2>';
        }
        
        // Check if Fluent Forms is active
        if (!class_exists('FluentForm\Framework\Foundation\Bootstrap')) {
            $output .= '<p class="error">Fluent Forms is required for gift certificate purchases.</p>';
        } else {
            // Use Fluent Forms shortcode
            $output .= do_shortcode('[fluentform id="' . intval($atts['form_id']) . '"]');
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    public function enqueue_scripts() {
        // Check if shortcodes are present on the page
        global $post;
        
        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'gift_certificate_balance_check') ||
            has_shortcode($post->post_content, 'gift_certificate_purchase_form')
        )) {
            wp_enqueue_script(
                'gift-certificate-balance-check',
                GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/js/balance-check.js',
                array('jquery'),
                GIFT_CERTIFICATES_FF_VERSION,
                true
            );
            
            wp_enqueue_style(
                'gift-certificate-balance-check',
                GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/css/balance-check.css',
                array(),
                GIFT_CERTIFICATES_FF_VERSION
            );
            
            wp_localize_script('gift-certificate-balance-check', 'giftCertificateAPI', array(
                'restUrl' => rest_url('gift-certificates/v1'),
                'nonce' => wp_create_nonce('wp_rest')
            ));
        }
    }
} 