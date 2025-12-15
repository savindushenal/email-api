# 🎯 Project Summary - Multi-Tenant Email API

## Overview

A production-ready Laravel 10+ Email API designed to serve as a central email microservice for multiple platforms and domains. The system provides secure, scalable email sending with database-stored Blade templates and support for both cPanel Exim and Amazon SES.

## ✅ Completed Features

### 1. Core Functionality
- ✅ Multi-tenant domain management
- ✅ API key authentication system
- ✅ Database-stored Blade templates with full syntax support
- ✅ Dynamic email rendering with variables, conditionals, and loops
- ✅ Dual mail transport (Exim/SES) per domain
- ✅ RESTful API endpoints
- ✅ Comprehensive email logging

### 2. Security
- ✅ API key authentication middleware
- ✅ Domain ownership validation
- ✅ Rate limiting (60 requests/minute + per-domain limits)
- ✅ Hourly and daily sending limits per domain
- ✅ Template validation and rendering security
- ✅ No SMTP passwords in API requests
- ✅ SQL injection protection via Eloquent

### 3. Email Management
- ✅ Template management system
- ✅ Support for Blade syntax (variables, conditions, loops)
- ✅ Dynamic subject line rendering
- ✅ HTML email support
- ✅ Multiple templates per domain
- ✅ Template status management (active/inactive)

### 4. Monitoring & Logging
- ✅ Comprehensive email logging
- ✅ Success/failure tracking
- ✅ Error message logging
- ✅ Statistics API endpoint
- ✅ Sending analytics (daily/weekly/monthly)

## 📁 Project Structure

```
email-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── EmailController.php          # API endpoints
│   │   └── Middleware/
│   │       └── ApiKeyAuthentication.php         # API key validation
│   ├── Mail/
│   │   └── DynamicTemplateMail.php              # Mailable class
│   ├── Models/
│   │   ├── EmailDomain.php                      # Domain management
│   │   ├── EmailTemplate.php                    # Template management
│   │   └── EmailLog.php                         # Email logging
│   └── Services/
│       └── EmailService.php                     # Core email logic
├── bootstrap/
│   └── app.php                                  # Bootstrap configuration
├── database/
│   ├── migrations/
│   │   ├── *_create_email_domains_table.php
│   │   ├── *_create_email_templates_table.php
│   │   └── *_create_email_logs_table.php
│   ├── seeders/
│   │   └── EmailApiSeeder.php                   # Sample data seeder
│   └── sample_data.sql                          # Manual SQL samples
├── routes/
│   ├── api.php                                  # API routes
│   ├── web.php                                  # Web routes
│   └── console.php                              # Console commands
├── .env.example                                 # Environment template
├── README.md                                    # Complete documentation
├── QUICKSTART.md                                # Quick setup guide
├── API_RESPONSES.md                             # API documentation
├── DEPLOYMENT_CHECKLIST.md                      # Production checklist
├── SES_SETUP_GUIDE.md                          # Amazon SES guide
└── postman_collection.json                      # Postman collection
```

## 🗄️ Database Schema

### email_domains
- `id`: Primary key
- `domain`: Domain name (unique)
- `from_email`: Sender email address
- `from_name`: Sender name
- `mailer`: Transport type (exim/ses)
- `status`: Domain status (active/inactive/suspended)
- `api_key`: Authentication key (auto-generated)
- `ses_key`, `ses_secret`, `ses_region`: SES credentials (optional)
- `daily_limit`, `hourly_limit`: Rate limits
- `timestamps`: Created/updated timestamps

### email_templates
- `id`: Primary key
- `domain_id`: Foreign key to email_domains
- `template_key`: Template identifier (otp, welcome, etc.)
- `subject`: Email subject (supports Blade syntax)
- `blade_html`: HTML template with Blade syntax
- `status`: Template status (active/inactive)
- `timestamps`: Created/updated timestamps
- **Unique constraint**: (domain_id, template_key)

### email_logs
- `id`: Primary key
- `domain_id`: Foreign key to email_domains
- `template_id`: Foreign key to email_templates
- `from_email`: Sender email
- `to_email`: Recipient email
- `subject`: Rendered subject
- `template_key`: Template used
- `status`: Sending status (sent/failed/queued)
- `error_message`: Error details (if failed)
- `mailer_used`: Transport used (exim/ses)
- `message_id`: Unique message identifier
- `variables`: JSON data passed to template
- `sent_at`: Timestamp when sent
- `timestamps`: Created/updated timestamps

## 🔌 API Endpoints

### POST /api/email/send
Send an email using a registered template.

**Authentication**: Required (X-API-Key header)  
**Rate Limit**: 60 requests/minute

**Request:**
```json
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
```

**Response (200):**
```json
{
  "success": true,
  "message": "Email sent successfully",
  "data": {
    "log_id": 123,
    "message_id": "eak_abc123_xyz789",
    "to": "user@example.com",
    "from": "noreply@menuvire.com",
    "subject": "Your OTP Code - 5 minutes validity",
    "sent_at": "2023-12-15T10:30:00Z",
    "mailer": "exim"
  }
}
```

### GET /api/email/stats
Get sending statistics for authenticated domain.

**Authentication**: Required  
**Parameters**: `period` (today/week/month)

**Response:**
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

### GET /api/health
Health check endpoint (no authentication required).

**Response:**
```json
{
  "success": true,
  "message": "Email API is running",
  "version": "1.0.0",
  "timestamp": "2023-12-15T10:30:00Z"
}
```

## 🔐 Security Features

1. **API Key Authentication**: Every request requires valid API key
2. **Domain Validation**: API key must match requested domain
3. **Rate Limiting**: 
   - Global: 60 requests/minute
   - Per-domain hourly limits
   - Per-domain daily limits
