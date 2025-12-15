-- ============================================
-- Email API - Sample Data Setup
-- ============================================

-- 1. Create a sample domain
-- This creates a domain with auto-generated API key
INSERT INTO email_domains (
    domain, 
    from_email, 
    from_name, 
    mailer, 
    status, 
    api_key,
    daily_limit,
    hourly_limit,
    created_at,
    updated_at
) VALUES (
    'menuvire.com',
    'noreply@menuvire.com',
    'MenuVire',
    'exim',
    'active',
    'eak_sample_key_replace_with_real_generated_key',
    1000,
    100,
    NOW(),
    NOW()
);

-- Get the domain ID (you'll need this for templates)
-- SELECT id, api_key FROM email_domains WHERE domain = 'menuvire.com';

-- 2. Create OTP Email Template
INSERT INTO email_templates (
    domain_id,
    template_key,
    subject,
    blade_html,
    status,
    created_at,
    updated_at
) VALUES (
    1, -- Replace with actual domain_id
    'otp',
    'Your OTP Code - {{ $minutes }} minutes validity',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6; 
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container { 
            max-width: 600px; 
            margin: 40px auto; 
            padding: 0;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .content {
            padding: 40px 30px;
        }
        .otp-box { 
            background: #f8f9fa; 
            padding: 25px; 
            text-align: center; 
            font-size: 32px; 
            font-weight: bold; 
            letter-spacing: 8px; 
            margin: 20px 0;
            border-radius: 6px;
            border: 2px dashed #667eea;
            color: #667eea;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .warning {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Security Code</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $name }}!</h2>
            <p>You requested a one-time password (OTP) to verify your identity.</p>
            <div class="otp-box">{{ $otp }}</div>
            <p>This code will <span class="warning">expire in {{ $minutes }} minutes</span>.</p>
            <p>If you didn''t request this code, please ignore this email or contact support if you have concerns.</p>
            <p>For security reasons, never share this code with anyone.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date("Y") }} MenuVire. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>',
    'active',
    NOW(),
    NOW()
);

-- 3. Create Welcome Email Template
INSERT INTO email_templates (
    domain_id,
    template_key,
    subject,
    blade_html,
    status,
    created_at,
    updated_at
) VALUES (
    1, -- Replace with actual domain_id
    'welcome',
    'Welcome to {{ $appName }}!',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; 
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container { 
            max-width: 600px; 
            margin: 40px auto; 
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .content {
            padding: 40px 30px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .features {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .feature-item {
            margin: 10px 0;
            padding-left: 25px;
            position: relative;
        }
        .feature-item:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #38ef7d;
            font-weight: bold;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Welcome to {{ $appName }}!</h1>
        </div>
        <div class="content">
            <h2>Hi {{ $name }},</h2>
            <p>We''re thrilled to have you on board! Your account has been successfully created.</p>
            
            <div class="features">
                <h3>What you can do now:</h3>
                <div class="feature-item">Access your personalized dashboard</div>
                <div class="feature-item">Customize your profile settings</div>
                <div class="feature-item">Explore our premium features</div>
                <div class="feature-item">Connect with our community</div>
            </div>

            <p style="text-align: center;">
                <a href="{{ $loginUrl }}" class="button">Get Started Now</a>
            </p>

            <p>If you have any questions, our support team is here to help!</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date("Y") }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
    'active',
    NOW(),
    NOW()
);

-- 4. Create Invoice Email Template
INSERT INTO email_templates (
    domain_id,
    template_key,
    subject,
    blade_html,
    status,
    created_at,
    updated_at
) VALUES (
    1, -- Replace with actual domain_id
    'invoice',
    'Invoice #{{ $invoiceNumber }} - {{ $amount }}',
    '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; 
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container { 
            max-width: 700px; 
            margin: 40px auto; 
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
            padding: 30px;
            color: white;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            padding: 20px 30px;
            background-color: #f8f9fa;
        }
        .content {
            padding: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .total-row {
            font-weight: bold;
            font-size: 18px;
            background-color: #f8f9fa;
        }
        .footer {
            background-color: #2c3e50;
            padding: 20px 30px;
            text-align: center;
            color: white;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">INVOICE</h1>
            <p style="margin: 5px 0 0 0;">Invoice #{{ $invoiceNumber }}</p>
        </div>
        
        <div class="invoice-info">
            <div>
                <strong>Bill To:</strong><br>
                {{ $name }}
            </div>
            <div>
                <strong>Due Date:</strong><br>
                {{ $dueDate }}
            </div>
        </div>
        
        <div class="content">
            <h3>Invoice Details</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $item["description"] }}</td>
                        <td>{{ $item["quantity"] }}</td>
                        <td>${{ number_format($item["price"], 2) }}</td>
                        <td>${{ number_format($item["quantity"] * $item["price"], 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total Amount:</td>
                        <td>{{ $amount }}</td>
                    </tr>
                </tbody>
            </table>
            
            <p><strong>Payment Terms:</strong> Payment is due within 30 days.</p>
            <p>Thank you for your business!</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date("Y") }} MenuVire. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
    'active',
    NOW(),
    NOW()
);

-- ============================================
-- Sample API Requests
-- ============================================

/*
1. Send OTP Email:
POST /api/email/send
Headers: X-API-Key: [your-api-key]
{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {
    "name": "John Doe",
    "otp": "492031",
    "minutes": 5
  }
}

2. Send Welcome Email:
POST /api/email/send
Headers: X-API-Key: [your-api-key]
{
  "domain": "menuvire.com",
  "template": "welcome",
  "to": "newuser@example.com",
  "data": {
    "name": "Jane Smith",
    "appName": "MenuVire",
    "loginUrl": "https://menuvire.com/login"
  }
}

3. Send Invoice Email:
POST /api/email/send
Headers: X-API-Key: [your-api-key]
{
  "domain": "menuvire.com",
  "template": "invoice",
  "to": "customer@example.com",
  "data": {
    "name": "ABC Company",
    "invoiceNumber": "INV-2023-001",
    "amount": "$250.00",
    "dueDate": "2024-01-15",
    "items": [
      {
        "description": "Premium Subscription",
        "quantity": 1,
        "price": 200.00
      },
      {
        "description": "Additional Users",
        "quantity": 5,
        "price": 10.00
      }
    ]
  }
}
*/
