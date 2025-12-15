# 📚 Documentation Index

Welcome to the Email API documentation! This index will help you navigate through all available documentation.

## 🚀 Getting Started

### Quick Start
- **[QUICKSTART.md](QUICKSTART.md)** - Fast setup guide (5-10 minutes)
  - Installation steps
  - Configuration
  - First email send
  - Common commands

### Complete Documentation
- **[README.md](README.md)** - Complete system documentation
  - Full feature overview
  - Installation guide
  - API usage examples
  - Template syntax
  - Troubleshooting

### Project Overview
- **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Executive summary
  - What's included
  - Features list
  - Technology stack
  - Key advantages

## 🏗️ Architecture & Design

- **[ARCHITECTURE.md](ARCHITECTURE.md)** - System architecture diagrams
  - High-level architecture
  - Data flow diagrams
  - Database relationships
  - Security flow
  - Multi-tenant isolation
  - Scalability design

## 📖 API Documentation

- **[API_RESPONSES.md](API_RESPONSES.md)** - Complete API reference
  - All endpoints documented
  - Request examples
  - Response formats
  - Error codes and messages
  - Client implementation examples (PHP, JavaScript, Python)
  - Best practices

- **[postman_collection.json](postman_collection.json)** - Postman collection
  - Import into Postman
  - Pre-configured requests
  - Environment variables
  - Example error scenarios

## 🚀 Deployment

- **[DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)** - Production deployment guide
  - Pre-deployment checklist
  - Server setup (cPanel)
  - Security checklist
  - Performance optimization
  - Testing procedures
  - Monitoring setup
  - Backup strategy
  - Post-deployment verification

- **[SES_SETUP_GUIDE.md](SES_SETUP_GUIDE.md)** - Amazon SES configuration
  - AWS account setup
  - Domain verification
  - IAM configuration
  - Laravel integration
  - DNS records (SPF, DKIM, DMARC)
  - Cost estimation
  - Troubleshooting

## 💻 Code Reference

### Database

#### Migrations
```
database/migrations/
├── 2023_01_01_000001_create_email_domains_table.php
├── 2023_01_01_000002_create_email_templates_table.php
└── 2023_01_01_000003_create_email_logs_table.php
```

- **email_domains**: Domain registration and configuration
- **email_templates**: Blade templates storage
- **email_logs**: Email sending audit trail

#### Seeders
- **[database/seeders/EmailApiSeeder.php](database/seeders/EmailApiSeeder.php)** - Sample data seeder
  - Creates sample domain
  - Generates API key
  - Creates 3 example templates (OTP, Welcome, Invoice)

#### Sample Data
- **[database/sample_data.sql](database/sample_data.sql)** - SQL examples
  - Manual domain creation
  - Template creation with HTML examples
  - Sample API requests

### Application Code

#### Models
```
app/Models/
├── EmailDomain.php     - Domain management
├── EmailTemplate.php   - Template management
└── EmailLog.php        - Email logging
```

#### Controllers
```
app/Http/Controllers/Api/
└── EmailController.php - API endpoints (send, stats, health)
```

#### Middleware
```
app/Http/Middleware/
└── ApiKeyAuthentication.php - API key validation
```

#### Services
```
app/Services/
└── EmailService.php - Core email sending logic
```

#### Mailable
```
app/Mail/
└── DynamicTemplateMail.php - Email generation
```

#### Routes
```
routes/
├── api.php     - API routes
├── web.php     - Web routes (minimal)
└── console.php - Console commands
```

#### Bootstrap
```
bootstrap/
└── app.php - Application bootstrap and middleware registration
```

### Configuration
- **[.env.example](.env.example)** - Environment variables template
  - Database configuration
  - Mail settings for cPanel
  - Optional SES settings

## 📧 Email Templates

### Example Templates (in database seeder)

1. **OTP Email**
   - Template key: `otp`
   - Variables: `name`, `otp`, `minutes`
   - Use case: Two-factor authentication

2. **Welcome Email**
   - Template key: `welcome`
   - Variables: `name`, `appName`, `loginUrl`
   - Use case: New user onboarding

3. **Invoice Email**
   - Template key: `invoice`
   - Variables: `name`, `invoiceNumber`, `amount`, `dueDate`, `items[]`
   - Use case: Billing notifications

### Template Syntax Reference (in README.md)
- Variables: `{{ $variable }}`
- Conditionals: `@if`, `@else`, `@endif`
- Loops: `@foreach`, `@endforeach`
- Defaults: `{{ $variable ?? 'default' }}`

## 🔧 Configuration

### Environment Setup
1. Copy `.env.example` to `.env`
2. Configure database credentials
3. Set mail driver (sendmail for cPanel, ses for Amazon)
4. Run migrations
5. Seed sample data

### cPanel Configuration
- Document root points to `/public`
- Sendmail path: `/usr/sbin/sendmail -bs -i`
- File permissions: 755 for storage and bootstrap/cache

### SES Configuration
- See [SES_SETUP_GUIDE.md](SES_SETUP_GUIDE.md)
- Configure per-domain in database
- No global SES credentials needed

## 🧪 Testing

### Manual Testing
1. Health check: `GET /api/health`
2. Send test email via cURL (examples in QUICKSTART.md)
3. Import Postman collection for structured testing

### Validation Testing
- Missing API key (401)
- Invalid API key (401)
- Domain mismatch (403)
- Validation errors (422)
- Rate limiting (429)

## 📊 Monitoring

### Email Logs
```sql
-- View recent emails
SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10;

-- Check success rate
SELECT 
    status, 
    COUNT(*) as count 
FROM email_logs 
GROUP BY status;

-- Failed emails
SELECT * FROM email_logs WHERE status = 'failed';
```