4. **Template Security**: Only registered templates can be used
5. **Input Validation**: All inputs validated before processing
6. **No Sensitive Data in Requests**: SMTP passwords stored in database
7. **Audit Trail**: All emails logged with full details

## 📧 Sample Templates

### 1. OTP Email
**Template Key**: `otp`  
**Variables**: `name`, `otp`, `minutes`  
**Use Case**: Two-factor authentication, password resets

### 2. Welcome Email
**Template Key**: `welcome`  
**Variables**: `name`, `appName`, `loginUrl`  
**Use Case**: New user onboarding

### 3. Invoice Email
**Template Key**: `invoice`  
**Variables**: `name`, `invoiceNumber`, `amount`, `dueDate`, `items[]`  
**Use Case**: Billing, receipts, invoices

## 🚀 Deployment Options

### Option 1: cPanel Shared Hosting
- Uses Exim/sendmail (default)
- No additional configuration needed
- Perfect for small to medium volume
- Cost-effective

### Option 2: Amazon SES
- High deliverability rates
- Scalable to millions of emails
- $0.10 per 1,000 emails
- Requires AWS account and domain verification

### Option 3: Hybrid
- Different mailers per domain
- Low-volume domains use Exim
- High-volume domains use SES
- Flexible and cost-optimized

## 📖 Documentation Files

1. **README.md**: Complete system documentation
2. **QUICKSTART.md**: Fast setup guide
3. **API_RESPONSES.md**: API documentation with examples
4. **DEPLOYMENT_CHECKLIST.md**: Production deployment guide
5. **SES_SETUP_GUIDE.md**: Amazon SES configuration
6. **postman_collection.json**: Postman API collection
7. **sample_data.sql**: SQL examples and sample data

## 🛠️ Technology Stack

- **Framework**: Laravel 10+
- **PHP**: 8.1+
- **Database**: MySQL 5.7+ / MariaDB
- **Mail Transports**: Sendmail/Exim, Amazon SES
- **Template Engine**: Blade
- **Authentication**: Custom API key middleware
- **Rate Limiting**: Laravel throttle middleware

## ✨ Key Advantages

1. **Centralized Email Management**: Single API for all applications
2. **Multi-Tenant**: Support multiple domains/applications
3. **Template Flexibility**: Blade syntax for powerful templating
4. **Dual Transport**: Choose between Exim and SES per domain
5. **Production-Ready**: Complete with logging, rate limiting, security
6. **Easy Integration**: Simple REST API, works with any platform
7. **Safe for Shared Hosting**: No security compromises
8. **Comprehensive Logging**: Full audit trail of all emails
9. **Statistics**: Built-in analytics endpoint
10. **Well-Documented**: Extensive documentation and examples

## 🎓 Usage Example

```php
// PHP Example
$apiKey = 'eak_your_api_key_here';
$apiUrl = 'https://your-domain.com/api/email/send';

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
$result = json_decode($response, true);

if ($result['success']) {
    echo "Email sent! Log ID: " . $result['data']['log_id'];
} else {
    echo "Error: " . $result['message'];
}
```

## 🔄 Future Enhancements (Optional)

- [ ] Queue support for async email sending
- [ ] Webhook notifications for delivery status
- [ ] Email scheduling
- [ ] Template versioning
- [ ] A/B testing for templates
- [ ] Advanced analytics dashboard
- [ ] Attachment support
- [ ] Template preview in admin panel
- [ ] Multi-language template support
- [ ] SMS integration

## 📞 Support & Maintenance

### Logs Location
- **Application Logs**: `storage/logs/laravel.log`
- **Email Logs**: Database table `email_logs`

### Monitoring Commands
```bash
# View recent logs
tail -f storage/logs/laravel.log

# Check email stats via Tinker
php artisan tinker
>>> App\Models\EmailLog::where('created_at', '>=', now()->startOfDay())->count()

# View failed emails
>>> App\Models\EmailLog::failed()->latest()->take(10)->get()
```

### Common Commands
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Generate API key for new domain
php artisan tinker
>>> $domain = App\Models\EmailDomain::create([...]);
>>> echo $domain->api_key;

# Run migrations
php artisan migrate

# Seed sample data
php artisan db:seed --class=EmailApiSeeder
```

## ✅ Production-Ready Checklist

- ✅ All migrations created and tested
- ✅ Models with relationships and scopes
- ✅ API key authentication middleware
- ✅ Rate limiting configured
- ✅ Email service with Blade rendering
- ✅ Dynamic mail transport selection
- ✅ Comprehensive error handling
- ✅ Input validation
- ✅ Email logging system
- ✅ Statistics endpoint
- ✅ Health check endpoint
- ✅ Sample templates included
- ✅ Complete documentation
- ✅ Postman collection
- ✅ Deployment checklist
- ✅ SES setup guide
- ✅ Database seeder
- ✅ .env.example file

## 🎉 Conclusion

This Email API is a **complete, production-ready solution** for multi-tenant email sending. It provides all the features requested:

✅ Domain-based sending with validation  
✅ Database-stored Blade templates  
✅ API key authentication  
✅ Support for cPanel (Exim) and Amazon SES  
✅ Rate limiting and security  
✅ Comprehensive logging  
✅ Clean architecture (Controller + Service pattern)  
✅ Ready for queue support  
✅ API-only (no UI)  
✅ Safe for shared hosting  

The system is **secure, scalable, and well-documented**, ready for immediate production deployment.

---

**Version**: 1.0.0  
**Author**: Built for Savindu Shenal  
**Date**: December 2023  
**Laravel Version**: 10+  
**PHP Version**: 8.1+
