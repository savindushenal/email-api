# Email Template API - Quick Reference

## Create Custom Template

```powershell
# Create JSON file with template definition
@{
    template_key = "your-template-key"
    category = "transactional"  # Options: authentication, notification, transactional, marketing, system
    description = "Brief description of template"
    subject = "Email Subject {{ $variable }}"
    blade_html = "<html>Your HTML with {{ $variables }}</html>"
    variables = @(
        @{
            name = "variable_name"
            type = "string"  # Options: string, number, boolean, date, url, email
            description = "What this variable is for"
            required = $true
        }
    )
    status = "active"
} | ConvertTo-Json -Depth 5 | Out-File template.json

# Create the template
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{'X-API-Key'='YOUR_API_KEY'; 'Content-Type'='application/json'} `
    -Body (Get-Content template.json -Raw)
```

## List Templates

```powershell
# All templates
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method GET `
    -Headers @{'X-API-Key'='YOUR_API_KEY'}

# Filter by category
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates?category=transactional' `
    -Method GET `
    -Headers @{'X-API-Key'='YOUR_API_KEY'}
```

## Get Single Template

```powershell
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates/template-key' `
    -Method GET `
    -Headers @{'X-API-Key'='YOUR_API_KEY'}
```

## Preview Template

```powershell
$body = @{
    data = @{
        variable1 = "value1"
        variable2 = "value2"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates/template-key/preview' `
    -Method POST `
    -Headers @{'X-API-Key'='YOUR_API_KEY'; 'Content-Type'='application/json'} `
    -Body $body
```

## Update Template

```powershell
$body = @{
    subject = "Updated Subject"
    blade_html = "<html>Updated content</html>"
    description = "Updated description"
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates/template-key' `
    -Method PUT `
    -Headers @{'X-API-Key'='YOUR_API_KEY'; 'Content-Type'='application/json'} `
    -Body $body
```

## Delete Template

```powershell
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates/template-key' `
    -Method DELETE `
    -Headers @{'X-API-Key'='YOUR_API_KEY'}
```

## Send Email with Custom Template

```powershell
$body = @{
    domain = "menuvire.com"
    to = "recipient@example.com"
    template = "your-template-key"
    data = @{
        variable1 = "value1"
        variable2 = "value2"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/send' `
    -Method POST `
    -Headers @{'X-API-Key'='YOUR_API_KEY'; 'Content-Type'='application/json'} `
    -Body $body
```

## Template Categories

- **authentication**: Login, signup, verification, password reset
- **notification**: Alerts, reminders, updates
- **transactional**: Orders, invoices, receipts, confirmations
- **marketing**: Newsletters, promotions, announcements
- **system**: Maintenance notices, error alerts, status updates

## Variable Types

- **string**: Text content
- **number**: Numeric values
- **boolean**: True/false values
- **date**: Date/datetime strings
- **url**: URLs/links
- **email**: Email addresses

## Blade Template Syntax Examples

### Variable Output
```html
{{ $variable_name }}  <!-- Escaped (safe) -->
{!! $html_content !!} <!-- Unescaped (use carefully) -->
{{ $name ?? 'Guest' }} <!-- With default value -->
```

### Conditionals
```html
@if($is_premium)
    <p>Premium content</p>
@elseif($is_trial)
    <p>Trial content</p>
@else
    <p>Free content</p>
@endif
```

### Loops
```html
@foreach($items as $item)
    <li>{{ $item }}</li>
@endforeach
```

### Check if Variable Exists
```html
@isset($variable)
    <p>{{ $variable }}</p>
@endisset
```

## Complete Working Example

### MenuVire API Keys
- **MenuVire**: `eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec`
- **FitVire**: `eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU`

### Example: Create Welcome Email Template

```powershell
$template = @{
    template_key = "user-welcome"
    category = "authentication"
    description = "Welcome email for new user signups"
    subject = "Welcome to {{ $platform_name }}, {{ $user_name }}!"
    blade_html = @"
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
        .button { display: inline-block; padding: 12px 30px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome!</h1>
        </div>
        <div style="padding: 30px 20px;">
            <h2>Hi {{ $user_name }},</h2>
            <p>Thank you for joining {{ $platform_name }}!</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="{{ $verification_link }}" class="button">Verify Email</a>
            </p>
        </div>
    </div>
</body>
</html>
"@
    variables = @(
        @{name='user_name'; type='string'; description='User full name'; required=$true},
        @{name='platform_name'; type='string'; description='Platform name'; required=$true},
        @{name='verification_link'; type='url'; description='Verification URL'; required=$true}
    )
    status = "active"
} | ConvertTo-Json -Depth 5 | Out-File welcome-template.json

# Create it
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{'X-API-Key'='eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'; 'Content-Type'='application/json'} `
    -Body (Get-Content welcome-template.json -Raw)

# Send email using it
$emailBody = @{
    domain = "menuvire.com"
    to = "newuser@example.com"
    template = "user-welcome"
    data = @{
        user_name = "John Doe"
        platform_name = "MenuVire"
        verification_link = "https://app.menuvire.com/verify/abc123"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/send' `
    -Method POST `
    -Headers @{'X-API-Key'='eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'; 'Content-Type'='application/json'} `
    -Body $emailBody
```

## Best Practices

1. **Always define variables** - Document expected data structure
2. **Use descriptive template keys** - Make them self-explanatory
3. **Test with preview first** - Verify rendering before sending
4. **Categorize properly** - Use appropriate categories for organization
5. **Keep HTML simple** - Complex CSS may not work in all email clients
6. **Use inline styles** - Better email client compatibility
7. **Provide descriptions** - Help other developers understand template purpose

## Troubleshooting

**Template validation fails?**
- Check Blade syntax is correct
- Ensure all variable placeholders use `{{ $variable }}` format
- Make sure HTML is well-formed

**Variables not showing in email?**
- Verify variable names match exactly (case-sensitive)
- Check data object contains all required variables
- Use preview endpoint to test

**Email not sending?**
- Verify domain matches your API key
- Check template status is "active"
- Ensure recipient email is valid
