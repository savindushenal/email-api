# Amazon SES Configuration Guide

## Overview

This guide explains how to configure Amazon Simple Email Service (SES) as an alternative to cPanel's Exim mailer for high-volume email sending.

## Why Use SES?

✅ **High Deliverability**: Better inbox placement rates  
✅ **Scalability**: Send millions of emails  
✅ **Cost-Effective**: $0.10 per 1,000 emails  
✅ **Analytics**: Detailed sending statistics  
✅ **Reputation Management**: Dedicated IP options  

## Prerequisites

1. AWS Account
2. SES Access (request production access if sending to non-verified emails)
3. Domain ownership verification

## Step 1: AWS SES Setup

### 1.1 Create AWS Account
- Go to https://aws.amazon.com
- Sign up for an account
- Enable billing

### 1.2 Access SES Console
- Navigate to: https://console.aws.amazon.com/ses
- Select your preferred region (e.g., us-east-1)

### 1.3 Verify Domain

1. **Add Domain:**
   - SES Console → Verified identities → Create identity
   - Select "Domain"
   - Enter your domain: `menuvire.com`
   - Enable "DKIM signatures"

2. **Configure DNS Records:**
   
   Add these records to your domain's DNS (values will be provided by AWS):

   ```dns
   # DKIM Records (3 records)
   abc123._domainkey.menuvire.com CNAME abc123.dkim.amazonses.com
   def456._domainkey.menuvire.com CNAME def456.dkim.amazonses.com
   ghi789._domainkey.menuvire.com CNAME ghi789.dkim.amazonses.com
   
   # Verification Record
   _amazonses.menuvire.com TXT "verification-code-here"
   ```

3. **Wait for Verification:**
   - Usually takes 10-60 minutes
   - Check status in SES Console

### 1.4 Request Production Access

**If in Sandbox Mode:**

1. Go to: SES Console → Account dashboard
2. Click "Request production access"
3. Fill out the form:
   - **Use case**: Transactional emails
   - **Website**: Your website URL
   - **Description**: "Multi-tenant email API for OTP, welcome emails, and invoices"
   - **Process**: Opt-out process description
   - **Compliance**: Agree to policies

**Approval usually takes 24 hours**

### 1.5 Create IAM User for API Access

1. **Go to IAM Console:**
   - https://console.aws.amazon.com/iam

2. **Create Policy:**
   - IAM → Policies → Create policy
   - JSON tab:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "ses:SendEmail",
           "ses:SendRawEmail"
         ],
         "Resource": "*"
       }
     ]
   }
   ```
   - Name: `EmailAPISESPolicy`

3. **Create User:**
   - IAM → Users → Add user
   - Username: `email-api-ses-user`
   - Access type: Programmatic access
   - Attach policy: `EmailAPISESPolicy`
   - **Save Access Key ID and Secret Access Key**

## Step 2: Laravel Configuration

### 2.1 Install AWS SDK

```bash
composer require aws/aws-sdk-php
```

### 2.2 Environment Variables

Add to `.env`:

```env
# AWS SES Configuration
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=us-east-1

# Mail Configuration
MAIL_MAILER=ses
```

### 2.3 Configure `config/services.php`

Ensure this exists:

```php
'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
],
```

## Step 3: Database Configuration

### 3.1 Update Domain to Use SES

**Using SQL:**

```sql
UPDATE email_domains 
SET 
    mailer = 'ses',
    ses_key = 'AKIAIOSFODNN7EXAMPLE',
    ses_secret = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    ses_region = 'us-east-1'
WHERE domain = 'menuvire.com';
```

**Using Tinker:**

```bash
php artisan tinker
```

```php
$domain = App\Models\EmailDomain::where('domain', 'menuvire.com')->first();
$domain->mailer = 'ses';
$domain->ses_key = 'AKIAIOSFODNN7EXAMPLE';
$domain->ses_secret = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
$domain->ses_region = 'us-east-1';
$domain->save();
```

### 3.2 Create New Domain with SES

```php
$domain = App\Models\EmailDomain::create([
    'domain' => 'example.com',
    'from_email' => 'noreply@example.com',
    'from_name' => 'Example App',
    'mailer' => 'ses',
    'status' => 'active',
    'ses_key' => 'AKIAIOSFODNN7EXAMPLE',
    'ses_secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'ses_region' => 'us-east-1',
    'daily_limit' => 50000,
    'hourly_limit' => 5000,
]);

echo "API Key: " . $domain->api_key;
```

## Step 4: Testing

### 4.1 Test SES Connection

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Mail;

Mail::raw('Test email from SES', function ($message) {
    $message->to('your-email@example.com')
            ->subject('SES Test');
});
```

### 4.2 Test via API

