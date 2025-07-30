<?php
/**
 * Email handler for gift certificates
 */

if (!defined('ABSPATH')) {
    exit;
}

class GiftCertificateEmail {
    
    private $database;
    private $settings;
    
    public function __construct() {
        $this->database = new GiftCertificateDatabase();
        $this->settings = get_option('gift_certificates_ff_settings', array());
        
        // Hook for scheduled deliveries
        add_action('gift_certificate_scheduled_delivery', array($this, 'send_scheduled_delivery'), 10, 1);
        
        // Hook for daily delivery check
        add_action('gift_certificate_daily_delivery_check', array($this, 'check_pending_deliveries'));
        
        // Schedule daily check if not already scheduled
        if (!wp_next_scheduled('gift_certificate_daily_delivery_check')) {
            wp_schedule_event(time(), 'daily', 'gift_certificate_daily_delivery_check');
        }
    }
    
    public function send_gift_certificate_email($gift_certificate_id) {
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        if (!$gift_certificate) {
            return false;
        }
        
        // Get the design template
        $designs = new GiftCertificateDesigns();
        $design = $designs->get_design($gift_certificate->design_id);
        
        if (!$design) {
            $design = $designs->get_default_design();
        }
        
        // Prepare email content
        $subject = $this->get_email_subject($gift_certificate);
        $message = $this->get_email_message($gift_certificate, $design);
        $headers = $this->get_email_headers($design);
        
        // Send email
        error_log("Gift Certificate Email: Attempting to send email to {$gift_certificate->recipient_email}");
        error_log("Gift Certificate Email: Subject: {$subject}");
        error_log("Gift Certificate Email: Headers: " . print_r($headers, true));
        
        $sent = wp_mail($gift_certificate->recipient_email, $subject, $message, $headers);
        
        if ($sent) {
            // Update status to delivered
            $this->database->update_gift_certificate_status($gift_certificate_id, 'delivered');
            
            // Log successful delivery
            error_log("Gift certificate email sent successfully: ID {$gift_certificate_id} to {$gift_certificate->recipient_email}");
        } else {
            // Log failed delivery
            error_log("Failed to send gift certificate email: ID {$gift_certificate_id} to {$gift_certificate->recipient_email}");
            
            // Check if FluentSMTP is available and log its status
            if (class_exists('FluentSmtp\App\Services\MailerManager')) {
                error_log("Gift Certificate Email: FluentSMTP is available");
                $mailer_manager = FluentSmtp\App\Services\MailerManager::getInstance();
                $current_mailer = $mailer_manager->getCurrentMailer();
                error_log("Gift Certificate Email: Current mailer: " . ($current_mailer ? $current_mailer->getKey() : 'None'));
            } else {
                error_log("Gift Certificate Email: FluentSMTP is not available");
            }
        }
        
        return $sent;
    }
    
    public function send_scheduled_delivery($gift_certificate_id) {
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        if (!$gift_certificate || $gift_certificate->status !== 'pending_delivery') {
            return false;
        }
        
        // Check if delivery date has arrived
        if ($gift_certificate->delivery_date > current_time('Y-m-d')) {
            // Reschedule for tomorrow
            wp_schedule_single_event(
                strtotime($gift_certificate->delivery_date . ' 09:00:00'),
                'gift_certificate_scheduled_delivery',
                array($gift_certificate_id)
            );
            return false;
        }
        
        // Send the email
        return $this->send_gift_certificate_email($gift_certificate_id);
    }
    
    public function check_pending_deliveries() {
        $pending_certificates = $this->database->get_pending_deliveries();
        
        foreach ($pending_certificates as $certificate) {
            if ($certificate->delivery_date <= current_time('Y-m-d')) {
                $this->send_gift_certificate_email($certificate->id);
            }
        }
    }
    
    private function get_email_subject($gift_certificate) {
        $subject = $this->settings['email_subject'] ?? '';
        
        if (empty($subject)) {
            $subject = sprintf(
                'Gift Certificate from %s - $%s',
                $gift_certificate->sender_name,
                number_format($gift_certificate->original_amount, 2)
            );
        }
        
        // Replace placeholders
        $subject = str_replace(
            array('{recipient_name}', '{sender_name}', '{amount}', '{site_name}'),
            array($gift_certificate->recipient_name, $gift_certificate->sender_name, '$' . number_format($gift_certificate->original_amount, 2), get_bloginfo('name')),
            $subject
        );
        
        return $subject;
    }
    
