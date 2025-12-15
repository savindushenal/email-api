# System Architecture

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT APPLICATIONS                      │
│  (Website, Mobile App, Internal Tools, External Services)       │
└───────────────────┬─────────────────────────────────────────────┘
                    │
                    │ HTTPS Requests
                    │ X-API-Key Header
                    │
┌───────────────────▼─────────────────────────────────────────────┐
│                       EMAIL API (Laravel)                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │                    API Layer                            │    │
│  │  - POST /api/email/send                                 │    │
│  │  - GET  /api/email/stats                                │    │
│  │  - GET  /api/health                                     │    │
│  └──────────────────────┬──────────────────────────────────┘    │
│                         │                                        │
│  ┌──────────────────────▼──────────────────────────────────┐    │
│  │              Middleware Layer                            │    │
│  │  - ApiKeyAuthentication                                  │    │
│  │  - Throttle (Rate Limiting)                             │    │
│  └──────────────────────┬──────────────────────────────────┘    │
│                         │                                        │
│  ┌──────────────────────▼──────────────────────────────────┐    │
│  │              Controller Layer                            │    │
│  │  - EmailController                                       │    │
│  │    * send()                                              │    │
│  │    * stats()                                             │    │
│  │    * health()                                            │    │
│  └──────────────────────┬──────────────────────────────────┘    │
│                         │                                        │
│  ┌──────────────────────▼──────────────────────────────────┐    │
│  │              Service Layer                               │    │
│  │  - EmailService                                          │    │
│  │    * Validate domain                                     │    │
│  │    * Check rate limits                                   │    │
│  │    * Load template                                       │    │
│  │    * Render Blade template                               │    │
│  │    * Configure mail transport                            │    │
│  │    * Send email                                          │    │
│  │    * Log result                                          │    │
│  └──────────────────────┬──────────────────────────────────┘    │
│                         │                                        │
│         ┌───────────────┴───────────────┐                       │
│         │                               │                       │
│  ┌──────▼──────┐                 ┌──────▼──────┐               │
│  │   Exim/     │                 │  Amazon SES │               │
│  │  Sendmail   │                 │   Driver    │               │
│  │  (cPanel)   │                 │             │               │
│  └──────┬──────┘                 └──────┬──────┘               │
│         │                               │                       │
└─────────┼───────────────────────────────┼───────────────────────┘
          │                               │
          │                               │
┌─────────▼─────────────────────────────▼─────────────────────────┐
│                      SMTP Servers                                │
│  - cPanel Exim (Local)                                          │
│  - Amazon SES (Cloud)                                           │
└───────────────────────┬──────────────────────────────────────────┘
                        │
                        │ Email Delivery
                        │
┌───────────────────────▼──────────────────────────────────────────┐
│                   RECIPIENT EMAIL SERVERS                         │
│  (Gmail, Outlook, Yahoo, Custom SMTP, etc.)                      │
└──────────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
┌──────────────┐
│   Client     │
│ Application  │
└──────┬───────┘
       │
       │ 1. POST /api/email/send
       │    Headers: X-API-Key
       │    Body: {domain, template, to, data}
       │
       ▼
┌──────────────────────┐
│ ApiKeyAuthentication │
│     Middleware       │
└──────┬───────────────┘
       │
       │ 2. Validate API Key
       │    Lookup EmailDomain by api_key
       │    Check status = 'active'
       │
       ▼
┌──────────────────────┐
│  EmailController     │
│     send()           │
└──────┬───────────────┘
       │
       │ 3. Validate Request
       │    - domain (required, string)
       │    - template (required, string)
       │    - to (required, email)
       │    - data (required, array)
       │
       │ 4. Verify domain matches API key
       │
       ▼
┌──────────────────────┐
│   EmailService       │
│     send()           │
└──────┬───────────────┘
       │
       │ 5. Domain validation
       ├────────────────────────────┐
       │                            │
       ▼                            ▼
   ┌───────┐                  ┌──────────┐
   │ Check │                  │  Check   │
   │Domain │                  │  Rate    │
   │Active │                  │  Limits  │
   └───┬───┘                  └────┬─────┘
       │                           │
       │◄──────────────────────────┘
       │
       │ 6. Load email_templates
       │    WHERE domain_id = ? 
       │    AND template_key = ?
       │    AND status = 'active'
       │
       ▼
