# Email API - Multi-Tenant Email Sending Service

## 📋 Overview

A production-ready Laravel 10+ Email API that serves as a central email microservice supporting multiple registered domains with database-stored Blade templates.

## ✨ Features

- ✅ Multi-tenant domain management
- ✅ Blade templates stored in database
- ✅ API key authentication
- ✅ Rate limiting (hourly & daily)
- ✅ Support for cPanel Exim and Amazon SES
- ✅ Email logging and tracking
- ✅ Domain-based sending validation
- ✅ No SMTP passwords in requests
- ✅ Safe for shared hosting

## 🚀 Installation

### 1. Clone and Install Dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=email_api
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Configure Mail Settings

For **cPanel/Exim (Default)**:

```env
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Email API"
```

For **Amazon SES** (per-domain configuration in database):

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
```

### 4. Run Migrations

```bash
php artisan migrate
```

## 📊 Database Setup

### Create a Domain

```sql
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
    'eak_your_generated_api_key_here',
    1000,
    100,
    NOW(),
    NOW()
);
```

Or use Laravel Tinker:

```bash
php artisan tinker
```

```php
$domain = App\Models\EmailDomain::create([
    'domain' => 'menuvire.com',
    'from_email' => 'noreply@menuvire.com',
    'from_name' => 'MenuVire',
    'mailer' => 'exim',
    'status' => 'active',
    'daily_limit' => 1000,
    'hourly_limit' => 100,
]);

// API key is auto-generated
echo $domain->api_key;
```

### Create Email Templates

```sql
INSERT INTO email_templates (
    domain_id,
    template_key,
    subject,
    blade_html,
    status,
    created_at,
    updated_at
) VALUES (
    1,
    'otp',
    'Your OTP Code - {{ $minutes }} minutes validity',
    '<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .otp-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hello {{ $name }}!</h2>
        <p>Your OTP code is:</p>
        <div class="otp-box">{{ $otp }}</div>
        <p>This code will expire in <strong>{{ $minutes }} minutes</strong>.</p>
        <p>If you didn''t request this code, please ignore this email.</p>
    </div>
</body>
</html>',
    'active',
    NOW(),
    NOW()
);
```

Or using Tinker:

```php
App\Models\EmailTemplate::create([
    'domain_id' => 1,
    'template_key' => 'otp',
    'subject' => 'Your OTP Code - {{ $minutes }} minutes validity',
    'blade_html' => view('emails.examples.otp')->render(),
    'status' => 'active',
]);
```

## 🔌 API Usage

### Base URL

```
http://your-domain.com/api
```

### Authentication

All requests require the `X-API-Key` header:

```
X-API-Key: eak_your_api_key_here
```

### Endpoints

#### 1. Send Email

**POST** `/api/email/send`

**Headers:**
```
Content-Type: application/json
X-API-Key: eak_your_api_key_here
```

**Request Body:**
```json
{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@gmail.com",
  "data": {
    "name": "Savindu",
    "otp": "492031",
    "minutes": 5
  }
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Email sent successfully",
  "data": {
    "log_id": 123,
    "message_id": "eak_abc123_xyz789",
    "to": "user@gmail.com",
    "from": "noreply@menuvire.com",
    "subject": "Your OTP Code - 5 minutes validity",
    "sent_at": "2023-12-15T10:30:00Z",
    "mailer": "exim"
  }
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "to": ["The to field must be a valid email address."]
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "Domain mismatch. Your API key is not authorized for this domain.",
  "authenticated_domain": "yourdomain.com",
  "requested_domain": "menuvire.com"
}
```

**Error Response (429):**
```json
{
  "success": false,
  "message": "Daily rate limit exceeded"
}
```

#### 2. Get Statistics

**GET** `/api/email/stats?period=today`

**Headers:**
```
X-API-Key: eak_your_api_key_here
```

**Query Parameters:**
- `period` (optional): `today`, `week`, or `month` (default: `today`)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "sent": 145,
    "failed": 5,
    "queued": 0,
    "period": "today",
    "start_date": "2023-12-15T00:00:00Z"
  }
}
```

#### 3. Health Check

**GET** `/api/health`

No authentication required.

**Response (200):**
```json
{
  "success": true,
  "message": "Email API is running",
  "version": "1.0.0",
  "timestamp": "2023-12-15T10:30:00Z"
}
```

## 📝 Blade Template Syntax

Templates support full Blade functionality:

### Variables
```blade
Hello {{ $name }}!
Your code is: {{ $otp }}
```

### Conditionals
```blade
@if($isPremium)
    <p>Thank you for being a premium member!</p>
@else
    <p>Upgrade to premium for more benefits.</p>
@endif
```

### Loops
```blade
<ul>
@foreach($items as $item)
    <li>{{ $item }}</li>
@endforeach
</ul>
```

### Default Values
```blade
Hello {{ $name ?? 'Guest' }}!
```

