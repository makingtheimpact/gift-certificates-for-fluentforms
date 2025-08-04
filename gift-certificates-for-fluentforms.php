<?php
/**
 * Plugin Name: Gift Certificates for Fluent Forms
 * Plugin URI: https://github.com/makingtheimpact/gift-certificates-for-fluentforms
 * Description: Extend Fluent Forms Pro to sell and redeem gift certificates with webhook integration, coupon management, and balance tracking.
 * Version: 1.1.0
 * Author: Making The Impact LLC
 * License: GPL v2 or later
 * Text Domain: gift-certificates-fluentforms
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GIFT_CERTIFICATES_FF_VERSION', '1.1.0');
define('GIFT_CERTIFICATES_FF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIFT_CERTIFICATES_FF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIFT_CERTIFICATES_FF_PLUGIN_BASENAME', plugin_basename(__FILE__));

use GiftCertificatesFluentForms\GiftCertificateDatabase;
use GiftCertificatesFluentForms\GiftCertificateAdmin;
use GiftCertificatesFluentForms\GiftCertificateWebhook;
use GiftCertificatesFluentForms\GiftCertificateCoupon;
use GiftCertificatesFluentForms\GiftCertificateAPI;
use GiftCertificatesFluentForms\GiftCertificateEmail;
use GiftCertificatesFluentForms\GiftCertificateShortcodes;
use GiftCertificatesFluentForms\GiftCertificateDesigns;

// Main plugin class
class GiftCertificatesForFluentForms {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'gift-certificates-fluentforms',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    public function init() {
        // Check if Fluent Forms Pro is active
        if (!$this->is_fluent_forms_active()) {
            add_action('admin_notices', array($this, 'fluent_forms_missing_notice'));
            return;
        }
        
        // Load plugin components
        $this->load_dependencies();
        $this->init_components();
    }
    
    private function is_fluent_forms_active() {
        // Include WordPress plugin functions
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        // Check for Fluent Forms Pro class
        if (class_exists('FluentForm\Framework\Foundation\Bootstrap')) {
            return true;
        }
        
        // Check for Fluent Forms Pro Add On Pack
        if (class_exists('FluentFormPro\Framework\Foundation\Bootstrap')) {
            return true;
        }
        
        // Check if Fluent Forms Pro plugin is active
        if (is_plugin_active('fluentformpro/fluentformpro.php')) {
            return true;
        }
        
        // Check if Fluent Forms Pro Add On Pack is active
        if (is_plugin_active('fluentformpro-addon-pack/fluentformpro-addon-pack.php')) {
            return true;
        }
        
        // Check for any plugin with "fluentform" in the name
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'fluentform') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function fluent_forms_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Gift Certificates for Fluent Forms requires Fluent Forms Pro to be installed and activated.', 'gift-certificates-fluentforms');
        echo '</p></div>';
    }
    
    public function load_dependencies() {
        // Load required files
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/gcff-functions.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-database.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-admin.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-webhook.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-coupon.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-api.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-email.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-shortcodes.php';
        require_once GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'includes/class-gift-certificate-designs.php';
    }
    
    private function init_components() {
        // Migrate any existing integer form IDs to strings
        $this->migrate_form_ids_to_strings();
        
        // Initialize components
        new GiftCertificateDatabase();
        new GiftCertificateAdmin();
        new GiftCertificateWebhook();
        new GiftCertificateCoupon();
        new GiftCertificateAPI();
        GiftCertificateEmail::get_instance();
        new GiftCertificateShortcodes();
        new GiftCertificateDesigns();
    }
    
    /**
     * Migrate any existing integer form IDs to strings for compatibility with Fluent Forms
     */
    private function migrate_form_ids_to_strings() {
        $settings = get_option('gift_certificates_ff_settings', array());
        
        if (isset($settings['allowed_form_ids']) && is_array($settings['allowed_form_ids'])) {
            $migrated = false;
            $new_allowed_form_ids = array();
            
            foreach ($settings['allowed_form_ids'] as $form_id) {
                // If the form ID is an integer, convert it to string
                if (is_int($form_id)) {
                    $new_allowed_form_ids[] = strval($form_id);
                    $migrated = true;
                } else {
                    $new_allowed_form_ids[] = $form_id;
                }
            }
            
            // Update settings if migration was needed
            if ($migrated) {
                $settings['allowed_form_ids'] = $new_allowed_form_ids;
                update_option('gift_certificates_ff_settings', $settings);
                gcff_log('Gift Certificates: Migrated form IDs from integers to strings');
            }
        }
    }
    
    public function activate() {
        // Load dependencies for activation
        $this->load_dependencies();
        
        // Check if database class exists
        if (!class_exists(GiftCertificateDatabase::class)) {
            wp_die('Gift Certificate Database class not found. Please check plugin installation.');
        }
        
        // Create database tables
        $database = new GiftCertificateDatabase();
        $database->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules for API endpoints
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled hooks to prevent orphaned events
        wp_clear_scheduled_hook('gift_certificate_daily_delivery_check');
        wp_clear_scheduled_hook('gift_certificate_scheduled_delivery');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function set_default_options() {
        $default_options = array(
            'gift_certificate_form_id' => '',
            'amount_field_name' => 'gift_certificate_amount',
            'recipient_email_field_name' => 'recipient_email',
            'recipient_name_field_name' => 'recipient_name',
            'sender_name_field_name' => 'sender_name',
            'message_field_name' => 'message',
            'delivery_date_field_name' => 'delivery_date',
            'design_field_name' => 'gift_certificate_design',
            'allowed_form_ids' => array(), // Empty array means all forms are allowed
            'email_template' => $this->get_default_email_template(),
            'api_enabled' => true,
            'balance_check_enabled' => true,
            'enable_logging' => (defined('WP_DEBUG') && WP_DEBUG)
        );
        
        add_option('gift_certificates_ff_settings', $default_options);
    }

    private function get_default_email_template() {
        return "Dear {recipient_name},\n\n" .
               "You have received a gift certificate from {sender_name}!\n\n" .
               "Gift Certificate Details:\n" .
               "Amount: {amount}\n" .
               "Code: {coupon_code}\n\n" .
               "Message from {sender_name}:\n{message}\n\n" .
               "You can use this gift certificate on {site_name} at {site_url}. To redeem, enter the code in the coupon code field at checkout.\n\n" .
               "You can check your balance at any time at {balance_check_url}.\n\n" .
               "Thank you!\n\n" .
               "[end]";
    }
}

// Initialize the plugin
function gift_certificates_ff_init() {
    return GiftCertificatesForFluentForms::get_instance();
}

// Start the plugin
gift_certificates_ff_init(); 