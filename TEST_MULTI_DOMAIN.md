# Test Email API - Multi-Domain Setup

## 🎉 Domains Created

### 1. MenuVire Platform
- **Domain:** `menuvire.com`
- **From Email:** `no-reply@menuvire.com`  
- **SMTP:** `uniform.de.hostns.io:465` (SSL)
- **API Key:** `eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec`

### 2. FitVire Platform  
- **Domain:** `fitvire.com`
- **From Email:** `no-reply@fitvire.com`
- **SMTP:** `uniform.de.hostns.io:465` (SSL) 
- **API Key:** `eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU`

---

## 📧 Test Sending Emails

### Test 1: Send Welcome Email from MenuVire

```powershell
$headers = @{
    'X-API-Key' = 'eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'
    'Content-Type' = 'application/json'
}

$body = @{
    to = 'savindu@menuvire.com'
    subject = 'Welcome to MenuVire!'
    template = 'welcome'
    data = @{
        user_name = 'Savindu'
        platform_name = 'MenuVire'
        verification_link = 'https://app.menuvire.com/verify?token=abc123'
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/send-email' -Method POST -Headers $headers -Body $body
```

### Test 2: Send Password Reset from FitVire

```powershell
$headers = @{
    'X-API-Key' = 'eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU'
    'Content-Type' = 'application/json'
}

$body = @{
    to = 'user@example.com'
    subject = 'Reset Your FitVire Password'
    template = 'password-reset'
    data = @{
        user_name = 'John Doe'
        platform_name = 'FitVire'
        reset_link = 'https://fitvire.com/reset?token=xyz789'
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/send-email' -Method POST -Headers $headers -Body $body
```

### Test 3: Send OTP from MenuVire

```powershell
$headers = @{
    'X-API-Key' = 'eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'
    'Content-Type' = 'application/json'
}

$body = @{
    to = 'savindu@menuvire.com'
    subject = 'Your MenuVire Verification Code'
    template = 'otp'
    data = @{
        otp_code = '123456'
        validity = 10
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8000/api/send-email' -Method POST -Headers $headers -Body $body
```

---

## 🔧 Using from Your Application (Laravel Example)

### MenuVire Backend

```php
use Illuminate\Support\Facades\Http;

// Send welcome email
$response = Http::withHeaders([
    'X-API-Key' => 'eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec',
])->post('https://email-api.menuvire.com/api/send-email', [
    'to' => $user->email,
    'template' => 'welcome',
    'data' => [
        'user_name' => $user->name,
        'platform_name' => 'MenuVire',
        'verification_link' => route('verify.email', ['token' => $token]),
    ]
]);
```

### FitVire Backend

```php
// Send password reset
$response = Http::withHeaders([
    'X-API-Key' => 'eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU',
])->post('https://email-api.menuvire.com/api/send-email', [
    'to' => $user->email,
    'template' => 'password-reset',
    'data' => [
        'user_name' => $user->name,
        'platform_name' => 'FitVire',
        'reset_link' => route('password.reset', ['token' => $token]),
    ]
]);
```

---

## ✅ Available Templates

Each domain has 3 templates:

### 1. `welcome` - User Onboarding
**Variables:**
- `user_name` - User's name
- `platform_name` - Platform name
- `verification_link` (optional) - Email verification URL

### 2. `password-reset` - Password Reset
**Variables:**
- `user_name` - User's name  
- `platform_name` - Platform name
- `reset_link` - Password reset URL

### 3. `otp` - One-Time Password
**Variables:**
- `otp_code` - The OTP code
- `validity` (optional) - Expiry time in minutes (default: 10)

---

## 🎯 How It Works

1. **Your Platform** (MenuVire/FitVire) makes API request
2. **Email API** receives request with API key
3. **Validates** API key and identifies domain
4. **Loads** domain-specific SMTP configuration:
   - MenuVire → `no-reply@menuvire.com` (uniform.de.hostns.io)
   - FitVire → `no-reply@fitvire.com` (uniform.de.hostns.io)
5. **Renders** Blade template with your data
6. **Sends** email using domain's SMTP server
7. **Logs** email in database for tracking

---

## 📊 Check Sending Stats

```powershell
# MenuVire stats
$headers = @{'X-API-Key' = 'eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'}
Invoke-RestMethod -Uri 'http://localhost:8000/api/stats' -Headers $headers

# FitVire stats
$headers = @{'X-API-Key' = 'eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU'}
Invoke-RestMethod -Uri 'http://localhost:8000/api/stats' -Headers $headers
```

---

## 🔐 Security Notes

- Each platform has its own API key
- API keys are hashed (SHA-256) in database
- SMTP passwords stored in `mail_config` JSON
- Rate limited to 100 emails/hour per domain
- All emails logged for auditing

---

## 🚀 Next Steps

1. **Update FitVire SMTP password** in database:
   ```sql
   UPDATE email_domains 
   SET mail_config = JSON_SET(mail_config, '$.password', 'actual_password')
   WHERE domain = 'fitvire.com';
   ```

2. **Deploy to production** using the deployment guide

3. **Update your applications** to use the Email API instead of direct SMTP

4. **Monitor logs** in `email_logs` table

5. **Create more templates** as needed via API or database

---

**Your centralized email microservice is ready!** 🎉

All platforms can now send emails through one API with their own domain-specific configurations!