┌──────────────────────┐
│   Blade Renderer     │
└──────┬───────────────┘
       │
       │ 7. Render HTML
       │    Blade::render($template, $data)
       │
       │ 8. Render Subject
       │    Blade::render($subject, $data)
       │
       ▼
┌──────────────────────┐
│   Create EmailLog    │
│   status = 'queued'  │
└──────┬───────────────┘
       │
       │ 9. Configure Mail Transport
       │    IF domain.mailer = 'ses'
       │       → Configure SES
       │    ELSE
       │       → Configure Sendmail
       │
       ▼
┌──────────────────────┐
│ DynamicTemplateMail  │
│   (Mailable)         │
└──────┬───────────────┘
       │
       │ 10. Send email
       │     Mail::to($recipient)->send()
       │
       ├────────────┬──────────────┐
       │            │              │
       ▼            ▼              ▼
   ┌────────┐  ┌───────┐    ┌──────────┐
   │  Exim  │  │  SES  │    │  Failed  │
   │Success │  │Success│    │          │
   └───┬────┘  └───┬───┘    └────┬─────┘
       │           │             │
       │           │             │
       │ 11. Update EmailLog    │
       │     status = 'sent'     │
       │     sent_at = NOW()     │
       │◄────────────────────────┘
       │     OR                   
       │     status = 'failed'   
       │     error_message = ?   
       │
       ▼
┌──────────────────────┐
│  Return Response     │
│  {                   │
│    success: true,    │
│    data: {...}       │
│  }                   │
└──────────────────────┘
```

## Database Schema Relationships

```
┌─────────────────────────────────────────────────┐
│              email_domains                      │
├─────────────────────────────────────────────────┤
│ id (PK)                                         │
│ domain (UNIQUE)                                 │
│ from_email                                      │
│ from_name                                       │
│ mailer (exim/ses)                               │
│ status (active/inactive/suspended)              │
│ api_key (UNIQUE, INDEXED)                       │
│ ses_key, ses_secret, ses_region (nullable)      │
│ daily_limit, hourly_limit                       │
│ timestamps                                      │
└────────────┬────────────────────────────────────┘
             │
             │ 1:N
             │
     ┌───────┴────────┐
     │                │
     ▼                ▼
┌────────────┐  ┌─────────────────────────────────┐
│email_      │  │      email_templates            │
│logs        │  ├─────────────────────────────────┤
│            │  │ id (PK)                         │
│            │  │ domain_id (FK) → email_domains  │
│            │  │ template_key                    │
│            │  │ subject                         │
│            │  │ blade_html (TEXT)               │
│            │  │ status (active/inactive)        │
│            │  │ timestamps                      │
│            │  │ UNIQUE(domain_id, template_key) │
│            │  └───────┬─────────────────────────┘
│            │          │
│            │          │ 1:N
│            │          │
│            │  ┌───────▼─────────────────────────┐
│            │  │      email_logs                 │
├────────────┤  ├─────────────────────────────────┤
│ id (PK)    │  │ id (PK)                         │
│ domain_id  │◄─┤ domain_id (FK) → email_domains  │
│ template_id│◄─┤ template_id (FK) → templates    │
│ from_email │  │ from_email                      │
│ to_email   │  │ to_email                        │
│ subject    │  │ subject                         │
│ status     │  │ template_key                    │
│ mailer_used│  │ status (sent/failed/queued)     │
│ message_id │  │ error_message (TEXT, nullable)  │
│ variables  │  │ mailer_used (exim/ses)          │
│ sent_at    │  │ message_id                      │
│ timestamps │  │ variables (JSON)                │
│            │  │ sent_at (nullable)              │
└────────────┘  │ timestamps                      │
                └─────────────────────────────────┘
