#!/usr/bin/env bash
# Build `.env.e2e.local` from `.env.local` for an isolated Playwright stack.
# Same MySQL/Redis hosts; separate schema (app_e2e), Redis DB index, Compose project, ports.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env.local ]]; then
  echo "Missing .env.local — run make ensure-env / cp .env.dist .env.local first." >&2
  exit 1
fi

E2E_HTTP_PORT="${E2E_HTTP_PORT:-9085}"
E2E_HTTPS_PORT="${E2E_HTTPS_PORT:-9460}"
E2E_MYSQL_DATABASE="${E2E_MYSQL_DATABASE:-app_e2e}"
E2E_REDIS_DB="${E2E_REDIS_DB:-1}"
E2E_VITE_PORT="${E2E_VITE_PORT:-5178}"
OUT="${E2E_ENV_FILE:-.env.e2e.local}"
E2E_BEACON_TARGET="${E2E_BEACON_TARGET:-self}"

# Preserve prior E2E BEACON_DSN across regenerations (before overwriting from .env.local).
PREV_E2E_BEACON_DSN=""
if [[ -f "$OUT" ]]; then
  PREV_E2E_BEACON_DSN="$(grep -E '^BEACON_DSN=' "$OUT" | head -1 | cut -d= -f2- || true)"
fi

cp .env.local "$OUT"

# Upsert KEY=VALUE (value must not contain newlines). Escapes & \ for sed replacement.
upsert() {
  local key="$1"
  local value="$2"
  local file="$3"
  local escaped
  escaped="$(printf '%s' "$value" | sed -e 's/[&\\]/\\&/g')"
  if grep -qE "^${key}=" "$file"; then
    sed -i -E "s|^${key}=.*$|${key}=${escaped}|" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >>"$file"
  fi
}

upsert COMPOSE_PROJECT_NAME "symfony-beacon-e2e" "$OUT"
upsert HTTP_PORT "$E2E_HTTP_PORT" "$OUT"
upsert HTTPS_PORT "$E2E_HTTPS_PORT" "$OUT"
upsert HTTP3_PORT "$E2E_HTTPS_PORT" "$OUT"
upsert DEFAULT_URI "https://localhost:${E2E_HTTPS_PORT}" "$OUT"
upsert MYSQL_DATABASE "$E2E_MYSQL_DATABASE" "$OUT"
upsert VITE_PORT "$E2E_VITE_PORT" "$OUT"

# Isolate sessions / cache / Messenger streams from the dogfood stack (shared Redis host).
# shellcheck disable=SC1091
set -a
# shellcheck source=/dev/null
source .env.local
set +a
REDIS_HOST="${REDIS_HOST:-redis-8.10.0}"
REDIS_PORT="${REDIS_PORT:-6379}"
upsert REDIS_URL "redis://${REDIS_HOST}:${REDIS_PORT}/${E2E_REDIS_DB}" "$OUT"
upsert MESSENGER_TRANSPORT_DSN "redis://${REDIS_HOST}:${REDIS_PORT}/${E2E_REDIS_DB}" "$OUT"

# BeaconBundle dogfood for the E2E stack (never touch .env.local).
# - self (default): loopback DSN of app_e2e's Symfony Beacon project (from .demo-client.e2e.env)
# - dogfood: report into the operator dogfood project (.demo-client.env client DSN)
# - off: disable reporting on the E2E containers
to_loopback_self_dsn() {
  local raw="$1"
  local keysig uuid
  keysig="$(printf '%s' "$raw" | sed -E 's|^https?://([^@]+)@.*|\1|')"
  uuid="$(printf '%s' "$raw" | sed -E 's|^https?://[^/]+/||')"
  if [[ -n "$keysig" && -n "$uuid" && "$keysig" != "$raw" && "$uuid" != "$raw" ]]; then
    printf 'http://%s@127.0.0.1/%s' "$keysig" "$uuid"
    return 0
  fi
  return 1
}

resolve_e2e_beacon_dsn() {
  local target="$1"
  case "$target" in
    off|none|empty)
      printf ''
      return
      ;;
    dogfood)
      if [[ -f .demo-client.env ]]; then
        local dsn
        dsn="$(grep -E '^BEACON_DSN=' .demo-client.env | head -1 | cut -d= -f2- || true)"
        if [[ -n "$dsn" ]]; then
          printf '%s' "$dsn"
          return
        fi
      fi
      printf ''
      return
      ;;
    self|*)
      if [[ -f .demo-client.e2e.env ]]; then
        local raw loop
        raw="$(grep -E '^BEACON_DSN=' .demo-client.e2e.env | head -1 | cut -d= -f2- || true)"
        if loop="$(to_loopback_self_dsn "$raw")"; then
          printf '%s' "$loop"
          return
        fi
      fi
      if [[ -n "$PREV_E2E_BEACON_DSN" && "$PREV_E2E_BEACON_DSN" == *"@127.0.0.1/"* ]]; then
        printf '%s' "$PREV_E2E_BEACON_DSN"
        return
      fi
      printf ''
      return
      ;;
  esac
}

BEACON_FOR_E2E="$(resolve_e2e_beacon_dsn "$E2E_BEACON_TARGET")"
upsert BEACON_DSN "$BEACON_FOR_E2E" "$OUT"

# Rewrite DATABASE_URL* database path when the URL is fully expanded (no ${MYSQL_DATABASE}).
rewrite_db_url() {
  local key="$1"
  local file="$2"
  local line
  line="$(grep -E "^${key}=" "$file" | head -1 || true)"
  [[ -z "$line" ]] && return 0
  local val="${line#*=}"
  val="${val%\"}"
  val="${val#\"}"
  if [[ "$val" == *'${MYSQL_DATABASE}'* ]]; then
    return 0
  fi
  # mysql://user:pass@host:port/dbname?... → swap path segment
  local rewritten
  rewritten="$(printf '%s' "$val" | sed -E "s|(mysql://[^/]+/)[^?]+|\\1${E2E_MYSQL_DATABASE}|")"
  upsert "$key" "\"${rewritten}\"" "$OUT"
}

rewrite_db_url DATABASE_URL "$OUT"
rewrite_db_url DATABASE_URL_RO "$OUT"

chmod 600 "$OUT" 2>/dev/null || true
if [[ -n "$BEACON_FOR_E2E" ]]; then
  echo "Wrote ${OUT} (DB=${E2E_MYSQL_DATABASE}, HTTPS=${E2E_HTTPS_PORT}, Redis DB=${E2E_REDIS_DB}, BEACON_DSN target=${E2E_BEACON_TARGET})"
else
  echo "Wrote ${OUT} (DB=${E2E_MYSQL_DATABASE}, HTTPS=${E2E_HTTPS_PORT}, Redis DB=${E2E_REDIS_DB}, BEACON_DSN empty — run make ready-e2e)"
fi