```bash
curl -X POST http://your-domain.com/api/email/send \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
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

### 4.3 Verify Email Sent

Check the response:
```json
{
  "success": true,
  "data": {
    "mailer": "ses"
  }
}
```

## Step 5: DNS Records for Better Deliverability

### SPF Record

Add to your domain's DNS:

```dns
Type: TXT
Name: @
Value: v=spf1 include:amazonses.com ~all
```

### DMARC Record

```dns
Type: TXT
Name: _dmarc
Value: v=DMARC1; p=quarantine; rua=mailto:postmaster@menuvire.com
```

### Return-Path (Optional)

Configure in SES Console → Verified identities → Your domain → Custom MAIL FROM

## Step 6: Monitoring

### 6.1 SES Console Monitoring

- **Sending Statistics**: SES Console → Account dashboard
- **Reputation Dashboard**: Monitor bounce/complaint rates
- **Sending Limits**: Check daily sending quota

### 6.2 CloudWatch Metrics

Available metrics:
- `Send`: Number of emails sent
- `Delivery`: Successful deliveries
- `Bounce`: Bounced emails
- `Complaint`: Spam complaints
- `Reject`: Rejected emails

### 6.3 Email API Logs

Check your email logs:

```sql
SELECT 
    mailer_used,
    status,
    COUNT(*) as count
FROM email_logs
WHERE mailer_used = 'ses'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY mailer_used, status;
```

## SES Limits

### Sandbox Mode
- ✅ Send to verified emails only
- ✅ 200 emails per 24 hours
- ✅ 1 email per second

### Production Mode
- ✅ Send to any email
- ✅ 50,000+ emails per 24 hours (increases over time)
- ✅ 14+ emails per second (increases over time)

## Cost Estimation

### Pricing (as of 2023)
- **First 62,000 emails/month**: $0 (with EC2)
- **Additional emails**: $0.10 per 1,000 emails
- **Data transfer**: $0.12 per GB

### Examples
- **10,000 emails/month**: ~$1.00
- **100,000 emails/month**: ~$10.00
- **1,000,000 emails/month**: ~$100.00

## Troubleshooting

### Email Not Sending

1. **Check SES Status:**
   ```bash
   aws ses get-send-quota --region us-east-1
   ```

2. **Verify Domain:**
   ```bash
   aws ses list-identities --region us-east-1
   ```

3. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Access Denied Error

- Verify IAM permissions
- Check AWS credentials in database
- Ensure region is correct

### Emails Going to Spam

1. Configure SPF, DKIM, and DMARC
2. Warm up your IP (gradually increase volume)
3. Monitor bounce/complaint rates
4. Use verified domain in From address

## Best Practices

### 1. Bounce Handling

Set up SNS notifications for bounces:

1. SES Console → Verified identities → Your domain
2. Notifications tab
3. Configure SNS topics for bounces/complaints

### 2. Email List Hygiene

- Remove hard bounces immediately
- Monitor complaint rates (keep < 0.1%)
- Implement unsubscribe mechanism

### 3. Sending Patterns

- Gradually increase sending volume
- Avoid sudden spikes
- Maintain consistent sending patterns

### 4. Content Best Practices

- Clear subject lines
- Relevant content
- Working unsubscribe links
- Valid reply-to addresses
- No spam trigger words

## Multi-Domain Setup

You can configure different mailers per domain:

```php
// Domain 1: Uses Exim (cPanel)
App\Models\EmailDomain::create([
    'domain' => 'small-site.com',
    'mailer' => 'exim',
    'daily_limit' => 500,
]);

// Domain 2: Uses SES (High volume)
App\Models\EmailDomain::create([
    'domain' => 'big-site.com',
    'mailer' => 'ses',
    'ses_key' => 'KEY_HERE',
    'ses_secret' => 'SECRET_HERE',
    'ses_region' => 'us-east-1',
    'daily_limit' => 50000,
]);
```

## Migration from Exim to SES

To migrate an existing domain:

```sql
-- Backup current configuration
SELECT * FROM email_domains WHERE domain = 'menuvire.com';

-- Update to SES
UPDATE email_domains 
SET 
    mailer = 'ses',
    ses_key = 'YOUR_KEY',
    ses_secret = 'YOUR_SECRET',
    ses_region = 'us-east-1',
    daily_limit = 50000,
    hourly_limit = 5000
WHERE domain = 'menuvire.com';
```

## Support Resources

- **AWS SES Documentation**: https://docs.aws.amazon.com/ses/
- **Laravel Mail Documentation**: https://laravel.com/docs/mail
- **AWS Support**: https://console.aws.amazon.com/support/
- **Email API Logs**: `storage/logs/laravel.log`

## Security Considerations

1. **Never commit AWS credentials** to version control
2. **Rotate credentials** regularly
3. **Use IAM roles** instead of access keys when possible
4. **Monitor CloudTrail** for API usage
5. **Enable MFA** on AWS account
6. **Encrypt sensitive data** in database

---

**Note:** Always test in sandbox mode before going to production. Monitor your sending reputation closely in the first few weeks.
