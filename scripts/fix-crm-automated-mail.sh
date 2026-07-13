#!/usr/bin/env bash
# Fix crm.absterco.com automated mail: system@ account + SMTP password via Admin API.
# Run ON THE EMAIL API SERVER (email.absterco.com) after git pull.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Fix crm.absterco.com automated mail (system@)"
echo ""

read_env_var() {
  local key="$1"
  if [[ ! -f .env ]]; then
    return
  fi
  grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//" | tr -d '\r\n'
}

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "ERROR: APP_KEY is missing in .env — Admin API cannot save mail_config.password."
  echo "Run: php artisan key:generate --force"
  echo "Then: php artisan config:clear"
  exit 1
fi

ADMIN_KEY="${ADMIN_API_KEY:-}"
if [[ -z "$ADMIN_KEY" ]]; then
  ADMIN_KEY="$(read_env_var ADMIN_API_KEY)"
fi
if [[ -z "$ADMIN_KEY" ]]; then
  echo "Set ADMIN_API_KEY in .env or export ADMIN_API_KEY=admin_..."
  exit 1
fi

CRM_SMTP_PASSWORD="${CRM_DOMAIN_SMTP_PASSWORD:-}"
if [[ -z "$CRM_SMTP_PASSWORD" ]]; then
  CRM_SMTP_PASSWORD="$(read_env_var CRM_DOMAIN_SMTP_PASSWORD)"
fi
if [[ -z "$CRM_SMTP_PASSWORD" ]]; then
  read -rsp "SMTP password for system@crm.absterco.com: " CRM_SMTP_PASSWORD
  echo ""
  CRM_SMTP_PASSWORD="$(printf '%s' "$CRM_SMTP_PASSWORD" | tr -d '\r\n')"
fi

BASE_URL="$(read_env_var APP_URL)"
BASE_URL="${BASE_URL:-http://127.0.0.1}"
if [[ "$BASE_URL" == http://localhost* ]]; then
  BASE_URL="http://127.0.0.1"
fi
BASE_URL="${BASE_URL%/}"

export CRM_SMTP_PASSWORD
PAYLOAD="$(php -r '
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
')"

echo "==> Updating domain via Admin API (${BASE_URL})..."
RESP="$(curl -sS -w "\n%{http_code}" -X PUT "${BASE_URL}/api/admin/domains/crm.absterco.com" \
  -H "X-Admin-Key: ${ADMIN_KEY}" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")"

HTTP_CODE="$(printf '%s' "$RESP" | tail -n1)"
BODY="$(printf '%s' "$RESP" | sed '$d')"

if [[ "$HTTP_CODE" != "200" ]]; then
  echo "ERROR: HTTP ${HTTP_CODE}"
  echo "$BODY"
  exit 1
fi

printf '%s' "$BODY" | php -r '
$raw = stream_get_contents(STDIN);
$j = json_decode($raw, true);
if (!is_array($j)) {
  fwrite(STDERR, "Invalid JSON response:\n".$raw."\n");
  exit(1);
}
if (!($j["success"] ?? false)) {
  fwrite(STDERR, json_encode($j, JSON_PRETTY_PRINT)."\n");
  exit(1);
}
$d = $j["data"] ?? [];
echo "OK: from=".($d["from_email"] ?? "?")." mail_config_updated=".((($d["mail_config_updated"] ?? false) ? "true" : "false"))."\n";
if (($d["from_email"] ?? "") !== "system@crm.absterco.com") {
  fwrite(STDERR, "from_email was not updated to system@crm.absterco.com\n");
  exit(1);
}
if (!($d["mail_config_updated"] ?? false)) {
  fwrite(STDERR, "mail_config was not updated — check request body and APP_KEY\n");
  exit(1);
}
'

echo ""
echo "==> Done. Test with:"
echo "curl -X POST ${BASE_URL}/api/admin/domains/crm.absterco.com/test-email \\"
echo "  -H \"X-Admin-Key: \${ADMIN_API_KEY}\" -H \"Content-Type: application/json\" \\"
echo "  -d '{\"to\":\"your@email.com\"}'"
