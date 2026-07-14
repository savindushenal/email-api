# Absterco CRM — Email API Setup

**Date:** May 26, 2026  
**Domain registered:** `crm.absterco.com`  
**Sending account:** `system@crm.absterco.com`

---

## 1. SMTP Configuration

| Setting | Value |
|---|---|
| Username | `system@crm.absterco.com` |
| Password | `system@2026` |
| Outgoing Server (SMTP) | `uniform.de.hostns.io` |
| SMTP Port | `465` |
| Encryption | `SSL` |
| Incoming Server (IMAP) | `uniform.de.hostns.io` |
| IMAP Port | `993` |
| POP3 Port | `995` |

---

## 2. Registered Domain

| Field | Value |
|---|---|
| Domain | `crm.absterco.com` |
| From Email | `system@crm.absterco.com` |
| From Name | `Absterco CRM` |
| Mailer | `exim` (transport overridden by `mail_config`) |
| Daily Limit | `2000` |
| Hourly Limit | `200` |

---

## 3. API Key

```
eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo
```

> **Important:** This is the raw API key. Store it in the CRM `.env` as shown in section 5.
> The Email API stores a SHA-256 hash of this key — never the raw value.

---

## 4. Run the Seeder

```bash
# From the email-api directory
php artisan db:seed --class=AbstercoCrmSeeder
```

Safe to re-run — uses `updateOrCreate` so existing data is updated, not duplicated.

---

## 5. CRM Environment Variables

Add to `e:\githubNew\absterco-crm\.env` (and Vercel Environment Variables for production):

```env
# Email API
EMAIL_API_BASE_URL=http://localhost:8001
EMAIL_API_KEY=eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo
EMAIL_API_DOMAIN=crm.absterco.com
EMAIL_REPLY_DOMAIN=crm.absterco.com
EMAIL_API_INBOUND_SECRET=<shared-secret-with-email-api>
```

For production:
```env
EMAIL_API_BASE_URL=https://email.absterco.com
EMAIL_API_KEY=eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo
EMAIL_API_DOMAIN=crm.absterco.com
EMAIL_REPLY_DOMAIN=crm.absterco.com
EMAIL_API_INBOUND_SECRET=<shared-secret-with-email-api>
```

---

## 6. Email Templates Created (10 total)

| # | Template Key | Category | Trigger |
|---|---|---|---|
| 1 | `ticket-created` | notification | Client/staff opens a ticket |
| 2 | `ticket-status-changed` | notification | Staff changes ticket status |
| 3 | `ticket-comment-client` | notification | Staff adds public comment |
| 4 | `ticket-comment-staff` | notification | Client adds a comment |
| 5 | `ticket-milestone-completed` | notification | Staff completes a milestone |
| 6 | `ticket-resolved` | notification | Ticket moved to RESOLVED |
| 7 | `invoice-issued` | transactional | Staff issues invoice |
| 8 | `invoice-payment-reminder` | transactional | 3 days before due date (cron) |
| 9 | `invoice-overdue` | transactional | 1 day after due date (cron) |
| 10 | `invoice-paid` | transactional | Payment marked as PAID |

---

## 7. How the Per-Domain SMTP Works

The `mail_config` JSON column on the `email_domains` table stores per-domain SMTP credentials.
`EmailService::configureMailer()` reads this at send time and overrides the default Laravel mail config.

The stored config for `crm.absterco.com` includes SMTP and inbound IMAP (passwords encrypted on save via seeder):

```json
{
  "transport":  "smtp",
  "host":       "uniform.de.hostns.io",
  "port":       465,
  "encryption": "ssl",
  "username":   "system@crm.absterco.com",
  "password":   "enc:...",
  "inbound": {
    "enabled":    true,
    "host":       "uniform.de.hostns.io",
    "port":       993,
    "encryption": "ssl",
    "folder":     "INBOX"
  }
}
```

---

## 8. Send Email — API Usage

```bash
curl -X POST http://localhost:8001/api/email/send \
  -H "Content-Type: application/json" \
  -H "X-API-Key: eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo" \
  -d '{
    "domain": "crm.absterco.com",
    "to": "client@example.com",
    "template": "ticket-created",
    "data": {
      "client_name":     "John Smith",
      "ticket_number":   "TKT-0001",
      "ticket_title":    "Login page broken on Safari",
      "ticket_type":     "BUG_REPORT",
      "ticket_priority": "HIGH",
      "ticket_url":      "https://crm.absterco.com/app/tickets/abc123",
      "org_name":        "Acme Corp"
    }
  }'
```

