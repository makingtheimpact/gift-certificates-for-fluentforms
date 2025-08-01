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
        // Get the appropriate template based on email format
        if ($design['email_format'] === 'html') {
            $template = $design['email_template'] ?? $this->get_default_email_template();
        } else {
            // For plain text, use a simpler template or convert HTML to plain text
            $template = $design['email_template'] ?? $this->get_default_plain_text_template();
            
            // If the template contains HTML but format is plain text, convert it
            if (strpos($template, '<') !== false && strpos($template, '>') !== false) {
                $template = $this->convert_html_to_plain_text($template);
            }
        }
        
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
            $message = $this->convert_to_html($gift_certificate->id, $message, $design);
        } else {
            // Format plain text message
            $message = $this->format_email_message($message, 'plain');
        }
        
        return $message;
    }
    
    private function get_default_email_template() {
        return "Dear {recipient_name},\n\n" .
               "You have received a gift certificate from {sender_name}!\n\n" .
               "Gift Certificate Details:\n" .
               "Amount: \${amount}\n" .
               "Code: {coupon_code}\n\n" .
               "Message from {sender_name}:\n{message}\n\n" .
               "You can use this gift certificate on {site_name} at {site_url}. To redeem, enter the code in the coupon code field at checkout.\n\n" .
               "You can check your balance at any time at {balance_check_url}.\n\n" .
               "Thank you!\n\n" .
               "[end]";
    }
    
    private function get_default_plain_text_template() {
        return "Dear {recipient_name},\n\n" .
               "You have received a beautiful gift certificate from {sender_name}!\n\n" .
               "Gift Certificate Details:\n" .
               "Amount: \${amount}\n" .
               "Code: {coupon_code}\n\n" .
               "Message from {sender_name}:\n{message}\n\n" .
               "You can use this gift certificate on our website. Simply enter the coupon code during checkout to apply your discount.\n\n" .
               "You can check your balance at any time at: {balance_check_url}\n\n" .
               "Thank you for choosing {site_name}!\n\n" .
               "{site_name}\n" .
               "{site_url}";
    }
    
    private function convert_html_to_plain_text($html) {
        // Remove HTML tags and convert common HTML entities
        $text = strip_tags($html);
        $text = str_replace(
            array('&nbsp;', '&amp;', '&lt;', '&gt;', '&quot;', '&#039;'),
            array(' ', '&', '<', '>', '"', "'"),
            $text
        );
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    private function format_email_message($message, $format = 'html') {
        if ($format === 'plain') {
            // Ensure proper line breaks for plain text emails
            $message = str_replace(array('<br>', '<br/>', '<br />'), "\n", $message);
            $message = str_replace(array('</p>', '</div>'), "\n\n", $message);
            $message = preg_replace('/<p[^>]*>/', '', $message);
            $message = preg_replace('/<div[^>]*>/', '', $message);
            
            // Clean up extra whitespace
            $message = preg_replace('/\n\s*\n/', "\n\n", $message);
            $message = trim($message);
        }
        
        return $message;
    }
    
    private function convert_to_html($gift_certificate_id, $message, $design) {
        // Get the gift certificate data
        $gift_certificate = $this->database->get_gift_certificate($gift_certificate_id);
        
        // Get custom CSS from design, or use default
        $custom_css = !empty($design['custom_css']) ? $design['custom_css'] : $this->get_default_css();
        
        // Build the HTML email wrapper
        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gift Certificate</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
' . $custom_css . '
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td align="center" style="background-color: #f4f4f4; padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container">
                    <tr>
                        <td class="header">
                            <h1>🎁 Gift Certificate</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="content">
                            {design_image}
                            ' . $message . '
                        </td>
                    </tr>
                    <tr>
                        <td class="footer">
                            <p>Thank you for choosing {site_name}!</p>
                            <p><strong>{site_name}</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        // Add design image if available
        $design_image_html = '';
        if (!empty($design['image_url'])) {
            $design_image_html = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="text-align: center; padding: 20px 0;">
                        <img src="' . esc_url($design['image_url']) . '" alt="Gift Certificate Design" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    </td>
                </tr>
            </table>';
        }
        
        // Replace remaining placeholders
        return str_replace(
            array(
                '{site_name}',
                '{design_image}'
            ),
            array(
                get_bloginfo('name'),
                $design_image_html
            ),
            $html
        );
    }
    
    private function get_email_headers($design) {
        $headers = array();
        
        // Set content type based on email format
        if ($design['email_format'] === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'MIME-Version: 1.0';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        // Set from email and name with proper defaults
        $from_email = $this->settings['from_email'] ?? get_option('admin_email');
        $from_name = $this->settings['from_name'] ?? get_bloginfo('name');
        
        // Ensure we have valid values
        if (empty($from_email) || !is_email($from_email)) {
            $from_email = get_option('admin_email');
        }
        if (empty($from_name)) {
            $from_name = get_bloginfo('name');
        }
        
        // Sanitize the from name to prevent header injection
        $from_name = sanitize_text_field($from_name);
        
        $headers[] = "From: {$from_name} <{$from_email}>";
        $headers[] = "Reply-To: {$from_email}";
        $headers[] = "X-Mailer: WordPress/" . get_bloginfo('version');
        
        // Add priority header for gift certificates
        $headers[] = "X-Priority: 1";
        $headers[] = "X-MSMail-Priority: High";
        
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
    
    private function get_default_css() {
        return "/* Reset styles */
body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }

/* Base styles */
body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #333333; background-color: #f4f4f4; }

/* Container */
.email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }

/* Header */
.header { background-color: #6c757d; color: #ffffff; padding: 30px 20px; text-align: center; }
.header h1 { margin: 0; font-size: 28px; font-weight: bold; }

/* Content */
.content { padding: 30px 20px; }
.content p { margin: 0 0 15px 0; }

/* Gift details */
.gift-details { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #6c757d; }
.gift-details h3 { margin: 0 0 15px 0; color: #333333; }

/* Amount */
.amount { font-size: 32px; font-weight: bold; color: #6c757d; text-align: center; margin: 15px 0; }

/* Coupon code */
.coupon-code { font-size: 24px; font-weight: bold; color: #6c757d; text-align: center; padding: 15px; background-color: #e9ecef; margin: 15px 0; }

/* Message */
.message { font-style: italic; margin: 20px 0; padding: 20px; background-color: #fff3e0; border-left: 4px solid #ff9800; }

/* Button */
.button { display: inline-block; padding: 12px 24px; background-color: #6c757d; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }

/* Footer */
.footer { text-align: center; margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }

/* Responsive */
@media only screen and (max-width: 600px) {
    .email-container { width: 100% !important; }
    .content { padding: 20px 15px !important; }
    .header { padding: 20px 15px !important; }
    .header h1 { font-size: 24px !important; }
    .amount { font-size: 28px !important; }
    .coupon-code { font-size: 20px !important; }
}";
    }
    
    public function send_test_email($email_address, $design_id = 'default') {
        error_log("Gift Certificate Email: Sending test email to {$email_address} using design {$design_id}");

        // Check email configuration
        $this->check_email_configuration();

        $test_certificate = (object) array(
            'id' => 0,
            'recipient_name' => 'Test Recipient',
            'sender_name' => 'Test Sender',
            'original_amount' => 50.00,
            'coupon_code' => 'GCTEST123',
            'message' => 'This is a test gift certificate message.',
            'design_id' => $design_id
        );

        // Get the requested design, fallback to default if not found
        $designs = new GiftCertificateDesigns();
        $design = $designs->get_design($design_id);
        if (!$design) {
            $design = $designs->get_default_design();
        }
        
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