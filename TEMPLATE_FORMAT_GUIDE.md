# Email Template Format Guide

## Overview
This guide explains how to create custom email templates with embedded data variables.

## Template Structure

### Required Fields
- **template_key**: Unique identifier (lowercase, numbers, hyphens, underscores only)
- **category**: Template category (e.g., "authentication", "notification", "transactional")
- **subject**: Email subject line (supports variables)
- **blade_html**: HTML template body using Laravel Blade syntax

### Optional Fields
- **description**: Human-readable description of the template's purpose
- **variables**: Array defining expected data variables with types and descriptions
- **status**: "active" or "inactive" (default: "active")

---

## Variable Definition Format

Define variables to document what data your template expects:

```json
{
  "variables": [
    {
      "name": "user_name",
      "type": "string",
      "description": "The recipient's full name",
      "required": true,
      "default": null
    },
    {
      "name": "verification_link",
      "type": "url",
      "description": "Link for email verification",
      "required": true,
      "default": null
    },
    {
      "name": "expires_at",
      "type": "string",
      "description": "When the link expires",
      "required": false,
      "default": "24 hours"
    }
  ]
}
```

### Supported Variable Types
- `string`: Text content
- `number`: Numeric values
- `boolean`: True/false values
- `date`: Date/datetime strings
- `url`: URLs/links
- `email`: Email addresses

---

## Blade Template Syntax

### Basic Variable Insertion
```html
<h1>Hello, {{ $user_name }}!</h1>
<p>Your email is: {{ $email }}</p>
```

### Conditional Content
```html
@if($is_premium)
    <p>Welcome to Premium!</p>
@else
    <p>Upgrade to Premium for more features.</p>
@endif
```

### Loops
```html
<ul>
@foreach($items as $item)
    <li>{{ $item }}</li>
@endforeach
</ul>
```

### Default Values
```html
<p>Welcome, {{ $user_name ?? 'Guest' }}!</p>
```

### Escaped vs Unescaped Output
```html
{{ $safe_text }}      <!-- Auto-escaped for security -->
{!! $html_content !!} <!-- Unescaped (use with caution) -->
```

---

## Complete Template Examples

### Example 1: Welcome Email

**API Request:**
```json
{
  "template_key": "welcome-email",
  "category": "authentication",
  "description": "Welcome email sent to new users after registration",
  "subject": "Welcome to {{ $platform_name }}!",
  "blade_html": "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <style>\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }\n        .content { padding: 30px 20px; }\n        .button { display: inline-block; padding: 12px 30px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; }\n        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\n    </style>\n</head>\n<body>\n    <div class=\"container\">\n        <div class=\"header\">\n            <h1>Welcome to {{ $platform_name }}</h1>\n        </div>\n        <div class=\"content\">\n            <h2>Hi {{ $user_name }},</h2>\n            <p>Thank you for joining {{ $platform_name }}! We're excited to have you on board.</p>\n            <p>To get started, please verify your email address by clicking the button below:</p>\n            <p style=\"text-align: center; margin: 30px 0;\">\n                <a href=\"{{ $verification_link }}\" class=\"button\">Verify Email Address</a>\n            </p>\n            <p>If the button doesn't work, copy and paste this link into your browser:</p>\n            <p style=\"word-break: break-all; color: #4F46E5;\">{{ $verification_link }}</p>\n            @if(isset($expires_at))\n            <p><small>This link will expire in {{ $expires_at }}.</small></p>\n            @endif\n        </div>\n        <div class=\"footer\">\n            <p>&copy; {{ date('Y') }} {{ $platform_name }}. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>",
  "variables": [
    {
      "name": "user_name",
      "type": "string",
      "description": "The new user's name",
      "required": true
    },
    {
      "name": "platform_name",
      "type": "string",
      "description": "Name of your platform",
      "required": true
    },
    {
      "name": "verification_link",
      "type": "url",
      "description": "Email verification URL",
      "required": true
    },
    {
      "name": "expires_at",
      "type": "string",
      "description": "Link expiration time",
      "required": false,
      "default": "24 hours"
    }
  ],
  "status": "active"
}
```

### Example 2: Password Reset

**API Request:**
```json
{
  "template_key": "password-reset",
  "category": "authentication",
  "description": "Password reset email with secure token link",
  "subject": "Reset Your {{ $platform_name }} Password",
  "blade_html": "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <style>\n        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .content { padding: 20px; background: #f9fafb; border-radius: 8px; }\n        .button { display: inline-block; padding: 12px 30px; background: #DC2626; color: white; text-decoration: none; border-radius: 5px; }\n        .warning { background: #FEF2F2; border-left: 4px solid #DC2626; padding: 15px; margin: 20px 0; }\n    </style>\n</head>\n<body>\n    <div class=\"container\">\n        <div class=\"content\">\n            <h2>Password Reset Request</h2>\n            <p>Hi {{ $user_name }},</p>\n            <p>We received a request to reset your password for your {{ $platform_name }} account.</p>\n            <p style=\"text-align: center; margin: 30px 0;\">\n                <a href=\"{{ $reset_link }}\" class=\"button\">Reset Password</a>\n            </p>\n            <p>Or copy this link: <span style=\"color: #DC2626;\">{{ $reset_link }}</span></p>\n            <p><strong>This link expires in {{ $expires_at }}.</strong></p>\n            <div class=\"warning\">\n                <p><strong>⚠️ Security Notice:</strong></p>\n                <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>\n            </div>\n        </div>\n    </div>\n</body>\n</html>",
  "variables": [
    {
      "name": "user_name",
      "type": "string",
      "description": "User's name",
      "required": true
    },
    {
      "name": "platform_name",
      "type": "string",
      "description": "Platform name",
      "required": true
    },
    {
      "name": "reset_link",
      "type": "url",
      "description": "Password reset URL with token",
      "required": true
    },
    {
      "name": "expires_at",
      "type": "string",
      "description": "Link expiration time",
      "required": true
    }
  ]
}
```

