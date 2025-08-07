<?php
/**
 * REST API for gift certificate balance checking and management
 */
namespace GiftCertificatesFluentForms;

use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateAPI {
    
    private $database;
    private $coupon_handler;
    private $namespace = 'gift-certificates/v1';
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->coupon_handler = new GiftCertificateCoupon();
        
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function register_routes() {
        // Balance check endpoint
        register_rest_route($this->namespace, '/balance/(?P<code>[a-zA-Z0-9]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_balance'),
                'permission_callback' => array($this, 'verify_balance_request'),
                'args' => array(
                    'code' => array(
                        'validate_callback' => function($param) {
                            return preg_match('/^GC[A-Z0-9]{8}$/', $param);
                        }
                    )
                )
            )
        ));
        
        // Balance check endpoint (POST for better security)
        register_rest_route($this->namespace, '/balance', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'check_balance'),
                'permission_callback' => array($this, 'verify_balance_request'),
                'args' => array(
                    'code' => array(
                        'required' => true,
                        'validate_callback' => function($param) {
                            return preg_match('/^GC[A-Z0-9]{8}$/', $param);
                        }
                    )
                )
            )
        ));
        
        // Admin endpoints (require authentication)
        register_rest_route($this->namespace, '/admin/certificates', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_certificates'),
                'permission_callback' => array($this, 'check_admin_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/certificates/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_certificate'),
                'permission_callback' => array($this, 'check_admin_permission')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_certificate'),
                'permission_callback' => array($this, 'check_admin_permission')
            )
        ));
        
        register_rest_route($this->namespace, '/admin/stats', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_stats'),
                'permission_callback' => array($this, 'check_admin_permission')
            )
        ));
    }
    
    public function get_balance($request) {
        $code = $request->get_param('code');
        
        $balance_info = $this->coupon_handler->get_gift_certificate_balance($code);

        if (!$balance_info) {
            return new WP_Error(
                'gift_certificate_not_found',
                'Gift certificate not found or inactive',
                array('status' => 404)
            );
        }

        $public_info = array(
            'balance' => $balance_info['balance'],
            'original_amount' => $balance_info['original_amount'],
            'status'  => $balance_info['status']
        );

        return new WP_REST_Response($public_info, 200);
    }
    
    public function check_balance($request) {
        $code = $request->get_param('code');
        
        $balance_info = $this->coupon_handler->get_gift_certificate_balance($code);

        if (!$balance_info) {
            return new WP_Error(
                'gift_certificate_not_found',
                'Gift certificate not found or inactive',
                array('status' => 404)
            );
        }

        $public_info = array(
            'balance' => $balance_info['balance'],
            'original_amount' => $balance_info['original_amount'],
            'status'  => $balance_info['status']
        );

        return new WP_REST_Response($public_info, 200);
    }
    
    public function get_certificates($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $status = $request->get_param('status');
        $search = $request->get_param('search');
        
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        if ($status) {
            $args['status'] = $status;
        }

        if ($search) {
            $args['search'] = $search;
        }
        
        $certificates = $this->database->get_gift_certificates($args);
        $total = $this->database->get_gift_certificates_count($args);
        
        return new WP_REST_Response(array(
            'certificates' => $certificates,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ), 200);
    }
    
    public function get_certificate($request) {
        $id = $request->get_param('id');
        
        $certificate = $this->database->get_gift_certificate($id);
        
        if (!$certificate) {
            return new WP_Error(
                'gift_certificate_not_found',
                'Gift certificate not found',
                array('status' => 404)
            );
        }
        
        // Get transactions
        $transactions = $this->database->get_transactions($id);
        
        $certificate->transactions = $transactions;
        
        return new WP_REST_Response($certificate, 200);
    }
    
    public function update_certificate($request) {
        $id = $request->get_param('id');
        $params = $request->get_params();
        
        $certificate = $this->database->get_gift_certificate($id);
        
        if (!$certificate) {
            return new WP_Error(
                'gift_certificate_not_found',
                'Gift certificate not found',
                array('status' => 404)
            );
        }
        
        // Allow updating specific fields
        $allowed_fields = array('status', 'current_balance', 'message');
        $update_data = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error(
                'no_valid_fields',
                'No valid fields to update',
                array('status' => 400)
            );
        }
        
        // Update certificate
        $result = $this->database->update_gift_certificate($id, $update_data);
        
        if (!$result) {
            return new WP_Error(
                'update_failed',
                'Failed to update gift certificate',
                array('status' => 500)
            );
        }
        
        $updated_certificate = $this->database->get_gift_certificate($id);
        
        return new WP_REST_Response($updated_certificate, 200);
    }
    
    public function get_stats($request) {
        $stats = $this->database->get_stats();
        
        return new WP_REST_Response($stats, 200);
    }

    /**
     * Verify nonce for balance checks to prevent abuse
     *
     * @param WP_REST_Request $request REST request object.
     * @return true|WP_Error
     */
    public function verify_balance_request($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid or missing nonce.', 'gift-certificates-fluentforms'),
                array('status' => 403)
            );
        }

        $rate_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        return true;
    }

    /**
     * Basic rate limiting to prevent abuse of balance checks.
     *
     * @param WP_REST_Request $request REST request object.
     * @return true|WP_Error
     */
    private function check_rate_limit($request) {
        $ip = sanitize_text_field( wp_get_user_ip() );
        if ( empty( $ip ) ) {
            $ip = 'unknown';
        }
        $key = 'gcff_balance_' . md5( $ip );
        $requests = (int) get_transient( $key );

        if ($requests >= 5) {
            return new WP_Error(
                'rest_rate_limited',
                __('Too many balance requests. Please try again later.', 'gift-certificates-fluentforms'),
                array('status' => 429)
            );
        }

        set_transient($key, $requests + 1, MINUTE_IN_SECONDS);
        return true;
    }
    
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
    
    public function enqueue_scripts() {
        // Only enqueue scripts if shortcodes are present on the page
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
                'restUrl' => rest_url($this->namespace),
                'nonce' => wp_create_nonce('wp_rest')
            ));
        }
    }
    
    // AJAX handlers for backward compatibility
    public function handle_ajax_balance_check() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !check_ajax_referer('gift_certificate_balance_check', 'nonce', false)) {
            wp_send_json_error('Security check failed', 403);
        }
        
        $code = sanitize_text_field($_POST['code']);
        
        if (!$this->coupon_handler->validate_coupon_for_balance_check($code)) {
            wp_send_json_error('Invalid gift certificate code');
        }
        
        $balance_info = $this->coupon_handler->get_gift_certificate_balance($code);

        if (!$balance_info) {
            wp_send_json_error('Gift certificate not found or inactive');
        }

        $public_info = array(
            'balance' => $balance_info['balance'],
            'status'  => $balance_info['status']
        );

        wp_send_json_success($public_info);
    }
}