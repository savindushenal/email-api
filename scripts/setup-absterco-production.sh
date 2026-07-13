#!/usr/bin/env bash
# Register Absterco dual-domain email setup on the Email API server (uniform).
# Run from the email-api project root after git pull.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Absterco Email API production setup"
echo ""

if ! grep -q '^CRM_DOMAIN_SMTP_PASSWORD=' .env 2>/dev/null; then
  echo "Add to .env (use quotes if password contains @):"
  echo "  CRM_DOMAIN_SMTP_PASSWORD='...'    # system@crm.absterco.com"
  echo "  EMAIL_DOMAIN_SMTP_PASSWORD='...'  # noreply@email.absterco.com"
  echo ""
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "WARNING: APP_KEY missing — run: php artisan key:generate --force"
  echo ""
fi

php artisan migrate --force
php artisan db:seed --class=AbstercoCrmSeeder --force

echo ""
echo "==> Next steps"
echo "1. Copy the API keys printed above to Vercel (absterco-crm):"
echo "   EMAIL_API_KEY, EMAIL_API_DOMAIN=crm.absterco.com"
echo "   EMAIL_OUTREACH_API_KEY, EMAIL_OUTREACH_API_DOMAIN=email.absterco.com"
echo "   OUTREACH_MAILBOX_DOMAIN=email.absterco.com"
echo "   EMAIL_API_ADMIN_KEY, EMAIL_API_INBOUND_SECRET"
echo ""
echo "2. Register staff mailboxes in CRM Staff Management (syncs to Email API)"
echo "   or: POST /api/admin/mailboxes with X-Admin-Key"
echo ""
echo "3. Ensure cron runs: */3 * * * * cd $(pwd) && php artisan email:poll-inbound"