```

## Security Flow

```
┌─────────────────┐
│ Client Request  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 1. Rate Limiter                     │
│    - Max 60 requests/minute         │
│    - Per API key                    │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 2. API Key Authentication           │
│    - Extract X-API-Key header       │
│    - Lookup in email_domains        │
│    - Verify status = 'active'       │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 3. Input Validation                 │
│    - Validate required fields       │
│    - Validate email format          │
│    - Validate data structure        │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 4. Domain Authorization             │
│    - Match requested domain         │
│    - With API key's domain          │
│    - Prevent domain spoofing        │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 5. Domain Rate Limits               │
│    - Check hourly limit             │
│    - Check daily limit              │
│    - Per domain (not global)        │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 6. Template Validation              │
│    - Template exists?               │
│    - Template active?               │
│    - Belongs to domain?             │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 7. Blade Template Rendering         │
│    - Secure rendering               │
│    - Exception handling             │
│    - No code injection              │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 8. Email Logging                    │
│    - Log before sending             │
│    - Log result (success/fail)      │
│    - Audit trail                    │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────┐
│ Send Email      │
└─────────────────┘
```

## Multi-Tenant Isolation

```
┌──────────────────────────────────────────────────────────┐
│                    Email API Server                       │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Domain: menuvire.com                           │    │
│  │  API Key: eak_abc123...                         │    │
│  │  Mailer: exim                                   │    │
│  │  ┌──────────────────────────────────────────┐  │    │
│  │  │ Templates:                               │  │    │
│  │  │  - otp                                   │  │    │
│  │  │  - welcome                               │  │    │
│  │  │  - invoice                               │  │    │
│  │  └──────────────────────────────────────────┘  │    │
│  └─────────────────────────────────────────────────┘    │
│                                                           │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Domain: restaurant.com                         │    │
│  │  API Key: eak_xyz789...                         │    │
│  │  Mailer: ses                                    │    │
│  │  ┌──────────────────────────────────────────┐  │    │
│  │  │ Templates:                               │  │    │
│  │  │  - booking_confirmation                  │  │    │
│  │  │  - order_receipt                         │  │    │
│  │  └──────────────────────────────────────────┘  │    │
│  └─────────────────────────────────────────────────┘    │
│                                                           │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Domain: shop.com                               │    │
│  │  API Key: eak_def456...                         │    │
│  │  Mailer: exim                                   │    │
│  │  ┌──────────────────────────────────────────┐  │    │
│  │  │ Templates:                               │  │    │
│  │  │  - order_shipped                         │  │    │
│  │  │  - password_reset                        │  │    │
│  │  └──────────────────────────────────────────┘  │    │
│  └─────────────────────────────────────────────────┘    │
│                                                           │
└──────────────────────────────────────────────────────────┘

Each domain is completely isolated:
✓ Separate API keys
✓ Independent templates
✓ Individual rate limits
✓ Isolated email logs
✓ Different mail transports
```

## Component Interaction Sequence

```
Client → Middleware → Controller → Service → Mail Transport → SMTP
  │          │            │           │            │             │
  │          │            │           │            │             │
  ▼          ▼            ▼           ▼            ▼             ▼
Request  Auth Check   Validate    Render      Configure     Deliver
         Rate Limit   Domain      Template    Transport     Email
                      Template    
```

## Technology Stack

```
┌─────────────────────────────────────────────────────┐
│                   Presentation                      │
│  - RESTful API (JSON)                              │
│  - HTTP/HTTPS                                       │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│                  Application                        │
│  - Laravel 10+ Framework                           │
│  - PHP 8.1+                                        │
│  - Blade Template Engine                           │
│  - Eloquent ORM                                    │
│  - Custom Middleware                               │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│                    Data                             │
│  - MySQL 5.7+ / MariaDB                            │
│  - JSON for template variables                     │
│  - Indexes for performance                         │
└─────────────────┬───────────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────────┐
│               Infrastructure                        │
│  - cPanel / Linux Server                           │
│  - Exim Mail Server (local)                        │
│  - Amazon SES (cloud, optional)                    │
│  - HTTPS/SSL                                       │
└─────────────────────────────────────────────────────┘
```

## Scalability Architecture

```
                    ┌─────────────────┐
                    │  Load Balancer  │
                    └────────┬────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  API Server 1   │ │  API Server 2   │ │  API Server N   │
│  (Laravel)      │ │  (Laravel)      │ │  (Laravel)      │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                    ┌────────▼────────┐
                    │  Database       │
                    │  (MySQL)        │
                    │  Master/Slave   │
                    └─────────────────┘
                             │
                    ┌────────▼────────┐
                    │  Redis Cache    │
                    │  (Optional)     │
                    └─────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  Queue Worker 1 │ │  Queue Worker 2 │ │  Queue Worker N │
│  (Optional)     │ │  (Optional)     │ │  (Optional)     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

---

**Note**: This architecture supports horizontal scaling, multiple database replicas, and optional queue workers for async processing.
