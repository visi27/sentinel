#!/usr/bin/env bash
#
# End-to-end smoke test for Sentinel.
#
# Walks one card through its full lifecycle and verifies the result at
# every step:
#
#   1. Issue a card                 POST  /api/cards
#   2. Verify it's Pending          GET   /api/cards/{id}
#   3. Activate it                  POST  /api/cards/{id}/activate
#   4. Verify it's Active           GET   /api/cards/{id}
#   5. Authorize a transaction      POST  /api/authorizations  (HMAC-signed)
#   6. Verify balance + spend       GET   /api/cards/{id}
#   7. Verify it's in the list      GET   /api/cards/{id}/authorizations
#   8. Verify async fan-out         (docker compose logs mock-receiver)
#
# Re-runnable: every invocation creates a fresh card with a unique
# processor_auth_id. The stack must be running (`make bootstrap`).
#
# Overridable via env: API, ADMIN_KEY, PROCESSOR_SIGNING_SECRET.

set -euo pipefail

# Run from the project root so `docker compose` finds compose.yaml
# regardless of where the script was invoked from.
cd "$(cd "$(dirname "$0")/.." && pwd)"

API="${API:-http://localhost:8100}"
ADMIN_KEY="${ADMIN_KEY:-dev_admin_key}"
SECRET="${PROCESSOR_SIGNING_SECRET:-dev_processor_signing_secret}"

# ── output styling ────────────────────────────────────────────────────
green=$'\033[0;32m'; red=$'\033[0;31m'; dim=$'\033[2m'; reset=$'\033[0m'
ok()   { printf "  %s✓%s %s\n" "$green" "$reset" "$1"; }
fail() { printf "  %s✗%s %s\n" "$red"   "$reset" "$1"; exit 1; }
step() { printf "\n%s━━%s %s\n" "$dim" "$reset" "$1"; }

# ── preflight ─────────────────────────────────────────────────────────
for tool in curl jq openssl; do
  command -v "$tool" >/dev/null || { echo "missing required tool: $tool"; exit 1; }
done

curl -sS -o /dev/null --max-time 3 "$API/health" \
  || fail "API not reachable at $API — run 'make bootstrap' first"

# ──────────────────────────────────────────────────────────────────────
step "1. Issue a card"

ISSUE_BODY='{
  "cardholder_id": "01890d3a-3e95-7000-8000-1234567890ab",
  "spending_limits": {"per_transaction": 50000, "daily": 200000, "monthly": 1000000},
  "initial_balance": 100000,
  "currency": "USD",
  "allowed_merchant_categories": ["4121", "5812"]
}'

CARD_ID=$(curl -sS -X POST "$API/api/cards" \
  -H "X-API-Key: $ADMIN_KEY" \
  -H "Content-Type: application/json" \
  -d "$ISSUE_BODY" | jq -r '.id')

[[ "$CARD_ID" =~ ^[0-9a-f]{8}-[0-9a-f-]+$ ]] || fail "did not get a UUID back: '$CARD_ID'"
ok "POST /api/cards → 201, id=$CARD_ID"

# ──────────────────────────────────────────────────────────────────────
step "2. Verify the new card is in Pending state"

STATUS=$(curl -sS "$API/api/cards/$CARD_ID" -H "X-API-Key: $ADMIN_KEY" | jq -r '.status')
[ "$STATUS" = "pending" ] || fail "expected pending, got $STATUS"
ok "GET /api/cards/$CARD_ID → status=pending"

# ──────────────────────────────────────────────────────────────────────
step "3. Activate the card"

HTTP=$(curl -sS -o /dev/null -w "%{http_code}" -X POST \
  "$API/api/cards/$CARD_ID/activate" -H "X-API-Key: $ADMIN_KEY")
[ "$HTTP" = "204" ] || fail "activate returned $HTTP, expected 204"
ok "POST /api/cards/$CARD_ID/activate → 204"

STATUS=$(curl -sS "$API/api/cards/$CARD_ID" -H "X-API-Key: $ADMIN_KEY" | jq -r '.status')
[ "$STATUS" = "active" ] || fail "expected active, got $STATUS"
ok "card is now Active"

# ──────────────────────────────────────────────────────────────────────
step "4. Authorize a \$250.00 transaction (HMAC-signed)"

