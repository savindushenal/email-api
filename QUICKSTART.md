# 🚀 Quick Start Guide - Email API

## Prerequisites

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB
- Composer
- cPanel hosting OR local development environment

## Installation Steps

### 1. Install Dependencies

```bash
composer install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=email_api
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Mail Configuration (cPanel Default)

In `.env`:

```env
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH="/usr/sbin/sendmail -bs -i"
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="Email API"
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Seed Sample Data

```bash
php artisan db:seed --class=EmailApiSeeder
```

This will create:
- ✅ A sample domain: `menuvire.com`
- ✅ API key (displayed in console)
- ✅ Three email templates: `otp`, `welcome`, `invoice`

**Save the API key shown in the console!**

### 7. Test the API

#### Health Check

```bash
curl http://your-domain.com/api/health
```

#### Send Test Email

```bash
curl -X POST http://your-domain.com/api/email/send \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
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

## Common Commands

### View API Key

```bash
php artisan tinker
```

```php
App\Models\EmailDomain::first()->api_key;
```

### Create New Domain

```bash
php artisan tinker
```

```php
$domain = App\Models\EmailDomain::create([
    'domain' => 'yourdomain.com',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Your App Name',
    'mailer' => 'exim',
    'status' => 'active',
]);

echo "API Key: " . $domain->api_key;
```

### Check Email Logs

```bash
php artisan tinker
```

```php
App\Models\EmailLog::latest()->take(10)->get(['to_email', 'subject', 'status', 'sent_at']);
```

### View Statistics

```bash
curl -H "X-API-Key: YOUR_API_KEY" \
  http://your-domain.com/api/email/stats?period=today
```

## cPanel Deployment

### 1. Upload Files

Upload all files to your cPanel public_html directory (or subdirectory)

### 2. Set Document Root

Point your domain to the `public` folder:

- cPanel → Domains → Domain
- Set Document Root to: `/home/username/public_html/public`

### 3. Set Permissions

```bash
chmod -R 755 storage bootstrap/cache
```

### 4. Create Database

- cPanel → MySQL Databases
- Create database: `username_email_api`
- Create user and assign to database
- Update `.env` with credentials

### 5. Run Setup via SSH

```bash
cd /home/username/public_html
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan db:seed --class=EmailApiSeeder
```

### 6. .htaccess Configuration

Laravel should create this automatically in `/public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

## Troubleshooting

### Emails Not Sending?

1. Check mail logs:
   ```sql
   SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10;
   ```

2. Test sendmail:
   ```bash
   echo "Test" | sendmail -v your@email.com
   ```

3. Verify PHP mail function:
   ```bash
   php -r "mail('test@example.com', 'Test', 'Test message');"
   ```

### Permission Errors?

```bash
chmod -R 755 storage bootstrap/cache
chown -R username:username storage bootstrap/cache
```

### API Not Working?

1. Clear cache:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```

2. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Security Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Use HTTPS (SSL certificate)
- [ ] Keep API keys secure
- [ ] Set appropriate rate limits
- [ ] Regularly backup database
- [ ] Monitor email logs for abuse
- [ ] Update Laravel regularly

## Next Steps

1. **Import Postman Collection**: Use `postman_collection.json`
2. **Customize Templates**: Add your own email templates in database
3. **Configure SES** (optional): For high-volume sending
4. **Set up Queue**: For async email sending
5. **Monitor Usage**: Check statistics regularly

## Support

For issues or questions:
- Check `README.md` for full documentation
- Review `database/sample_data.sql` for examples
- Check Laravel logs: `storage/logs/laravel.log`

---

**🎉 You're ready to send emails!**
