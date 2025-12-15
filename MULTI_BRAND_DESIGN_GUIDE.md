# Multi-Brand Design Management Guide

## How Brand-Specific Templates Work

Each domain in your email API has **completely separate templates** stored in the database. This means:

✅ **MenuVire** uses API Key: `eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec`
✅ **FitVire** uses API Key: `eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU`

Each domain can have:
- Different HTML designs
- Different color schemes
- Different branding elements
- Different template names (or same names with different designs)

---

## Brand Design Separation in Database

```
email_templates table:
┌────┬───────────┬──────────────────┬────────────┬─────────────────┐
│ ID │ Domain ID │ Template Key     │ Category   │ Design          │
├────┼───────────┼──────────────────┼────────────┼─────────────────┤
│ 12 │ 7 (Menu)  │ menuvire-branded │ auth       │ 🍽️ Orange/Red   │
│ 13 │ 8 (Fit)   │ fitvire-branded  │ auth       │ ⚡ Blue/Purple  │
│ 14 │ 7 (Menu)  │ invoice          │ transact   │ Restaurant      │
│ 15 │ 8 (Fit)   │ subscription     │ transact   │ Fitness         │
└────┴───────────┴──────────────────┴────────────┴─────────────────┘
```

**Key Point**: Templates are isolated by `domain_id` - MenuVire can't access FitVire templates and vice versa.

---

## Managing Different Brand Designs

### Option 1: Completely Different Template Keys
Use unique names per brand:

**MenuVire:**
- `menuvire-welcome`
- `menuvire-invoice`
- `restaurant-reservation-confirm`

**FitVire:**
- `fitvire-welcome`  
- `subscription-payment`
- `workout-reminder`

### Option 2: Same Template Key, Different Design
Both brands can have `welcome` template with different designs:

```powershell
# MenuVire's "welcome" - Restaurant theme
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{'X-API-Key'='MenuVire_KEY'} `
    -Body @{
        template_key = "welcome"
        blade_html = "<html>...RESTAURANT DESIGN...</html>"
    }

# FitVire's "welcome" - Fitness theme  
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{'X-API-Key'='FitVire_KEY'} `
    -Body @{
        template_key = "welcome"
        blade_html = "<html>...FITNESS DESIGN...</html>"
    }
```

---

## Brand Design System Examples

### MenuVire Brand System 🍽️

**Color Palette:**
```css
Primary: #FF6B6B (Coral Red)
Secondary: #FF8E53 (Warm Orange)
Background: #FFF4E6 (Cream)
Text: #333333
```

**Design Elements:**
- Restaurant/food emojis (🍽️🍴🥗)
- Rounded buttons with warm colors
- Grid layouts for menu items
- QR code imagery

**Template Example:**
```html
<div style="background: linear-gradient(135deg, #FF6B6B, #FF8E53); padding: 40px;">
    <h1 style="color: #fff">🍽️ MenuVire</h1>
    <p style="color: #fff">Restaurant Menu Solutions</p>
</div>
```

### FitVire Brand System ⚡

**Color Palette:**
```css
Primary: #00D4FF (Cyan)
Secondary: #7B2FF7 (Purple)
Background: #0A0E27 (Dark Blue)
Accent: #0099FF (Blue)
```

**Design Elements:**
- Fitness/energy emojis (⚡💪🔥)
- Bold, uppercase text
- Gradient buttons
- Stats/numbers prominently displayed
- Modern, athletic aesthetic

**Template Example:**
```html
<div style="background: linear-gradient(135deg, #00D4FF, #7B2FF7); padding: 50px;">
    <h1 style="color: #fff; font-weight: 900; letter-spacing: 2px;">⚡ FITVIRE ⚡</h1>
    <p style="color: #fff; font-weight: 600;">TRANSFORM YOUR BODY</p>
</div>
```

---

## Creating Brand-Specific Templates

### Step 1: Define Brand Variables
Create a JSON template file for each brand:

**menuvire-template.json:**
```json
{
  "template_key": "order-confirmation",
  "category": "transactional",
  "subject": "Your MenuVire Order #{{ $order_id }}",
  "blade_html": "<html>...RESTAURANT THEMED DESIGN...</html>",
  "variables": [...]
}
```

**fitvire-template.json:**
```json
{
  "template_key": "subscription-renewal",
  "category": "transactional",
  "subject": "Your FitVire Membership Renewal",
  "blade_html": "<html>...FITNESS THEMED DESIGN...</html>",
  "variables": [...]
}
```

### Step 2: Create Templates via API

```powershell
# Create MenuVire template
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{
        'X-API-Key' = 'eak_fu5NuRBJQEoXmdGHfjley03jMBCmw94IU66t6Uec'
        'Content-Type' = 'application/json'
    } `
    -Body (Get-Content menuvire-template.json -Raw)

# Create FitVire template
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{
        'X-API-Key' = 'eak_JDxGOSFFVLssRviunHUM1iuKUuL11627vmX4b4GU'
        'Content-Type' = 'application/json'
    } `
    -Body (Get-Content fitvire-template.json -Raw)
```

### Step 3: Send Branded Emails