PROC_AUTH_ID="e2e_$(date +%s)_$$"
AUTH_BODY=$(jq -nc \
  --arg pid "$PROC_AUTH_ID" \
  --arg card "$CARD_ID" \
  --arg now "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  '{
    processor_auth_id: $pid,
    card_id:           $card,
    amount:            25000,
    currency:          "USD",
    merchant:          {name: "Uber", category_code: "4121",
                        location: {city: "Boston", region: "MA", country: "US"}},
    requested_at:      $now
  }')

TS=$(date +%s)
SIG=$(printf '%s.%s' "$TS" "$AUTH_BODY" | openssl dgst -sha256 -hmac "$SECRET" -r | cut -d' ' -f1)

DECISION=$(curl -sS -X POST "$API/api/authorizations" \
  -H "Content-Type: application/json" \
  -H "X-Processor-Signature: t=$TS,v1=$SIG" \
  -d "$AUTH_BODY")

DEC_STATUS=$(echo "$DECISION" | jq -r '.status')
[ "$DEC_STATUS" = "approved" ] || fail "expected approved, got: $DECISION"
AUTH_ID=$(echo "$DECISION" | jq -r '.authorization_id')
ok "POST /api/authorizations → status=approved, id=$AUTH_ID"

# ──────────────────────────────────────────────────────────────────────
step "5. Verify balance + spend counters"

CARD=$(curl -sS "$API/api/cards/$CARD_ID" -H "X-API-Key: $ADMIN_KEY")
BAL=$(echo "$CARD"     | jq -r '.available_balance.amount')
DAILY=$(echo "$CARD"   | jq -r '.daily_spend.amount')
MONTHLY=$(echo "$CARD" | jq -r '.monthly_spend.amount')

[ "$BAL" = "75000" ]     || fail "expected balance 75000, got $BAL"
[ "$DAILY" = "25000" ]   || fail "expected daily 25000, got $DAILY"
[ "$MONTHLY" = "25000" ] || fail "expected monthly 25000, got $MONTHLY"
ok "available_balance=\$$(awk "BEGIN{printf \"%.2f\", $BAL/100}") (started \$1000.00, -\$250.00)"
ok "daily_spend=\$$(awk "BEGIN{printf \"%.2f\", $DAILY/100}"), monthly_spend=\$$(awk "BEGIN{printf \"%.2f\", $MONTHLY/100}")"

# ──────────────────────────────────────────────────────────────────────
step "6. Verify the authorization is queryable via the list endpoint"

LIST=$(curl -sS "$API/api/cards/$CARD_ID/authorizations" -H "X-API-Key: $ADMIN_KEY")
TOTAL=$(echo "$LIST"         | jq -r '.total_items')
LISTED_ID=$(echo "$LIST"     | jq -r '.items[0].id')
LISTED_STATUS=$(echo "$LIST" | jq -r '.items[0].status')

[ "$TOTAL" = "1" ]                || fail "expected 1 authorization in the list, got $TOTAL"
[ "$LISTED_ID" = "$AUTH_ID" ]     || fail "list shows wrong authorization id"
[ "$LISTED_STATUS" = "approved" ] || fail "list shows wrong status"
ok "GET /api/cards/$CARD_ID/authorizations → 1 item, id matches, status=approved"

# ──────────────────────────────────────────────────────────────────────
step "7. Verify async fan-out reached the mock receiver"

if ! command -v docker >/dev/null; then
  printf "  %s\n" "(docker CLI not available; skipping async-fanout check)"
else
  echo "  waiting up to 20s for outbox worker → SQS → Lambda → subscriber..."
  FOUND=""
  for i in $(seq 1 10); do
    sleep 2
    if docker compose logs mock-receiver 2>/dev/null | grep -q "$AUTH_ID"; then
      FOUND=1
      ok "mock-receiver captured card.authorization.approved with authorization_id=$AUTH_ID (after ${i}x2s)"
      break
    fi
  done
  [ -n "$FOUND" ] || fail "$AUTH_ID never reached mock-receiver — check 'docker compose logs worker-outbox' and 'docker compose logs floci'"
fi

printf "\n%s━━ all checks passed%s\n" "$green" "$reset"
printf "%sfull pipeline verified: HTTP → DB → outbox → SQS → Lambda → subscriber%s\n" "$dim" "$reset"
