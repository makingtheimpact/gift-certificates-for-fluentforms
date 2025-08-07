<?php
/**
 * Admin interface for gift certificates
 */
namespace GiftCertificatesFluentForms;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateAdmin {
    
    private $database;
    private $settings;
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->settings = get_option('gift_certificates_ff_settings', array());
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_gift_certificate_admin_action', array($this, 'handle_admin_ajax'));
        add_action('admin_post_gcff_save_certificate', array($this, 'handle_save_certificate'));
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Gift Certificates', 'gift-certificates-fluentforms'),
            __('Gift Certificates', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff',
            array($this, 'dashboard_page'),
            'dashicons-tickets-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'gift-certificates-ff',
            __('Dashboard', 'gift-certificates-fluentforms'),
            __('Dashboard', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('All Certificates', 'gift-certificates-fluentforms'),
            __('All Certificates', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-list',
            array($this, 'certificates_list_page')
        );

        add_submenu_page(
            'gift-certificates-ff',
            __('Add New', 'gift-certificates-fluentforms'),
            __('Add New', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-add',
            array($this, 'edit_certificate_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('Settings', 'gift-certificates-fluentforms'),
            __('Settings', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'gift-certificates-ff',
            __('How to Use', 'gift-certificates-fluentforms'),
            __('How to Use', 'gift-certificates-fluentforms'),
            'manage_options',
            'gift-certificates-ff-help',
            array($this, 'help_page')
        );
    }
    
    public function init_settings() {
        register_setting('gift_certificates_ff_settings', 'gift_certificates_ff_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        add_settings_section(
            'gift_certificates_ff_general',
            __('General Settings', 'gift-certificates-fluentforms'),
            array($this, 'general_settings_section'),
            'gift_certificates_ff_settings'
        );
        
        add_settings_field(
            'gift_certificate_form_id',
            __('Gift Certificate Form ID', 'gift-certificates-fluentforms'),
            array($this, 'form_id_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );
        
        add_settings_field(
            'field_mapping',
            __('Form Field Mapping', 'gift-certificates-fluentforms'),
            array($this, 'field_mapping_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );

        add_settings_field(
            'allowed_form_ids',
            __('Allowed Redemption Forms', 'gift-certificates-fluentforms'),
            array($this, 'allowed_forms_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );

        add_settings_field(
            'order_total_field_name',
            __('Order Total Field', 'gift-certificates-fluentforms'),
            array($this, 'order_total_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );

        add_settings_field(
            'balance_check_page_id',
            __('Balance Check Page', 'gift-certificates-fluentforms'),
            array($this, 'balance_check_page_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_general'
        );
        
        add_settings_section(
            'gift_certificates_ff_email',
            __('Email Settings', 'gift-certificates-fluentforms'),
            array($this, 'email_settings_section'),
            'gift_certificates_ff_settings'
        );
        
        add_settings_field(
            'email_template',
            __('Email Template', 'gift-certificates-fluentforms'),
            array($this, 'email_template_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_email'
        );
        
        add_settings_field(
            'email_format',
            __('Email Format', 'gift-certificates-fluentforms'),
            array($this, 'email_format_field'),
            'gift_certificates_ff_settings',
            'gift_certificates_ff_email'
        );
    }
    
    public function dashboard_page() {
        $stats = $this->database->get_stats();
        $recent_certificates = $this->database->get_gift_certificates(array('limit' => 5, 'status' => ''));
        $pending_deliveries = $this->database->get_pending_deliveries();
        
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function certificates_list_page() {
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $args = array(
            'limit' => 20,
            'offset' => ($page - 1) * 20,
            'status' => $status
        );
        
        $certificates = $this->database->get_gift_certificates($args);
        $total = $this->database->get_gift_certificates_count($args);

        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/certificates-list.php';
    }

    public function edit_certificate_page() {
        $certificate_id = isset($_GET['certificate_id']) ? intval($_GET['certificate_id']) : 0;
        $certificate = null;
        $is_edit = false;

        if ($certificate_id) {
            $certificate = $this->database->get_gift_certificate($certificate_id);
            $is_edit = (bool) $certificate;
        } else {
            $certificate = new \stdClass();
            $certificate->coupon_code = $this->generate_coupon_code();
        }

        $designs_class = new GiftCertificateDesigns();
        $designs = $designs_class->get_designs();

        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/certificate-edit.php';
    }

    public function handle_save_certificate() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        check_admin_referer('gcff_save_certificate');

        $certificate_id = isset($_POST['certificate_id']) ? intval($_POST['certificate_id']) : 0;
        $is_edit = false;
        $certificate = null;

        if ($certificate_id) {
            $certificate = $this->database->get_gift_certificate($certificate_id);
            $is_edit = (bool) $certificate;
        }

        $data = array(
            'coupon_code' => sanitize_text_field($_POST['coupon_code'] ?? ''),
            'original_amount' => floatval($_POST['original_amount'] ?? 0),
            'current_balance' => floatval($_POST['current_balance'] ?? 0),
            'recipient_email' => sanitize_email($_POST['recipient_email'] ?? ''),
            'recipient_name' => sanitize_text_field($_POST['recipient_name'] ?? ''),
            'sender_name' => sanitize_text_field($_POST['sender_name'] ?? ''),
            'message' => sanitize_textarea_field($_POST['message'] ?? ''),
            'delivery_date' => sanitize_text_field($_POST['delivery_date'] ?? ''),
            'design_id' => sanitize_text_field($_POST['design_id'] ?? 'default'),
            'status' => sanitize_text_field($_POST['status'] ?? 'active')
        );

        $coupon_code = $data['coupon_code'];
        $amount = $data['current_balance'];

        if ($is_edit) {
            $old_code = $certificate->coupon_code;
            $this->database->update_gift_certificate($certificate_id, $data);

            if ($old_code !== $coupon_code || $certificate->current_balance != $amount) {
                $this->update_fluent_forms_coupon($old_code, $coupon_code, $amount);
            }
        } else {
            if (empty($coupon_code)) {
                $coupon_code = $this->generate_coupon_code();
                $data['coupon_code'] = $coupon_code;
            } else {
                if ($this->database->get_gift_certificate_by_coupon_code($coupon_code)) {
                    $coupon_code = $this->generate_coupon_code();
                    $data['coupon_code'] = $coupon_code;
                }
            }

            $certificate_id = $this->database->create_gift_certificate($data);

            if ($certificate_id) {
                $this->create_fluent_forms_coupon($coupon_code, $amount, $certificate_id);
                $email_handler = GiftCertificateEmail::get_instance();
                $email_handler->send_gift_certificate_email($certificate_id);
            }
        }

        wp_redirect(admin_url('admin.php?page=gift-certificates-ff-list'));
        exit;
    }
    
    public function settings_page() {
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function help_page() {
        include GIFT_CERTIFICATES_FF_PLUGIN_DIR . 'admin/views/help.php';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gift-certificates-ff') === false) {
            return;
        }
        
        wp_enqueue_script(
            'gift-certificate-admin',
            GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GIFT_CERTIFICATES_FF_VERSION,
            true
        );
        
        wp_enqueue_style(
            'gift-certificate-admin',
            GIFT_CERTIFICATES_FF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GIFT_CERTIFICATES_FF_VERSION
        );
        
        wp_localize_script('gift-certificate-admin', 'giftCertificateAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gift_certificate_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this gift certificate?', 'gift-certificates-fluentforms'),
                'confirmResend' => __('Are you sure you want to resend this gift certificate?', 'gift-certificates-fluentforms')
            )
        ));
        
        // Add inline script for debug functionality
        if (strpos($hook, 'gift-certificates-ff-settings') !== false) {
            wp_add_inline_script('gift-certificate-admin', '
                jQuery(document).ready(function($) {
                    $("#test-webhook").on("click", function() {
                        var button = $(this);
                        var resultDiv = $("#webhook-test-result");
                        
                        button.prop("disabled", true).text("Testing...");
                        resultDiv.html("<p>Testing webhook connection...</p>");
                        
                        $.ajax({
                            url: giftCertificateAdmin.ajaxUrl,
                            method: "POST",
                            data: {
                                action: "test_gift_certificate_webhook",
                                nonce: giftCertificateAdmin.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultDiv.html("<p style=\"color: green;\">✓ " + response.data + "</p>");
                                } else {
                                    resultDiv.html("<p style=\"color: red;\">✗ " + response.data + "</p>");
                                }
                            },
                            error: function() {
                                resultDiv.html("<p style=\"color: red;\">✗ Error testing webhook</p>");
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Test Webhook Connection");
                            }
                        });
                    });
                    
                    $("#debug-form-fields").on("click", function() {
                        var button = $(this);
                        var resultDiv = $("#form-fields-debug");
                        var formId = $("select[name=\"gift_certificates_ff_settings[gift_certificate_form_id]\"]").val();
                        
                        if (!formId) {
                            resultDiv.html("<p style=\"color: red;\">Please select a form first.</p>");
                            return;
                        }
                        
                        button.prop("disabled", true).text("Loading...");
                        resultDiv.html("<p>Loading form fields...</p>");
                        
                        $.ajax({
                            url: giftCertificateAdmin.ajaxUrl,
                            method: "POST",
                            data: {
                                action: "debug_form_fields",
                                form_id: formId,
                                nonce: giftCertificateAdmin.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultDiv.html("<pre>" + response.data + "</pre>");
                                } else {
                                    resultDiv.html("<p style=\"color: red;\">✗ " + response.data + "</p>");
                                }
                            },
                            error: function() {
                                resultDiv.html("<p style=\"color: red;\">✗ Error loading form fields</p>");
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Debug Form Fields");
                            }
                        });
                    });
                    
                    $("#test-email").on("click", function() {
                        var button = $(this);
                        var resultDiv = $("#test-email-result");
                        var emailAddress = $("#test-email-address").val();
                        
                        if (!emailAddress) {
                            resultDiv.html("<p style=\"color: red;\">Please enter an email address.</p>");
                            return;
                        }
                        
                        button.prop("disabled", true).text("Sending...");
                        resultDiv.html("<p>Sending test email...</p>");
                        
                        $.ajax({
                            url: giftCertificateAdmin.ajaxUrl,
                            method: "POST",
                            data: {
                                action: "test_gift_certificate_email",
                                email: emailAddress,
                                nonce: giftCertificateAdmin.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    resultDiv.html("<p style=\"color: green;\">✓ " + response.data + "</p>");
                                } else {
                                    resultDiv.html("<p style=\"color: red;\">✗ " + response.data + "</p>");
                                }
                            },
                            error: function() {
                                resultDiv.html("<p style=\"color: red;\">✗ Error sending test email</p>");
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Send Test Email");
                            }
                        });
                    });
                });
            ');
        }
    }

    public function handle_admin_ajax() {
        // Ensure required parameters exist
        if ( ! isset( $_POST['nonce'], $_POST['action_type'] ) ) {
            wp_send_json_error( 'Missing required parameters' );
        }

        // Verify nonce
        check_ajax_referer( 'gift_certificate_admin_nonce', 'nonce', true );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }

        $action = sanitize_text_field( $_POST['action_type'] );
        $certificate_id = intval( $_POST['certificate_id'] );
        
        switch ($action) {
            case 'delete':
                $this->delete_certificate($certificate_id);
                break;
                
            case 'resend':
                $this->resend_certificate($certificate_id);
                break;
                
            case 'update_status':
                $status = sanitize_text_field($_POST['status']);
                $this->update_certificate_status($certificate_id, $status);
                break;
                
            case 'test_gift_certificate_webhook':
                $this->test_webhook();
                break;
                
            case 'debug_form_fields':
                $form_id = intval($_POST['form_id']);
                $this->debug_form_fields($form_id);
                break;
                
            case 'test_gift_certificate_email':
                $email = sanitize_email($_POST['email']);
                $this->test_email($email);
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    private function delete_certificate($certificate_id) {
        $certificate = $this->database->get_gift_certificate($certificate_id);
        
        if (!$certificate) {
            wp_send_json_error('Certificate not found');
        }
        
        // Delete from database
        $result = $this->database->delete_gift_certificate($certificate_id);
        
        if ($result) {
            // Also delete the Fluent Forms coupon
            $this->delete_fluent_forms_coupon($certificate->coupon_code);
            
            wp_send_json_success('Certificate deleted successfully');
        } else {
            wp_send_json_error('Failed to delete certificate');
        }
    }
    
    private function resend_certificate($certificate_id) {
        $email_handler = GiftCertificateEmail::get_instance();
        $result = $email_handler->send_gift_certificate_email($certificate_id);
        
        if ($result) {
            wp_send_json_success('Certificate resent successfully');
        } else {
            wp_send_json_error('Failed to resend certificate');
        }
    }
    
    private function update_certificate_status($certificate_id, $status) {
        $allowed_statuses = array('active', 'expired', 'pending_delivery', 'delivered', 'used');

        if (!in_array($status, $allowed_statuses, true)) {
            wp_send_json_error('Invalid status');
        }

        $result = $this->database->update_gift_certificate_status($certificate_id, $status);

        if ($result) {
            wp_send_json_success('Status updated successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }
    
    private function delete_fluent_forms_coupon($coupon_code) {
        if (!class_exists('FluentFormPro\Classes\Coupon\CouponService')) {
            return false;
        }
        
        if (!method_exists($this, 'get_coupon_table_name')) {
            return false;
        }

        $coupon_table_name = $this->get_coupon_table_name();

        if (!$this->table_exists($coupon_table_name)) {
            return false;
        }

        try {
            $coupon_service = new FluentFormPro\Classes\Coupon\CouponService();

            $coupon = wpFluent()->table($coupon_table_name)
                ->where('code', $coupon_code)
                ->first();

            if ($coupon) {
                $coupon_service->delete($coupon->id);
            }

            return true;

        } catch (Exception $e) {
            gcff_log("Failed to delete Fluent Forms coupon: " . $e->getMessage());
            return false;
        }
    }
    
    // Settings field callbacks
    public function general_settings_section() {
        echo '<p>' . __('Configure the basic settings for gift certificate functionality.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function form_id_field() {
        $form_id = $this->settings['gift_certificate_form_id'] ?? '';
        
        echo '<select name="gift_certificates_ff_settings[gift_certificate_form_id]">';
        echo '<option value="">' . __('Select a form', 'gift-certificates-fluentforms') . '</option>';
        
        // More flexible Fluent Forms detection
        $fluent_forms_active = false;
        
        // Check multiple ways Fluent Forms might be available
        if (class_exists('FluentForm\Framework\Foundation\Bootstrap')) {
            $fluent_forms_active = true;
        } elseif (class_exists('FluentFormPro\Framework\Foundation\Bootstrap')) {
            $fluent_forms_active = true;
        } elseif (function_exists('wpFluent')) {
            $fluent_forms_active = true;
        } elseif (is_plugin_active('fluentform/fluentform.php')) {
            $fluent_forms_active = true;
        } elseif (is_plugin_active('fluentformpro/fluentformpro.php')) {
            $fluent_forms_active = true;
        }
        
        if ($fluent_forms_active && function_exists('wpFluent')) {
            try {
                // Get Fluent Forms
                $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();
                
                if (!empty($forms)) {
                    foreach ($forms as $form) {
                        $selected = ($form->id == $form_id) ? 'selected' : '';
                        echo "<option value='{$form->id}' {$selected}>{$form->title}</option>";
                    }
                } else {
                    echo '<option value="" disabled>' . __('No forms found', 'gift-certificates-fluentforms') . '</option>';
                }
            } catch (Exception $e) {
                echo '<option value="" disabled>' . __('Error loading forms: ' . esc_html($e->getMessage()), 'gift-certificates-fluentforms') . '</option>';
            }
        } else {
            echo '<option value="" disabled>' . __('Fluent Forms not found or not active', 'gift-certificates-fluentforms') . '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">' . __('Select the Fluent Forms form that will be used for gift certificate purchases.', 'gift-certificates-fluentforms') . '</p>';
    }

    public function allowed_forms_field() {
        $allowed_forms = $this->settings['allowed_form_ids'] ?? array();

        echo '<select name="gift_certificates_ff_settings[allowed_form_ids][]" multiple style="height: 120px; width: 100%;">';

        // More flexible Fluent Forms detection
        $fluent_forms_active = false;

        if (class_exists('FluentForm\\Framework\\Foundation\\Bootstrap')) {
            $fluent_forms_active = true;
        } elseif (class_exists('FluentFormPro\\Framework\\Foundation\\Bootstrap')) {
            $fluent_forms_active = true;
        } elseif (function_exists('wpFluent')) {
            $fluent_forms_active = true;
        } elseif (is_plugin_active('fluentform/fluentform.php')) {
            $fluent_forms_active = true;
        } elseif (is_plugin_active('fluentformpro/fluentformpro.php')) {
            $fluent_forms_active = true;
        }

        if ($fluent_forms_active && function_exists('wpFluent')) {
            try {
                $forms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->get();

                if (!empty($forms)) {
                    foreach ($forms as $form) {
                        $selected = in_array(strval($form->id), $allowed_forms, true) ? 'selected' : '';
                        echo "<option value='{$form->id}' {$selected}>{$form->title}</option>";
                    }
                } else {
                    echo '<option value="" disabled>' . __('No forms found', 'gift-certificates-fluentforms') . '</option>';
                }
            } catch (Exception $e) {
                echo '<option value="" disabled>' . __('Error loading forms: ' . esc_html($e->getMessage()), 'gift-certificates-fluentforms') . '</option>';
            }
        } else {
            echo '<option value="" disabled>' . __('Fluent Forms not found or not active', 'gift-certificates-fluentforms') . '</option>';
        }

        echo '</select>';
        echo '<p class="description">' . __('Select the forms where gift certificates can be redeemed. Leave empty to allow all forms.', 'gift-certificates-fluentforms') . '</p>';
    }

    public function order_total_field() {
        $value = $this->settings['order_total_field_name'] ?? '';
        echo "<input type='text' name='gift_certificates_ff_settings[order_total_field_name]' value='" . esc_attr($value) . "' class='regular-text'>";
        echo '<p class="description">' . __('Field names containing payment amounts in redemption forms. Separate multiple fields with commas; amounts from all matching fields will be summed and multiplied by their quantities. You can also add a hidden calculation field that stores the subtotal and list its name here to have it read first.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function field_mapping_field() {
        $field_names = array(
            'amount_field_name' => __('Amount Field', 'gift-certificates-fluentforms'),
            'recipient_email_field_name' => __('Recipient Email Field', 'gift-certificates-fluentforms'),
            'recipient_name_field_name' => __('Recipient Name Field', 'gift-certificates-fluentforms'),
            'sender_name_field_name' => __('Sender Name Field', 'gift-certificates-fluentforms'),
            'message_field_name' => __('Message Field', 'gift-certificates-fluentforms'),
            'delivery_date_field_name' => __('Delivery Date Field', 'gift-certificates-fluentforms'),
            'design_field_name' => __('Design Field', 'gift-certificates-fluentforms')
        );
        
        foreach ($field_names as $field_key => $field_label) {
            $value = $this->settings[$field_key] ?? '';
            echo "<p><label>{$field_label}:</label><br>";
            echo "<input type='text' name='gift_certificates_ff_settings[{$field_key}]' value='" . esc_attr($value) . "' class='regular-text'>";
            echo "</p>";
        }
        
        echo '<p class="description">' . __('Enter the field names from your Fluent Forms form that correspond to each gift certificate field.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_settings_section() {
        echo '<p>' . __('Configure email settings for gift certificate delivery.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_template_field() {
        $template = $this->settings['email_template'] ?? '';
        
        echo '<textarea name="gift_certificates_ff_settings[email_template]" rows="10" cols="50" class="large-text">' . esc_textarea($template) . '</textarea>';
        echo '<p class="description">' . __('Available placeholders: {recipient_name}, {sender_name}, {amount}, {coupon_code}, {message}, {site_name}, {site_url}, {balance_check_url}', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function email_format_field() {
        $format = $this->settings['email_format'] ?? 'text';
        
        echo '<select name="gift_certificates_ff_settings[email_format]">';
        echo '<option value="text" ' . selected($format, 'text', false) . '>' . __('Plain Text', 'gift-certificates-fluentforms') . '</option>';
        echo '<option value="html" ' . selected($format, 'html', false) . '>' . __('HTML', 'gift-certificates-fluentforms') . '</option>';
        echo '</select>';
    }
    
    public function balance_check_page_field() {
        $page_id = $this->settings['balance_check_page_id'] ?? '';
        
        // Get all pages
        $pages = get_pages(array(
            'sort_column' => 'post_title',
            'sort_order' => 'ASC'
        ));
        
        echo '<select name="gift_certificates_ff_settings[balance_check_page_id]">';
        echo '<option value="">' . __('Select a page...', 'gift-certificates-fluentforms') . '</option>';
        
        foreach ($pages as $page) {
            $selected = selected($page_id, $page->ID, false);
            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">' . __('Select the page where users can check their gift certificate balance. You can use the shortcode [gift_certificate_balance_check] on this page.', 'gift-certificates-fluentforms') . '</p>';
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize form ID
        $sanitized['gift_certificate_form_id'] = intval($input['gift_certificate_form_id'] ?? 0);
        
        // Sanitize field names
        $field_names = array(
            'amount_field_name', 'recipient_email_field_name', 'recipient_name_field_name',
            'sender_name_field_name', 'message_field_name', 'delivery_date_field_name', 'design_field_name',
            'order_total_field_name'
        );
        
        foreach ($field_names as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field] ?? '');
        }
        
        // Sanitize allowed form IDs for redemption
        $sanitized['allowed_form_ids'] = array();
        if (isset($input['allowed_form_ids']) && is_array($input['allowed_form_ids'])) {
            foreach ($input['allowed_form_ids'] as $form_id) {
                $form_id = intval($form_id);
                if ($form_id > 0) {
                    $sanitized['allowed_form_ids'][] = strval($form_id);
                }
            }
        }
        
        // Sanitize email settings
        $sanitized['email_template'] = wp_kses_post($input['email_template'] ?? '');
        $sanitized['email_format'] = sanitize_text_field($input['email_format'] ?? 'text');
        $sanitized['email_subject'] = sanitize_text_field($input['email_subject'] ?? '');
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? '');
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? '');
        
        // Sanitize balance check page
        $sanitized['balance_check_page_id'] = intval($input['balance_check_page_id'] ?? 0);
        
        // Sanitize coupon table name (allow only alphanumeric characters and underscores)
        $coupon_table = $input['coupon_table_name'] ?? '';
        $coupon_table = trim($coupon_table);
        if ($coupon_table !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $coupon_table)) {
            $coupon_table = '';
        }
        $sanitized['coupon_table_name'] = sanitize_key($coupon_table);
        
        return $sanitized;
    }
    
    private function test_webhook() {
        $settings = get_option('gift_certificates_ff_settings', array());
        
        if (empty($settings['gift_certificate_form_id'])) {
            wp_send_json_error('No form ID configured');
        }
        
        // More flexible Fluent Forms detection
        $fluent_forms_active = false;
        if (class_exists('FluentForm\Framework\Foundation\Bootstrap') || 
            class_exists('FluentFormPro\Framework\Foundation\Bootstrap') || 
            function_exists('wpFluent') ||
            is_plugin_active('fluentform/fluentform.php') ||
            is_plugin_active('fluentformpro/fluentformpro.php')) {
            $fluent_forms_active = true;
        }
        
        if (!$fluent_forms_active || !function_exists('wpFluent')) {
            wp_send_json_error('Fluent Forms not active');
        }
        
        // Check if the form exists
        $form = wpFluent()->table('fluentform_forms')->where('id', $settings['gift_certificate_form_id'])->first();
        if (!$form) {
            wp_send_json_error('Configured form not found');
        }
        
        // Check if webhook hook is properly registered
        if (!has_action('fluentform_submission_inserted')) {
            wp_send_json_error('Webhook hook not registered');
        }
        
        wp_send_json_success('Webhook connection is working properly. Form: ' . $form->title);
    }
    
    private function debug_form_fields($form_id) {
        // More flexible Fluent Forms detection
        $fluent_forms_active = false;
        if (class_exists('FluentForm\Framework\Foundation\Bootstrap') || 
            class_exists('FluentFormPro\Framework\Foundation\Bootstrap') || 
            function_exists('wpFluent') ||
            is_plugin_active('fluentform/fluentform.php') ||
            is_plugin_active('fluentformpro/fluentformpro.php')) {
            $fluent_forms_active = true;
        }
        
        if (!$fluent_forms_active || !function_exists('wpFluent')) {
            wp_send_json_error('Fluent Forms not active');
        }
        
        $form = wpFluent()->table('fluentform_forms')->where('id', $form_id)->first();
        if (!$form) {
            wp_send_json_error('Form not found');
        }
        
        $form_fields = json_decode($form->form_fields, true);
        if (!$form_fields) {
            wp_send_json_error('No form fields found');
        }
        
        $output = "Form: {$form->title}\n\n";
        $output .= "Available fields:\n";
        
        foreach ($form_fields as $field) {
            if (isset($field['attributes']['name'])) {
                $output .= "- {$field['attributes']['name']} ({$field['element']})\n";
            }
        }
        
        wp_send_json_success($output);
    }
    
    private function test_email($email_address) {
        $email_handler = GiftCertificateEmail::get_instance();
        $result = $email_handler->send_test_email($email_address);

        if ($result) {
            wp_send_json_success('Test email sent successfully! Check your email inbox.');
        } else {
            wp_send_json_error('Failed to send test email. Check the error logs for details.');
        }
    }

    private function update_fluent_forms_coupon($old_code, $new_code, $amount) {
        $coupon_table_name = $this->get_coupon_table_name();

        if (!$this->table_exists($coupon_table_name)) {
            return false;
        }

        try {
            $coupon = wpFluent()->table($coupon_table_name)
                ->where('code', $old_code)
                ->first();

            if ($coupon) {
                $settings = unserialize($coupon->settings);
                if (is_array($settings)) {
                    $settings['success_message'] = '{coupon.code} - {coupon.amount}';
                }

                $update_data = array(
                    'amount' => $amount,
                    'settings' => serialize($settings),
                    'updated_at' => current_time('mysql')
                );

                if ($old_code !== $new_code) {
                    $update_data['code'] = $new_code;
                    $update_data['title'] = 'Gift Certificate - ' . $new_code;
                }

                wpFluent()->table($coupon_table_name)
                    ->where('id', $coupon->id)
                    ->update($update_data);
                return true;
            } else {
                return $this->create_fluent_forms_coupon($new_code, $amount, 0);
            }
        } catch (Exception $e) {
            gcff_log('Failed to update Fluent Forms coupon: ' . $e->getMessage());
            return false;
        }
    }

    private function generate_coupon_code() {
        $prefix = 'GC';
        $length = 8;
        $max_attempts = 10;

        for ($i = 0; $i < $max_attempts; $i++) {
            $code = $prefix . strtoupper(substr(md5(uniqid()), 0, $length));
            $existing = $this->database->get_gift_certificate_by_coupon_code($code);
            if (!$existing) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5(uniqid()), 0, $length));
    }

    private function create_fluent_forms_coupon($coupon_code, $amount, $gift_certificate_id) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active('fluentformpro/fluentformpro.php') && !is_plugin_active('fluentformpro-addon-pack/fluentformpro-addon-pack.php')) {
            return false;
        }

        $coupon_table_name = $this->get_coupon_table_name();

        if (!$this->table_exists($coupon_table_name)) {
            return false;
        }

        try {
            $settings = array(
                'allowed_form_ids' => $this->settings['allowed_form_ids'] ?? array(),
                'coupon_limit' => '0',
                'success_message' => '{coupon.code} - {coupon.amount}',
                'failed_message' => array(
                    'inactive' => 'The provided coupon is not valid',
                    'min_amount' => 'The provided coupon does not meet the requirements',
                    'stackable' => 'Sorry, you can not apply this coupon with other coupon code',
                    'date_expire' => 'The provided coupon is not valid',
                    'allowed_form' => 'The provided coupon is not valid',
                    'limit' => 'The provided coupon is not valid'
                )
            );

            $coupon_data = array(
                'title' => "Gift Certificate - {$coupon_code}",
                'code' => $coupon_code,
                'coupon_type' => 'fixed',
                'amount' => $amount,
                'status' => 'active',
                'stackable' => 'no',
                'settings' => serialize($settings),
                'created_by' => get_current_user_id() ?: 1,
                'min_amount' => 0,
                'max_use' => 0,
                'start_date' => current_time('Y-m-d'),
                'expire_date' => date('Y-m-d', strtotime('+1 year')),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );

            wpFluent()->table($coupon_table_name)->insert($coupon_data);
            return true;
        } catch (Exception $e) {
            gcff_log('Failed to create Fluent Forms coupon: ' . $e->getMessage());
            return false;
        }
    }

    private function get_coupon_table_name() {
        $settings = get_option('gift_certificates_ff_settings', array());
        $custom_table_name = $settings['coupon_table_name'] ?? '';

        if (!empty($custom_table_name)) {
            return str_replace('wp_', '', sanitize_key($custom_table_name));
        }

        return 'fluentform_coupons';
    }

    private function table_exists($table_name) {
        global $wpdb;
        try {
            $full_table_name = $wpdb->prefix . $table_name;
            return $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
        } catch (Exception $e) {
            gcff_log('Gift Certificate: Error checking table ' . $table_name . ': ' . $e->getMessage());
            return false;
        }
    }
}