### Example 3: Order Confirmation (E-commerce)

**API Request:**
```json
{
  "template_key": "order-confirmation",
  "category": "transactional",
  "description": "Order confirmation with item details and tracking",
  "subject": "Order Confirmation #{{ $order_number }}",
  "blade_html": "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"utf-8\">\n    <style>\n        body { font-family: Arial, sans-serif; color: #333; }\n        .container { max-width: 600px; margin: 0 auto; padding: 20px; }\n        .header { background: #10B981; color: white; padding: 20px; text-align: center; }\n        table { width: 100%; border-collapse: collapse; margin: 20px 0; }\n        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }\n        th { background: #f3f4f6; }\n        .total { font-size: 18px; font-weight: bold; }\n    </style>\n</head>\n<body>\n    <div class=\"container\">\n        <div class=\"header\">\n            <h1>✓ Order Confirmed</h1>\n        </div>\n        <p>Hi {{ $customer_name }},</p>\n        <p>Thank you for your order! Your order #{{ $order_number }} has been confirmed.</p>\n        \n        <h3>Order Details:</h3>\n        <table>\n            <thead>\n                <tr>\n                    <th>Item</th>\n                    <th>Quantity</th>\n                    <th>Price</th>\n                </tr>\n            </thead>\n            <tbody>\n                @foreach($items as $item)\n                <tr>\n                    <td>{{ $item['name'] }}</td>\n                    <td>{{ $item['quantity'] }}</td>\n                    <td>${{ number_format($item['price'], 2) }}</td>\n                </tr>\n                @endforeach\n            </tbody>\n        </table>\n        \n        <p class=\"total\">Total: ${{ number_format($total_amount, 2) }}</p>\n        \n        <p><strong>Shipping Address:</strong><br>\n        {{ $shipping_address }}</p>\n        \n        @if(isset($tracking_url))\n        <p>Track your order: <a href=\"{{ $tracking_url }}\">{{ $tracking_number }}</a></p>\n        @endif\n        \n        <p>Estimated delivery: {{ $estimated_delivery }}</p>\n    </div>\n</body>\n</html>",
  "variables": [
    {
      "name": "customer_name",
      "type": "string",
      "description": "Customer's name",
      "required": true
    },
    {
      "name": "order_number",
      "type": "string",
      "description": "Order number/ID",
      "required": true
    },
    {
      "name": "items",
      "type": "array",
      "description": "Array of order items with name, quantity, price",
      "required": true
    },
    {
      "name": "total_amount",
      "type": "number",
      "description": "Total order amount",
      "required": true
    },
    {
      "name": "shipping_address",
      "type": "string",
      "description": "Full shipping address",
      "required": true
    },
    {
      "name": "tracking_number",
      "type": "string",
      "description": "Shipment tracking number",
      "required": false
    },
    {
      "name": "tracking_url",
      "type": "url",
      "description": "Tracking URL",
      "required": false
    },
    {
      "name": "estimated_delivery",
      "type": "date",
      "description": "Estimated delivery date",
      "required": true
    }
  ]
}
```

---

## API Endpoints

### Create Template
```bash
POST /api/email/templates
X-API-Key: your_api_key
Content-Type: application/json

{
  "template_key": "my-template",
  "category": "notification",
  "description": "My custom template",
  "subject": "Subject with {{ $variable }}",
  "blade_html": "<html>...</html>",
  "variables": [...],
  "status": "active"
}
```

### List Templates
```bash
GET /api/email/templates?category=authentication
X-API-Key: your_api_key
```

### Get Template
```bash
GET /api/email/templates/my-template
X-API-Key: your_api_key
```

### Update Template
```bash
PUT /api/email/templates/my-template
X-API-Key: your_api_key
Content-Type: application/json

{
  "subject": "Updated subject",
  "blade_html": "<html>Updated content</html>"
}
```

### Preview Template
```bash
POST /api/email/templates/my-template/preview
X-API-Key: your_api_key
Content-Type: application/json

{
  "data": {
    "user_name": "John Doe",
    "platform_name": "MyApp"
  }
}
```

### Delete Template
```bash
DELETE /api/email/templates/my-template
X-API-Key: your_api_key
```

---

## Common Template Categories

- **authentication**: Login, signup, verification, password reset
- **notification**: Alerts, reminders, updates
- **transactional**: Orders, receipts, confirmations
- **marketing**: Newsletters, promotions, announcements
- **system**: Maintenance, errors, status updates

---

## Best Practices

1. **Always define variables**: Document expected data for clarity
2. **Use descriptive keys**: Template keys should be self-explanatory
3. **Test with preview**: Use the preview endpoint before activating
4. **Escape user data**: Use `{{ }}` to prevent XSS attacks
5. **Mobile responsive**: Use max-width: 600px for email containers
6. **Include plain text alternatives**: Consider recipients without HTML support
7. **Keep it simple**: Complex CSS may not render in all email clients
8. **Test thoroughly**: Preview in multiple email clients

---

## Troubleshooting

### Template not rendering?
- Check Blade syntax for errors
- Ensure all required variables are provided
- Use preview endpoint to test

### Variables not showing?
- Verify variable names match exactly (case-sensitive)
- Check that data is passed in the `data` object when sending

### Styling issues?
- Use inline styles for best email client compatibility
- Avoid external CSS files
- Test in Gmail, Outlook, Apple Mail

---

## Support

For additional help, refer to:
- Laravel Blade Documentation: https://laravel.com/docs/blade
- Email HTML Best Practices: https://www.campaignmonitor.com/css/
