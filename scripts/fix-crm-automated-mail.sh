#!/usr/bin/env bash
# Fix crm.absterco.com automated mail: system@ account + SMTP password.
# Run ON THE EMAIL API SERVER after git pull.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Fix crm.absterco.com automated mail (system@)"
echo ""

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "ERROR: APP_KEY is missing in .env"
  echo "Run: php artisan key:generate --force && php artisan config:clear"
  exit 1
fi

if ! grep -q '^CRM_DOMAIN_SMTP_PASSWORD=' .env 2>/dev/null; then
  echo "Add to .env:"
  echo "  CRM_DOMAIN_SMTP_PASSWORD='system@2026'"
  echo ""
fi

php artisan config:clear

echo "==> Updating domain in database (no HTTP/curl needed)..."
php artisan absterco:fix-crm-mail

echo ""
echo "==> Done. Test send:"
echo "php artisan tinker --execute=\"\\Illuminate\\Support\\Facades\\Mail::raw('test', fn(\\$m) => \\$m->to('your@email.com')->subject('CRM mail test'));\""
echo ""
echo "Or via Admin API:"
echo "curl -X POST https://email.absterco.com/api/admin/domains/crm.absterco.com/test-email \\"
echo "  -H \"X-Admin-Key: \$(grep ^ADMIN_API_KEY= .env | cut -d= -f2- | tr -d '\"')\" \\"
echo "  -H \"Content-Type: application/json\" -d '{\"to\":\"your@email.com\"}'"