## 🔒 Security Features

1. **API Key Authentication**: Every request requires a valid API key
2. **Domain Validation**: Domains must be registered and active
3. **Rate Limiting**: 60 requests per minute per API key
4. **Hourly/Daily Limits**: Per-domain email limits
5. **Template Validation**: Only registered templates can be used
6. **Email Logging**: All emails are logged for auditing
7. **No Raw HTML**: All emails must use registered templates

## 🛠️ Advanced Configuration

### Using Amazon SES

For a domain to use SES, update the database:

```sql
UPDATE email_domains 
SET 
    mailer = 'ses',
    ses_key = 'AKIAIOSFODNN7EXAMPLE',
    ses_secret = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    ses_region = 'us-east-1'
WHERE domain = 'menuvire.com';
```

### Queue Support

To enable queue support for sending emails asynchronously, update `.env`:

```env
QUEUE_CONNECTION=database
```

Run migrations for queue:

```bash
php artisan queue:table
php artisan migrate
```

Start the queue worker:

```bash
php artisan queue:work
```

## 📧 Example Templates

### OTP Email

**Template Key:** `otp`

**Subject:** `Your OTP Code - {{ $minutes }} minutes validity`

**Variables:**
- `name`: Recipient name
- `otp`: OTP code
- `minutes`: Validity period

### Welcome Email

**Template Key:** `welcome`

**Subject:** `Welcome to {{ $appName }}!`

**Variables:**
- `name`: User name
- `appName`: Application name
- `loginUrl`: Login URL

### Invoice Email

**Template Key:** `invoice`

**Subject:** `Invoice #{{ $invoiceNumber }} - {{ $amount }}`

**Variables:**
- `name`: Customer name
- `invoiceNumber`: Invoice number
- `amount`: Total amount
- `dueDate`: Payment due date
- `items`: Array of invoice items

## 🧪 Testing

### Using cURL

```bash
curl -X POST http://your-domain.com/api/email/send \
  -H "Content-Type: application/json" \
  -H "X-API-Key: eak_your_api_key_here" \
  -d '{
    "domain": "menuvire.com",
    "template": "otp",
    "to": "test@example.com",
    "data": {
      "name": "Test User",
      "otp": "123456",
      "minutes": 5
    }
  }'
```

### Using Postman

1. Create a new POST request
2. URL: `http://your-domain.com/api/email/send`
3. Headers:
   - `Content-Type: application/json`
   - `X-API-Key: eak_your_api_key_here`
4. Body (raw JSON): See request body above

### Using PHP

```php
<?php

$apiKey = 'eak_your_api_key_here';
$apiUrl = 'http://your-domain.com/api/email/send';

$data = [
    'domain' => 'menuvire.com',
    'template' => 'otp',
    'to' => 'user@example.com',
    'data' => [
        'name' => 'John Doe',
        'otp' => '492031',
        'minutes' => 5,
    ],
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
```

## 🚨 Troubleshooting

### Emails Not Sending

1. Check mail configuration in `.env`
2. Verify domain is active: `SELECT * FROM email_domains WHERE status = 'active'`
3. Check email logs: `SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10`
4. Verify sendmail path: `which sendmail`

### Rate Limit Issues

1. Check current usage: `GET /api/email/stats`
2. Increase limits in database:
   ```sql
   UPDATE email_domains 
   SET hourly_limit = 200, daily_limit = 2000 
   WHERE domain = 'yourdomain.com';
   ```

### Template Rendering Errors

1. Validate Blade syntax
2. Check all variables are provided in request
3. Test template in Tinker:
   ```php
   $template = App\Models\EmailTemplate::find(1);
   echo Blade::render($template->blade_html, ['name' => 'Test', 'otp' => '123456']);
   ```

## 📂 Project Structure

```
email-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── EmailController.php
│   │   └── Middleware/
│   │       └── ApiKeyAuthentication.php
│   ├── Mail/
│   │   └── DynamicTemplateMail.php
│   ├── Models/
│   │   ├── EmailDomain.php
│   │   ├── EmailTemplate.php
│   │   └── EmailLog.php
│   └── Services/
│       └── EmailService.php
├── database/
│   └── migrations/
│       ├── 2023_01_01_000001_create_email_domains_table.php
│       ├── 2023_01_01_000002_create_email_templates_table.php
│       └── 2023_01_01_000003_create_email_logs_table.php
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
└── bootstrap/
    └── app.php
```

## 🔐 Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Configure proper database credentials
- [ ] Set up SSL certificate (HTTPS)
- [ ] Configure firewall rules
- [ ] Set appropriate rate limits
- [ ] Enable queue workers for async sending
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy
- [ ] Test all email templates
- [ ] Secure API keys (rotate regularly)

## 📄 License

Proprietary - All rights reserved

## 👤 Author

Savindu Shenal

## 🤝 Support

For support, email: support@yourdomain.com
