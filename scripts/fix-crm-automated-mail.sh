#!/usr/bin/env bash
# Fix crm.absterco.com automated mail: system@ account + SMTP password via Admin API.
# Run ON THE EMAIL API SERVER (email.absterco.com) after git pull.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Fix crm.absterco.com automated mail (system@)"
echo ""

# 1. APP_KEY is required to encrypt SMTP passwords in mail_config
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "ERROR: APP_KEY is missing in .env — Admin API cannot save mail_config.password."
  echo "Run: php artisan key:generate --force"
  echo "Then: php artisan config:clear"
  exit 1
fi

ADMIN_KEY="${ADMIN_API_KEY:-}"
if [[ -z "$ADMIN_KEY" ]] && [[ -f .env ]]; then
  ADMIN_KEY="$(grep -E '^ADMIN_API_KEY=' .env | cut -d= -f2- | tr -d '"')"
fi
if [[ -z "$ADMIN_KEY" ]]; then
  echo "Set ADMIN_API_KEY in .env or export ADMIN_API_KEY=admin_..."
  exit 1
fi

CRM_SMTP_PASSWORD="${CRM_DOMAIN_SMTP_PASSWORD:-}"
if [[ -z "$CRM_SMTP_PASSWORD" ]] && [[ -f .env ]]; then
  CRM_SMTP_PASSWORD="$(grep -E '^CRM_DOMAIN_SMTP_PASSWORD=' .env | cut -d= -f2- | tr -d "'\"")"
fi
if [[ -z "$CRM_SMTP_PASSWORD" ]]; then
  read -rsp "SMTP password for system@crm.absterco.com: " CRM_SMTP_PASSWORD
  echo ""
fi

BASE_URL="${APP_URL:-http://127.0.0.1}"
if [[ "$BASE_URL" == http://localhost* ]]; then
  BASE_URL="http://127.0.0.1"
fi

PAYLOAD=$(php -r '
$pw = getenv("CRM_SMTP_PASSWORD") ?: "";
echo json_encode([
  "from_email" => "system@crm.absterco.com",
  "from_name" => "Absterco CRM",
  "mail_config" => [
    "host" => "uniform.de.hostns.io",
    "port" => 465,
    "encryption" => "ssl",
    "username" => "system@crm.absterco.com",
    "password" => $pw,
    "inbound" => ["enabled" => false],
  ],
], JSON_UNESCAPED_SLASHES);
' CRM_SMTP_PASSWORD="$CRM_SMTP_PASSWORD")

echo "==> Updating domain via Admin API..."
RESP=$(curl -sS -X PUT "${BASE_URL}/api/admin/domains/crm.absterco.com" \
  -H "X-Admin-Key: ${ADMIN_KEY}" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")

echo "$RESP" | php -r '
$j = json_decode(stream_get_contents(STDIN), true);
if (!($j["success"] ?? false)) { fwrite(STDERR, json_encode($j, JSON_PRETTY_PRINT)."\n"); exit(1); }
$d = $j["data"] ?? [];
echo "OK: from=".$d["from_email"]." mail_config_updated=".($d["mail_config_updated"] ? "true" : "false")."\n";
if (($d["from_email"] ?? "") !== "system@crm.absterco.com") exit(1);
if (!($d["mail_config_updated"] ?? false)) exit(1);
'

echo ""
echo "==> Done. Test with:"
echo "curl -X POST ${BASE_URL}/api/admin/domains/crm.absterco.com/test-email \\"
echo "  -H \"X-Admin-Key: \${ADMIN_API_KEY}\" -H \"Content-Type: application/json\" \\"
echo "  -d '{\"to\":\"your@email.com\"}'"