### Statistics API
```bash
# Today's stats
curl -H "X-API-Key: YOUR_KEY" \
  http://your-domain.com/api/email/stats?period=today

# Weekly stats
curl -H "X-API-Key: YOUR_KEY" \
  http://your-domain.com/api/email/stats?period=week
```

### Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

## 🔐 Security

### Best Practices (from DEPLOYMENT_CHECKLIST.md)
- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Use HTTPS/SSL
- Secure API keys
- Set appropriate rate limits
- Monitor logs for abuse
- Regular Laravel updates

### Security Features
- API key authentication
- Domain validation
- Rate limiting (global + per-domain)
- Template validation
- SQL injection protection (Eloquent)
- XSS protection (Blade escaping)

## 🆘 Troubleshooting

### Common Issues (from README.md)

1. **Emails not sending**
   - Check mail configuration
   - Verify domain is active
   - Check email logs
   - Test sendmail

2. **Rate limits**
   - Check statistics
   - Increase limits in database

3. **Template errors**
   - Validate Blade syntax
   - Ensure all variables provided
   - Test in Tinker

### Log Files
- Application: `storage/logs/laravel.log`
- Database: `email_logs` table

## 📝 API Reference Quick Links

### Endpoints
- `POST /api/email/send` - Send email
- `GET /api/email/stats` - Get statistics
- `GET /api/health` - Health check

### Authentication
```
X-API-Key: eak_your_api_key_here
```

### Request Format
```json
{
  "domain": "menuvire.com",
  "template": "otp",
  "to": "user@example.com",
  "data": {
    "name": "John Doe",
    "otp": "123456",
    "minutes": 5
  }
}
```

## 🔄 Workflow

### Typical Implementation Flow
1. Read **QUICKSTART.md** for initial setup
2. Run migrations and seeders
3. Test with sample domain and templates
4. Read **README.md** for detailed understanding
5. Import **postman_collection.json** for testing
6. Create your domains and templates
7. Integrate with your application
8. Use **DEPLOYMENT_CHECKLIST.md** for production
9. Optionally configure SES using **SES_SETUP_GUIDE.md**

### Daily Operations
1. Monitor email logs
2. Check statistics API
3. Review error logs
4. Monitor rate limit usage

## 📞 Support

### When You Need Help

1. **Installation issues**: Check QUICKSTART.md
2. **API questions**: Check API_RESPONSES.md
3. **Deployment**: Check DEPLOYMENT_CHECKLIST.md
4. **SES setup**: Check SES_SETUP_GUIDE.md
5. **Architecture questions**: Check ARCHITECTURE.md
6. **General questions**: Check README.md

### Logs to Check
```bash
# Application logs
tail -f storage/logs/laravel.log

# Database logs
mysql> SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 20;

# Failed emails
mysql> SELECT * FROM email_logs WHERE status = 'failed';
```

## 🎯 Quick Commands Reference

```bash
# Installation
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=EmailApiSeeder

# Get API key
php artisan tinker
>>> App\Models\EmailDomain::first()->api_key

# Check recent logs
>>> App\Models\EmailLog::latest()->take(10)->get()

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Production optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 📦 File Structure Overview

```
email-api/
├── Documentation/
│   ├── README.md                      - Complete guide
│   ├── QUICKSTART.md                  - Quick setup
│   ├── PROJECT_SUMMARY.md             - Overview
│   ├── ARCHITECTURE.md                - System design
│   ├── API_RESPONSES.md               - API docs
│   ├── DEPLOYMENT_CHECKLIST.md        - Deploy guide
│   ├── SES_SETUP_GUIDE.md            - SES config
│   └── DOCS_INDEX.md                  - This file
│
├── Configuration/
│   ├── .env.example                   - Env template
│   └── postman_collection.json        - API collection
│
├── Application Code/
│   ├── app/                           - Laravel app
│   ├── bootstrap/                     - Bootstrap
│   ├── database/                      - Migrations
│   └── routes/                        - Routes
│
└── Samples/
    └── database/sample_data.sql       - SQL examples
```

## 🎓 Learning Path

### Beginner
1. Start with **PROJECT_SUMMARY.md** (5 min read)
2. Follow **QUICKSTART.md** (10 min setup)
3. Test with Postman collection (5 min)
4. Read **API_RESPONSES.md** (15 min)

### Intermediate
1. Study **README.md** thoroughly (30 min)
2. Review **ARCHITECTURE.md** (20 min)
3. Explore database structure
4. Customize templates

### Advanced
1. Read **DEPLOYMENT_CHECKLIST.md** (30 min)
2. Configure **SES_SETUP_GUIDE.md** if needed (45 min)
3. Implement queue workers
4. Set up monitoring
5. Scale infrastructure

## ✅ Documentation Checklist

- ✅ Quick start guide
- ✅ Complete README
- ✅ API documentation
- ✅ Architecture diagrams
- ✅ Deployment guide
- ✅ SES setup guide
- ✅ Postman collection
- ✅ Sample data
- ✅ Database seeder
- ✅ Environment template
- ✅ Project summary
- ✅ This index

## 📄 Document Versions

| Document | Version | Last Updated |
|----------|---------|--------------|
| README.md | 1.0 | Dec 2023 |
| QUICKSTART.md | 1.0 | Dec 2023 |
| API_RESPONSES.md | 1.0 | Dec 2023 |
| DEPLOYMENT_CHECKLIST.md | 1.0 | Dec 2023 |
| SES_SETUP_GUIDE.md | 1.0 | Dec 2023 |
| ARCHITECTURE.md | 1.0 | Dec 2023 |
| PROJECT_SUMMARY.md | 1.0 | Dec 2023 |

---

**System Version**: 1.0.0  
**Laravel Version**: 10+  
**PHP Version**: 8.1+  
**Documentation Status**: Complete ✅