    private function get_email_message($gift_certificate, $design) {
        $template = $design['email_template'] ?? $this->get_default_email_template();
        
        // Replace placeholders
        $message = str_replace(
            array(
                '{recipient_name}',
                '{sender_name}',
                '{amount}',
                '{coupon_code}',
                '{message}',
                '{site_name}',
                '{site_url}',
                '{balance_check_url}'
            ),
            array(
                $gift_certificate->recipient_name,
                $gift_certificate->sender_name,
                number_format($gift_certificate->original_amount, 2),
                $gift_certificate->coupon_code,
                $gift_certificate->message,
                get_bloginfo('name'),
                get_site_url(),
                $this->get_balance_check_url()
            ),
            $template
        );
        
        // Convert to HTML if needed
        if ($design['email_format'] === 'html') {
            $message = $this->convert_to_html($gift_certificate->id, $message, $design); // Pass $gift_certificate_id and design
        }
        
        return $message;
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
    
    private function convert_to_html($gift_certificate_id, $message, $design) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Certificate</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
        .content { padding: 20px; }
        .gift-details { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .coupon-code { font-size: 18px; font-weight: bold; color: #007bff; }
        .message { font-style: italic; margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
        .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎁 A Gift for You</h1>
        </div>
        <div class="content">
            {design_image}
            <p>Dear <strong>{recipient_name}</strong>,</p>
            <p>You have received a gift certificate from <strong>{sender_name}</strong>!</p>
            
            <div class="gift-details">
                <h3>Gift Certificate Details:</h3>
                <p><strong>Amount:</strong> {amount}</p>
                <p><strong>Code:</strong> <span class="coupon-code">{coupon_code}</span></p>
            </div>
            
            <div class="message">
                <strong>Message from {sender_name}:</strong><br>
                {message}
            </div>
            
            <p>You can use this gift certificate on our website. The coupon code will be automatically applied during checkout.</p>
            
            <p style="text-align: center;">
                <a href="{balance_check_url}" class="button">Check Balance</a>
            </p>
        </div>
        <div class="footer">
            <p>Thank you!</p>
            <p><strong>{site_name}</strong></p>
        </div>
    </div>
</body>
</html>';
        
        // Replace placeholders in HTML template
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        // Add design image if available
        $design_image_html = '';
        if (!empty($design['image_url'])) {
            $design_image_html = '<div class="design-image" style="text-align: center; margin: 20px 0;">
                <img src="' . esc_url($design['image_url']) . '" alt="Gift Certificate Design" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            </div>';
        }
        
        return str_replace(
            array(
                '{recipient_name}',
                '{sender_name}',
                '{amount}',
                '{coupon_code}',
                '{message}',
                '{site_name}',
                '{balance_check_url}',
                '{design_image}'
            ),
            array(
                $gift_certificate->recipient_name,
                $gift_certificate->sender_name,
                number_format($gift_certificate->original_amount, 2),
                $gift_certificate->coupon_code,
                $gift_certificate->message,
                get_bloginfo('name'),
                $this->get_balance_check_url(),
                $design_image_html
            ),
            $html
        );
    }
    
    private function get_email_headers($design) {
        $headers = array();
        
        // Set content type
        if ($design['email_format'] === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        // Set from email and name with proper defaults
        $from_email = $this->settings['from_email'] ?? get_option('admin_email');
        $from_name = $this->settings['from_name'] ?? get_bloginfo('name');
        
        // Ensure we have valid values
        if (empty($from_email)) {
            $from_email = get_option('admin_email');
        }
        if (empty($from_name)) {
            $from_name = get_bloginfo('name');
        }
        
        $headers[] = "From: {$from_name} <{$from_email}>";
        $headers[] = "Reply-To: {$from_email}";
        
        return $headers;
    }
    
    private function get_balance_check_url() {
        // Create a balance check page URL or use a shortcode
        $page_id = $this->settings['balance_check_page_id'] ?? 0;
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // Fallback to home page with balance check parameter
        return add_query_arg('gift_certificate_balance', '1', home_url());
    }
    
    public function send_test_email($email_address) {
        error_log("Gift Certificate Email: Sending test email to {$email_address}");
        
        // Check email configuration
        $this->check_email_configuration();
        
        $test_certificate = (object) array(
            'recipient_name' => 'Test Recipient',
            'sender_name' => 'Test Sender',
            'original_amount' => 50.00,
            'coupon_code' => 'GCTEST123',
            'message' => 'This is a test gift certificate message.',
            'design_id' => 'default'
        );
        
        // Get the default design
        $designs = new GiftCertificateDesigns();
        $design = $designs->get_default_design();
        
        $subject = $this->get_email_subject($test_certificate);
        $message = $this->get_email_message($test_certificate, $design);
        $headers = $this->get_email_headers($design);
        
        error_log("Gift Certificate Email: Test email subject: {$subject}");
        error_log("Gift Certificate Email: Test email headers: " . print_r($headers, true));
        
        $result = wp_mail($email_address, $subject, $message, $headers);
        
        error_log("Gift Certificate Email: Test email result: " . ($result ? 'Success' : 'Failed'));
        
        return $result;
    }
    
    private function check_email_configuration() {
        error_log("Gift Certificate Email: Checking email configuration...");
        
        // Check if FluentSMTP is available
        if (class_exists('FluentSmtp\App\Services\MailerManager')) {
            error_log("Gift Certificate Email: FluentSMTP is available");
            
            try {
                $mailer_manager = FluentSmtp\App\Services\MailerManager::getInstance();
                $current_mailer = $mailer_manager->getCurrentMailer();
                
                if ($current_mailer) {
                    error_log("Gift Certificate Email: Current mailer: " . $current_mailer->getKey());
                    error_log("Gift Certificate Email: Mailer settings: " . print_r($current_mailer->getSettings(), true));
                } else {
                    error_log("Gift Certificate Email: No mailer configured in FluentSMTP");
                }
            } catch (Exception $e) {
                error_log("Gift Certificate Email: Error checking FluentSMTP: " . $e->getMessage());
            }
        } else {
            error_log("Gift Certificate Email: FluentSMTP is not available");
        }
        
        // Check WordPress mail settings
        error_log("Gift Certificate Email: WordPress admin email: " . get_option('admin_email'));
        error_log("Gift Certificate Email: WordPress blog name: " . get_bloginfo('name'));
    }
    
    public function get_email_preview($gift_certificate_id) {
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        if (!$gift_certificate) {
            return false;
        }
        
        // Get the design template
        $designs = new GiftCertificateDesigns();
        $design = $designs->get_design($gift_certificate->design_id);
        
        if (!$design) {
            $design = $designs->get_default_design();
        }
        
        return array(
            'subject' => $this->get_email_subject($gift_certificate),
            'message' => $this->get_email_message($gift_certificate, $design),
            'recipient_email' => $gift_certificate->recipient_email
        );
    }
} 