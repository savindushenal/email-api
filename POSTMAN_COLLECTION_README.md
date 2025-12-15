# Email API - Postman Collection

## 📦 Import Instructions

1. Open Postman
2. Click **Import** button (top left)
3. Select **File** tab
4. Choose `Email-API-Postman-Collection.json`
5. Click **Import**

## 🔧 Environment Setup

The collection uses these variables (already configured):

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `http://localhost:8001` | API base URL |
| `menuvire_api_key` | `eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec` | MenuVire API Key |
| `fitvire_api_key` | `eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU` | FitVire API Key |

**To change base URL for production:**
1. Go to Collection **Variables** tab
2. Update `base_url` to your production domain
3. Save changes

## 📑 Collection Structure

### 1️⃣ Health Check
- **Health Check** - Verify API is running

### 2️⃣ Email Sending
- **Send Email - MenuVire Welcome** - Send welcome email
- **Send Email - FitVire Password Reset** - Send password reset
- **Send Email - MenuVire Invoice** - Send custom invoice template
- **Send Email - OTP** - Send OTP verification code

### 3️⃣ Template Management
- **List All Templates - MenuVire** - Get all MenuVire templates
- **List All Templates - FitVire** - Get all FitVire templates
- **List Templates by Category** - Filter by category
- **Get Single Template** - View template details
- **Create Template - Order Confirmation** - Create new template (MenuVire)
- **Create Template - Workout Reminder** - Create new template (FitVire)
- **Update Template** - Modify existing template
- **Preview Template** - Test template rendering
- **Delete Template** - Remove template

### 4️⃣ Statistics
- **Get Email Stats - MenuVire** - MenuVire email statistics
- **Get Email Stats - FitVire** - FitVire email statistics

## 🚀 Quick Start Guide

### Step 1: Test API Health
Run: **Health Check**
Expected: `200 OK` with `{"status": "healthy"}`

### Step 2: List Existing Templates
Run: **List All Templates - MenuVire**
Expected: List of templates including `welcome`, `password-reset`, `otp`, `invoice-notification`

### Step 3: Send a Test Email
Run: **Send Email - MenuVire Welcome**
- Change `to` email to your test email
- Expected: `200 OK` with `{"success": true}`

### Step 4: Create Custom Template
Run: **Create Template - Order Confirmation**
Expected: `201 Created` with template details

### Step 5: Preview Template
Run: **Preview Template**
Expected: Rendered HTML and subject with sample data

### Step 6: Send Email with Custom Template
Create new request or modify existing:
```json
{
    "domain": "menuvire.com",
    "to": "your-email@example.com",
    "template": "order-confirmation",
    "data": {
        "customer_name": "Your Name",
        "order_number": "TEST-001",
        "restaurant_name": "Test Restaurant",
        "total_amount": 50.00,
        "delivery_time": "30 minutes",
        "tracking_link": "https://example.com/track"
    }
}
```

## 🎯 Testing Scenarios

### Scenario 1: Multi-Brand Email Test
1. Send email with MenuVire key → Restaurant-themed template
2. Send email with FitVire key → Fitness-themed template
3. Compare designs in your inbox

### Scenario 2: Template CRUD Flow
1. **Create** - Create new template
2. **Read** - List and view template
3. **Update** - Modify template subject
4. **Preview** - Test rendering
5. **Delete** - Remove template

### Scenario 3: Category Organization
1. Create templates in different categories:
   - `authentication` - Welcome, verify, reset
   - `transactional` - Invoice, order, receipt
   - `notification` - Reminder, alert, update
2. List templates filtered by category
3. Verify organization

## 🔐 Authentication

All endpoints (except health check) require `X-API-Key` header:

```
X-API-Key: eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec
```

**Important:** 
- MenuVire key only accesses MenuVire templates
- FitVire key only accesses FitVire templates
- API keys are SHA-256 hashed in database

## 📊 Response Codes

| Code | Status | Description |
|------|--------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Template created successfully |
| 401 | Unauthorized | Invalid or missing API key |
| 403 | Forbidden | Domain mismatch |
| 404 | Not Found | Template not found |
| 422 | Unprocessable | Validation error |
| 500 | Server Error | Internal server error |

## 🧪 Example Responses

### Success Response
```json
{
    "success": true,
    "message": "Email sent successfully",
    "data": {
        "log_id": 42,
        "message_id": "eak_694047cfb95..."
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "template": ["The template field is required."]
    }
}
```

### Template List Response
```json
{
    "success": true,
    "data": {
        "domain": "menuvire.com",
        "templates": [
            {
                "id": 12,
                "template_key": "invoice-notification",
                "category": "transactional",
                "description": "Invoice notification with payment details",
                "subject": "Invoice #{{ $invoice_number }}",
                "status": "active"
            }
        ],
        "count": 4
    }
}
```

## 📝 Variable Types in Templates

When creating templates, use these variable types:

- `string` - Text content (names, descriptions)
- `number` - Numeric values (amounts, quantities)
- `boolean` - True/false flags
- `date` - Date/datetime strings
- `url` - Links/URLs
- `email` - Email addresses

## 🎨 Brand-Specific Templates

### MenuVire (Restaurant Platform)
- **Theme**: Warm colors (orange/red)
- **Style**: Professional, appetizing
- **Emojis**: 🍽️🍴🥗
- **Use Cases**: Reservations, orders, menus

### FitVire (Fitness Platform)
- **Theme**: Bold gradients (cyan/purple)
- **Style**: Energetic, motivational
- **Emojis**: ⚡💪🔥
- **Use Cases**: Workouts, subscriptions, challenges

## 🐛 Troubleshooting

### "401 Unauthorized" Error
- Check API key is correct
- Verify `X-API-Key` header is set
- Ensure no extra spaces in key

### "403 Domain Mismatch" Error
- Verify `domain` in body matches API key's domain
- MenuVire key → `menuvire.com`
- FitVire key → `fitvire.com`

### "422 Validation Failed" Error
- Check all required fields are present
- Verify data types match variable definitions
- Ensure Blade syntax is valid

### "500 Server Error"
- Check Laravel server logs
- Verify database connection
- Ensure SMTP configuration is correct

## 📚 Additional Resources

- **Template Format Guide**: `TEMPLATE_FORMAT_GUIDE.md`
- **Quick Reference**: `TEMPLATE_API_QUICK_REFERENCE.md`
- **Multi-Brand Guide**: `MULTI_BRAND_DESIGN_GUIDE.md`

## 💡 Pro Tips

1. **Use Preview First** - Always preview templates before sending
2. **Test with Multiple Clients** - Check rendering in Gmail, Outlook, Apple Mail
3. **Version Your Templates** - Use descriptive keys like `welcome-v2`
4. **Document Variables** - Add clear descriptions for all variables
5. **Categorize Properly** - Use consistent categories across domains
6. **Keep HTML Simple** - Complex CSS may not work in all email clients

## 🤝 Support

For issues or questions:
- Check documentation in project root
- Review Laravel logs: `storage/logs/laravel.log`
- Inspect database: `email_templates` table

---

**Happy Testing! 🚀**
