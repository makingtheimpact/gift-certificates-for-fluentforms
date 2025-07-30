# Webhook Design Template Mapping Guide

This guide explains how to connect design templates to your gift certificate purchase form through the webhook system.

## Overview

The webhook system allows customers to select different gift certificate designs when purchasing. The selected design determines the email template and styling used when the gift certificate is delivered.

## How It Works

1. **Form Field**: A radio or select field in your Fluent Forms form allows customers to choose a design
2. **Field Mapping**: The plugin maps this field to the design selection system
3. **Validation**: The webhook validates the submitted design ID against available active designs
4. **Processing**: The gift certificate is created with the selected design
5. **Delivery**: The email is sent using the design's custom template

## Setup Instructions

### Step 1: Create Design Templates

1. Go to **Gift Certificates → Design Templates**
2. Create your desired designs (or edit the default design)
3. Note the **Design ID** for each design (e.g., "default", "design_123", "holiday_theme")
4. Ensure designs are marked as **Active**

### Step 2: Configure the Form Field

1. In your Fluent Forms form, add a **Radio** or **Select** field
2. Set the **Field Name** to something like `gift_certificate_design`
3. Configure the options with these exact values:

#### Option Configuration Example

| Option Label | Option Value | Description |
|--------------|--------------|-------------|
| Classic Design | `default` | Default design (always available) |
| Holiday Theme | `design_123` | Your custom holiday design |
| Birthday Special | `design_456` | Your custom birthday design |

**Important**: The option values must exactly match the design IDs from your design templates.

### Step 3: Configure Plugin Settings

1. Go to **Gift Certificates → Settings**
2. In the **Design Selection Field** setting, enter the field name from Step 2
3. Save the settings

## Field Mapping Requirements

### Required Field Names

The following field names must be configured in the plugin settings:

- **Amount Field**: Field containing the gift certificate amount
- **Recipient Email**: Field containing the recipient's email address
- **Recipient Name**: Field containing the recipient's name
- **Sender Name**: Field containing the sender's name
- **Message**: Field containing the personal message (optional)
- **Delivery Date**: Field containing the delivery date (optional)
- **Design Selection Field**: Field containing the design selection (optional)

### Design Field Requirements

- **Field Type**: Radio or Select field
- **Field Name**: Must match what's configured in plugin settings
- **Option Values**: Must exactly match design IDs (case-sensitive)
- **Option Labels**: Can be user-friendly names (e.g., "Classic Design", "Holiday Theme")

## Validation and Error Handling

### Design ID Validation

The webhook automatically validates submitted design IDs:

1. **Valid Design**: If the submitted design ID exists and is active, it's used
2. **Invalid Design**: If the design ID doesn't exist or is inactive, the system:
   - Logs a warning message
   - Falls back to the default design
   - Continues processing the gift certificate

### Error Scenarios

| Scenario | Action | Result |
|----------|--------|--------|
| No design field configured | Uses default design | Gift certificate created successfully |
| Empty design value submitted | Uses default design | Gift certificate created successfully |
| Invalid design ID submitted | Falls back to default design | Gift certificate created with warning logged |
| Inactive design submitted | Falls back to default design | Gift certificate created with warning logged |

## Example Form Configuration

### Fluent Forms Field Setup

```
Field Type: Radio
Field Name: gift_certificate_design
Field Label: Choose Your Design

Options:
- Label: "Classic Gift Certificate" | Value: "default"
- Label: "Holiday Special" | Value: "holiday_theme"
- Label: "Birthday Celebration" | Value: "birthday_design"
- Label: "Corporate Style" | Value: "corporate_design"
```

### Plugin Settings Configuration

```
Design Selection Field: gift_certificate_design
```

## Troubleshooting

### Common Issues

1. **Design not being applied**
   - Check that the field name matches exactly in both form and plugin settings
   - Verify the option values match the design IDs exactly (case-sensitive)
   - Ensure the design is marked as active in Design Templates

2. **Default design always being used**
   - Check the error logs for validation warnings
   - Verify the submitted design ID exists and is active
   - Ensure the form field is properly configured

3. **Form submission errors**
   - Check that all required fields are mapped
   - Verify field names match exactly
   - Check error logs for specific error messages

### Debug Information

The webhook logs detailed information about design processing:

```
Gift Certificate Webhook: Extracted data - Amount: 50.00, Email: recipient@example.com, Name: John Doe, Design: holiday_theme
Gift Certificate Webhook: Valid design ID submitted: holiday_theme
Gift certificate created successfully: ID 123, Coupon: GC12345678, Design: holiday_theme
```

### Error Log Examples

```
Gift Certificate Webhook: Invalid or inactive design ID submitted: invalid_design. Available designs: default, holiday_theme, birthday_design
Gift Certificate Webhook: Falling back to default design
```

## Best Practices

1. **Design IDs**: Use descriptive, lowercase IDs with underscores (e.g., "holiday_theme", "birthday_design")
2. **Field Names**: Use consistent naming conventions (e.g., "gift_certificate_design")
3. **Testing**: Test with each design option to ensure proper mapping
4. **Documentation**: Keep a list of your design IDs for reference
5. **Validation**: Regularly check that all designs are active and accessible

## Advanced Configuration

### Multiple Design Categories

You can organize designs by creating multiple selection fields:

```
Primary Design: gift_certificate_primary_design
Seasonal Theme: gift_certificate_seasonal_theme
```

### Conditional Design Logic

Use Fluent Forms conditional logic to show/hide design options based on:
- Amount selected
- Recipient type
- Special occasions
- Seasonal promotions

### Custom Design Validation

For advanced users, you can extend the validation logic by modifying the `validate_and_process_design_id()` method in the webhook class.

## Support

If you encounter issues with design mapping:

1. Check the error logs for specific error messages
2. Verify all field mappings are correct
3. Test with the default design first
4. Contact support with specific error details and form configuration 