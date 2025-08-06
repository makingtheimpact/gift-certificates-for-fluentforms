# Gift Certificates for Fluent Forms

A comprehensive WordPress plugin that extends Fluent Forms Pro to sell and redeem gift certificates with webhook integration, coupon management, and balance tracking.

## Features

- **Secure & Efficient**: Built with WordPress security best practices and optimized for performance
- **Plugin Conflict Free**: Uses proper WordPress hooks and follows coding standards to avoid conflicts
- **Hosting Platform Compatible**: Works with all major WordPress hosting platforms
- **Webhook Integration**: Automatically processes Fluent Forms submissions to create gift certificates
- **Coupon Management**: Integrates with Fluent Forms Pro coupon system for seamless redemption
- **Auto-Generated Codes**: New certificates are pre-filled with a properly formatted coupon code
- **Balance Tracking**: Real-time balance updates and transaction history
- **Scheduled Delivery**: Send gift certificates immediately or on a specific date
- **REST API**: Built-in API endpoints for balance checking and management
- **Nonce-Protected**: Balance check requests require a valid WordPress nonce
- **Admin Interface**: Complete WordPress admin interface for managing gift certificates
- **Email Templates**: Customizable email templates with HTML support
- **Design Templates**: Multiple gift certificate design templates with custom images
- **Shortcodes**: Easy-to-use shortcodes for balance checking and purchase forms

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Fluent Forms Pro (for coupon functionality)
- Fluent Forms (free version for form creation)

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/gift-certificates-for-fluentforms/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to 'Gift Certificates → Settings' to configure the plugin

## Uninstallation

When you delete the plugin through the WordPress admin, it will clean up its data:

- The `gift_certificates_ff_settings` option is removed.
- Custom database tables (`gift_certificates` and `gift_certificate_transactions`) are dropped.

## Quick Setup

### 1. Create a Fluent Forms Form

Create a form with these fields:
- **Amount Field**: Number input for gift certificate amount
- **Recipient Email**: Email field for the recipient
- **Recipient Name**: Text field for recipient name
- **Sender Name**: Text field for sender name
- **Message**: Textarea for personal message (optional)
- **Delivery Date**: Date field for scheduled delivery (optional)
- **Design Selection**: Radio or select field for gift certificate design (optional)

#### Design Selection Field Setup

For the design selection field:
1. Use a **Radio** or **Select** field type
2. Set option values to match design IDs exactly (e.g., "default", "design_123")
3. Set option labels to user-friendly names (e.g., "Classic Design", "Holiday Theme")
4. Configure the field name in plugin settings

**Example:**
- Option Label: "Classic Gift Certificate" | Value: "default"
- Option Label: "Holiday Special" | Value: "holiday_theme"
- Option Label: "Birthday Celebration" | Value: "birthday_design"

### 2. Configure Plugin Settings

1. Go to **Gift Certificates → Settings**
2. Select your gift certificate form
3. Choose which forms can redeem gift certificate coupons (leave empty to allow all)
4. Map the form fields to gift certificate fields
5. Configure email settings
6. Save settings

### 3. Add Payment Integration

Add your preferred payment gateway (Stripe, PayPal, etc.) to the Fluent Forms form to handle purchases.

## How It Works

### Purchase Process
1. Customer fills out the gift certificate form
2. Payment is processed through Fluent Forms
3. Form submission triggers webhook
4. Plugin creates gift certificate record and coupon code
5. Email is sent to recipient (immediate or scheduled)

### Redemption Process
1. Recipient receives email with coupon code
2. Recipient uses coupon code in any Fluent Forms form
3. Plugin validates coupon and checks balance
4. Discount is applied and balance is updated
5. Coupon is deactivated when balance reaches zero

## Usage

### Design Template Mapping

For detailed instructions on setting up design template mapping with the webhook system, see [Webhook Design Mapping Guide](docs/WEBHOOK_DESIGN_MAPPING.md).

### Shortcodes

#### Balance Check
```php
[gift_certificate_balance_check]
```

Options:
- `title`: Custom title for the form
- `placeholder`: Custom placeholder text
- `button_text`: Custom button text
- `show_instructions`: Show/hide instructions (true/false)

#### Purchase Form
```php
[gift_certificate_purchase_form form_id="123"]
```

Options:
- `form_id`: Fluent Forms form ID (optional, uses settings if not provided)
- `title`: Custom title for the form