---

## 9. CRM Utility Function

Create `src/lib/email/client.ts` in the CRM:

```typescript
// src/lib/email/client.ts
const BASE_URL = process.env.EMAIL_API_BASE_URL!
const API_KEY  = process.env.EMAIL_API_KEY!
const DOMAIN   = process.env.EMAIL_API_DOMAIN!

export async function sendEmail(
  to: string,
  template: string,
  data: Record<string, unknown>
): Promise<void> {
  const res = await fetch(`${BASE_URL}/api/email/send`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': API_KEY,
    },
    body: JSON.stringify({ domain: DOMAIN, to, template, data }),
  })
  if (!res.ok) {
    const body = await res.text()
    console.error(`[EMAIL] Failed to send ${template} to ${to}:`, body)
  }
}
```

---

## 10. Verify Setup

```bash
# 1. Run seeder
php artisan db:seed --class=AbstercoCrmSeeder

# 2. Test email via admin endpoint (requires X-Admin-Key header)
curl -X POST http://localhost:8001/api/admin/domains/crm.absterco.com/test-email \
  -H "X-Admin-Key: <your-admin-key>" \
  -H "Content-Type: application/json" \
  -d '{"to": "you@example.com"}'

# 3. Preview a template
curl -X POST http://localhost:8001/api/email/templates/ticket-created/preview \
  -H "X-API-Key: eak_AbstercoCRM2026xK9mLpQvTzWnRsYbJcFdHeGiUo" \
  -H "Content-Type: application/json" \
  -d '{
    "client_name": "Test User",
    "ticket_number": "TKT-0001",
    "ticket_title": "Test Ticket",
    "ticket_type": "SUPPORT",
    "ticket_priority": "MEDIUM",
    "ticket_url": "https://crm.absterco.com/tickets/1",
    "org_name": "Test Org"
  }'
```

---

## 11. Deal email replies (IMAP)

Outbound outreach sets `Reply-To: reply+{token}@crm.absterco.com`. Inbound IMAP is configured on the **domain row** (`mail_config.inbound`), not in `.env`. Replies land in `system@crm.absterco.com` on hostns (cPanel), **not** Zoho.

**Only enable inbound on domains that send deal outreach.** Ticket, invoice, OTP, and other transactional sends do not use reply threading.

**Prerequisites:**

1. `crm.absterco.com` MX record points to `uniform.de.hostns.io` (not Zoho).
2. Plus-addressing or catch-all enabled for `crm.absterco.com` in cPanel.
3. Re-run seeder or set `mail_config.inbound.enabled: true` on the domain.

**Global webhook (email-api `.env`):**

```env
INBOUND_CRM_WEBHOOK_URL=https://crm.absterco.com/api/webhooks/email-api/inbound-deal-reply
INBOUND_CRM_WEBHOOK_SECRET=<same as EMAIL_API_INBOUND_SECRET>
```

**Cron:**

```cron
*/3 * * * * cd /path/to/email-api && php artisan email:poll-inbound >> /dev/null 2>&1
```

The poller opens **INBOX plus Junk/Spam** (auto-detected folder names like `Junk`, `Spam`, `Bulk`). Trash/Deleted is not polled. Dedup is by Message-ID so the same reply is not imported twice if it later moves to INBOX.

### Keep replies out of Junk (cPanel filter)

Do this once per staff outreach mailbox (e.g. `savindu@email.absterco.com`):

1. cPanel → **Email Accounts** → **Manage** the staff mailbox → **Create Filter** (or **Email Filters**).
2. Add a filter, for example:
   - **Rules:** Subject starts with `Re:`  
     **OR** To contains the staff address  
   - **Actions:** Deliver to Inbox **and** (if available) **Do not spam** / skip spam filter
3. Optional: Email Accounts → Spam Filters / SpamAssassin → whitelist known prospect domains, or lower the spam threshold for that account.
4. Move any existing deal replies from Junk → Inbox once, then run:

```bash
php artisan email:poll-inbound --force --days=60 --verbose-messages
```