```powershell
# MenuVire sends restaurant-themed email
$menuBody = @{
    domain = "menuvire.com"
    to = "customer@example.com"
    template = "order-confirmation"
    data = @{
        order_id = "MV-2025-123"
        restaurant_name = "Bella Italia"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/send' `
    -Method POST `
    -Headers @{'X-API-Key'='MenuVire_KEY'} `
    -Body $menuBody

# FitVire sends fitness-themed email
$fitBody = @{
    domain = "fitvire.com"
    to = "member@example.com"
    template = "subscription-renewal"
    data = @{
        member_name = "John"
        plan = "Premium"
    }
} | ConvertTo-Json

Invoke-RestMethod -Uri 'http://localhost:8001/api/email/send' `
    -Method POST `
    -Headers @{'X-API-Key'='FitVire_KEY'} `
    -Body $fitBody
```

---

## Brand Management Best Practices

### 1. **Use Brand Prefixes** (Recommended)
```
menuvire-welcome
menuvire-order-confirm
menuvire-reservation

fitvire-welcome
fitvire-workout-reminder
fitvire-achievement
```

**Pros:**
- Clear ownership
- No confusion
- Easy to filter

### 2. **Shared Component Library**
Create reusable template components:

```html
<!-- MenuVire Header Component -->
<div class="menuvire-header">
    <img src="https://cdn.menuvire.com/logo.png" alt="MenuVire">
</div>

<!-- FitVire Header Component -->
<div class="fitvire-header">
    <img src="https://cdn.fitvire.com/logo.png" alt="FitVire">
</div>
```

### 3. **Category Organization**
Organize by use case:

```
MenuVire:
├── authentication (welcome, verify, reset)
├── transactional (order, reservation, receipt)
├── marketing (promotions, menu-updates)
└── notification (table-ready, review-request)

FitVire:
├── authentication (welcome, verify, reset)
├── transactional (subscription, payment)
├── marketing (challenges, new-workouts)
└── notification (workout-reminder, achievement)
```

### 4. **Template Versioning**
Use descriptive keys for A/B testing:

```
menuvire-welcome-v1
menuvire-welcome-v2-simplified
menuvire-welcome-v3-video

fitvire-welcome-v1
fitvire-welcome-v2-motivational
```

---

## Viewing Templates by Brand

### List All Templates for a Brand
```powershell
# MenuVire templates
$menuvireTemplates = Invoke-RestMethod `
    -Uri 'http://localhost:8001/api/email/templates' `
    -Headers @{'X-API-Key'='MenuVire_KEY'}

$menuvireTemplates.data.templates | Format-Table

# FitVire templates
$fitvireTemplates = Invoke-RestMethod `
    -Uri 'http://localhost:8001/api/email/templates' `
    -Headers @{'X-API-Key'='FitVire_KEY'}

$fitvireTemplates.data.templates | Format-Table
```

### Filter by Category
```powershell
# MenuVire transactional emails
Invoke-RestMethod `
    -Uri 'http://localhost:8001/api/email/templates?category=transactional' `
    -Headers @{'X-API-Key'='MenuVire_KEY'}

# FitVire authentication emails  
Invoke-RestMethod `
    -Uri 'http://localhost:8001/api/email/templates?category=authentication' `
    -Headers @{'X-API-Key'='FitVire_KEY'}
```

---

## Database Query Examples

### View All Templates by Brand
```sql
-- MenuVire templates (domain_id = 7)
SELECT template_key, category, subject 
FROM email_templates 
WHERE domain_id = 7;

-- FitVire templates (domain_id = 8)
SELECT template_key, category, subject 
FROM email_templates 
WHERE domain_id = 8;
```

### Compare Same Template Across Brands
```sql
SELECT 
    d.domain,
    t.template_key,
    t.subject,
    t.category
FROM email_templates t
JOIN email_domains d ON t.domain_id = d.id
WHERE t.template_key = 'welcome'
ORDER BY d.domain;
```

---

## Security & Isolation

✅ **API Key Isolation**: Each brand's API key only accesses their templates
✅ **Database Isolation**: Templates filtered by `domain_id`
✅ **No Cross-Contamination**: MenuVire can never accidentally use FitVire templates
✅ **Independent Updates**: Update one brand without affecting others

---

## Adding New Brands

To add a new brand (e.g., "ShopVire"):

1. **Add domain to database:**
```sql
INSERT INTO email_domains (domain, from_email, from_name, ...) 
VALUES ('shopvire.com', 'no-reply@shopvire.com', 'ShopVire', ...);
```

2. **Get the new API key** from seeder or generate manually

3. **Create brand templates:**
```powershell
Invoke-RestMethod -Uri 'http://localhost:8001/api/email/templates' `
    -Method POST `
    -Headers @{'X-API-Key'='ShopVire_API_KEY'} `
    -Body @{
        template_key = "shopvire-order-shipped"
        category = "transactional"
        subject = "Your ShopVire Order Has Shipped!"
        blade_html = "<html>...ECOMMERCE DESIGN...</html>"
    }
```

---

## Summary

🎨 **Each brand = Separate design system**
🔐 **API keys ensure isolation**
📁 **Templates stored per domain in database**
🎯 **Same template key can have different designs per brand**
⚡ **Update one brand without affecting others**
🚀 **Scalable to unlimited brands**

Your email API is a **true multi-tenant system** where each brand has complete design independence! 🎉