### API Endpoints

#### Check Balance
Requires a valid nonce sent in the `X-WP-Nonce` header.
```http
POST /wp-json/gift-certificates/v1/balance
Content-Type: application/json
X-WP-Nonce: {nonce}

{
    "code": "GC12345678"
}
```

#### Get Balance (GET)
```http
GET /wp-json/gift-certificates/v1/balance/GC12345678
```

> **Data Exposed:** Balance endpoints return only the gift certificate's current balance and status. Recipient details are omitted to protect privacy.

### JavaScript Integration

```javascript
// Check balance using JavaScript
fetch('/wp-json/gift-certificates/v1/balance', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({ code: 'GC12345678' })
})
.then(response => response.json())
.then(data => {
    console.log('Balance:', data.balance);
});
```

## Admin Interface

### Dashboard
- Overview statistics
- Recent gift certificates
- Pending deliveries
- Quick actions

### All Certificates
- List all gift certificates
- Filter by status
- View transaction history
- Manage certificates

### Settings
- Form configuration
- Field mapping
- Email settings
- API configuration

### Design Templates
- Create and manage gift certificate designs
- Upload custom images for each design
- Customize email templates per design
- Enable/disable designs

### How to Use
- Complete setup instructions
- Troubleshooting guide
- API documentation

## Security Features

- **Nonce Verification**: All AJAX requests use WordPress nonces
- **Input Sanitization**: All user inputs are properly sanitized
- **Capability Checks**: Admin functions require proper permissions
- **SQL Prepared Statements**: Database queries use prepared statements
- **XSS Protection**: Output is properly escaped
- **CSRF Protection**: Form submissions include security tokens

## Performance Optimizations

- **Database Indexing**: Optimized database queries with proper indexes
- **Caching**: Efficient data retrieval and caching strategies
- **Minimal Hooks**: Only essential WordPress hooks are used
- **Lazy Loading**: Components are loaded only when needed
- **Asset Optimization**: CSS and JS files are minified and optimized

## Design Templates

The plugin includes a powerful design template system that allows you to create multiple gift certificate designs with custom images and email templates.

### Creating Design Templates

1. Go to **Gift Certificates → Design Templates**
2. Click **Add New Design**
3. Configure the design:
   - **Design Name**: A descriptive name for the design
   - **Design Image**: Upload a custom image for the gift certificate
   - **Email Template**: Customize the email template for this design
   - **Email Format**: Choose between HTML or plain text
   - **Active**: Enable or disable the design

### Using Design Templates in Forms

1. Add a radio or select field to your Fluent Forms form
2. Set the field name to match your settings (default: `gift_certificate_design`)
3. Add options with design IDs as values:
   - `default` - Default design
   - `design_123` - Custom design (use the ID shown in admin)
4. Configure the plugin settings to map this field

### Design Template Features

- **Custom Images**: Each design can have its own image
- **Individual Email Templates**: Different email templates per design
- **HTML Support**: Rich HTML email templates with styling
- **Placeholder Support**: Use placeholders like `{recipient_name}`, `{amount}`, etc.
- **Responsive Design**: Email templates work on all devices

### Default Design

The plugin includes a default design that:
- Uses a professional HTML email template
- Includes a placeholder for design images
- Works immediately without configuration
- Cannot be deleted but can be customized

## Troubleshooting

### Common Issues

**Gift certificates not being created:**
- Check form ID in settings
- Verify field mapping
- Check WordPress error logs

**Coupons not working:**
- Ensure Fluent Forms Pro is active
- Verify coupon functionality is enabled
- Check coupon code format (GCXXXXXXXX)

**Emails not sending:**
- Check WordPress email configuration
- Verify email template settings
- Test email functionality

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Concurrency

Balance updates are performed using atomic SQL queries. External integrations should check the affected row count when redeeming a certificate and retry or report a conflict if no rows are updated. See [Concurrency and External Integrations](docs/CONCURRENCY.md) for more details.

## Support

For support and documentation:
- Check the WordPress error logs
- Ensure all required plugins are updated
- Test with a default WordPress theme
- Review the troubleshooting section in the admin interface

## Changelog

### Version 1.0.0
- Initial release
- Webhook integration with Fluent Forms
- Coupon management system
- Balance tracking and API
- Admin interface
- Email templates
- Shortcodes

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please ensure your code follows WordPress coding standards and includes proper documentation. 
