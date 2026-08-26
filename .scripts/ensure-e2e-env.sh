#!/usr/bin/env bash
# Build `.env.e2e.local` from `.env.e2e.dist` for an isolated Playwright stack.
# Overlay shared infra / secrets from `.env.local` (never copy isolation keys from dogfood).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DIST="${E2E_ENV_DIST:-.env.e2e.dist}"
OUT="${E2E_ENV_FILE:-.env.e2e.local}"

if [[ ! -f "$DIST" ]]; then
  echo "Missing ${DIST} — this repository must version the E2E env template." >&2
  exit 1
fi

E2E_HTTP_PORT="${E2E_HTTP_PORT:-9085}"
E2E_HTTPS_PORT="${E2E_HTTPS_PORT:-9460}"
E2E_MYSQL_DATABASE="${E2E_MYSQL_DATABASE:-app_e2e}"
E2E_REDIS_DB="${E2E_REDIS_DB:-1}"
E2E_VITE_PORT="${E2E_VITE_PORT:-5178}"
E2E_BEACON_TARGET="${E2E_BEACON_TARGET:-self}"

# Preserve prior E2E BEACON_DSN across regenerations (before overwriting from dist).
PREV_E2E_BEACON_DSN=""
if [[ -f "$OUT" ]]; then
  PREV_E2E_BEACON_DSN="$(grep -E '^BEACON_DSN=' "$OUT" | head -1 | cut -d= -f2- || true)"
fi

cp "$DIST" "$OUT"

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

# Isolation keys owned by .env.e2e.dist / Make E2E_* — never copy from .env.local.
is_isolation_key() {
  case "$1" in
    COMPOSE_PROJECT_NAME|HTTP_PORT|HTTPS_PORT|HTTP3_PORT|DEFAULT_URI|MYSQL_DATABASE|VITE_PORT|REDIS_URL|MESSENGER_TRANSPORT_DSN|BEACON_DSN|DATABASE_URL|DATABASE_URL_RO)
      return 0
      ;;
  esac
  return 1
}

# Overlay operator secrets and shared infra from .env.local (MYSQL_PASSWORD, REDIS_HOST, …).
if [[ -f .env.local ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    [[ "$line" =~ ^[[:space:]]*$ ]] && continue
    [[ "$line" != *=* ]] && continue
    key="${line%%=*}"
    value="${line#*=}"
    is_isolation_key "$key" && continue
    upsert "$key" "$value" "$OUT"
  done < .env.local
else
  echo "Note: no .env.local — ${OUT} uses ${DIST} placeholders. Run make ensure-env if shared infra secrets differ." >&2
fi

upsert COMPOSE_PROJECT_NAME "symfony-beacon-e2e" "$OUT"
upsert HTTP_PORT "$E2E_HTTP_PORT" "$OUT"
upsert HTTPS_PORT "$E2E_HTTPS_PORT" "$OUT"
upsert HTTP3_PORT "$E2E_HTTPS_PORT" "$OUT"
upsert DEFAULT_URI "https://localhost:${E2E_HTTPS_PORT}" "$OUT"
upsert MYSQL_DATABASE "$E2E_MYSQL_DATABASE" "$OUT"
upsert VITE_PORT "$E2E_VITE_PORT" "$OUT"

# Isolate sessions / cache / Messenger from the dogfood stack (shared Redis host).
# REDIS_URL path = Redis DB index. MESSENGER path would be the stream name (overrides
# messenger.yaml) — use ?dbindex=N instead (REQ-MESSENGER-001).
# Prefer REDIS_* already written to OUT (.env.e2e.dist + optional .env.local overlay).
env_file_value() {
  local key="$1"
  local file="$2"
  local line
  line="$(grep -E "^${key}=" "$file" | head -1 || true)"
  [[ -z "$line" ]] && return 0
  printf '%s' "${line#*=}"
}
REDIS_HOST_VALUE="$(env_file_value REDIS_HOST "$OUT")"
REDIS_PORT_VALUE="$(env_file_value REDIS_PORT "$OUT")"
REDIS_HOST_VALUE="${REDIS_HOST_VALUE:-redis-8.10.0}"
REDIS_PORT_VALUE="${REDIS_PORT_VALUE:-6379}"
upsert REDIS_URL "redis://${REDIS_HOST_VALUE}:${REDIS_PORT_VALUE}/${E2E_REDIS_DB}" "$OUT"
upsert MESSENGER_TRANSPORT_DSN "redis://${REDIS_HOST_VALUE}:${REDIS_PORT_VALUE}?dbindex=${E2E_REDIS_DB}" "$OUT"

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
  echo "Wrote ${OUT} from ${DIST} (DB=${E2E_MYSQL_DATABASE}, HTTPS=${E2E_HTTPS_PORT}, Redis DB=${E2E_REDIS_DB}, BEACON_DSN target=${E2E_BEACON_TARGET})"
else
  echo "Wrote ${OUT} from ${DIST} (DB=${E2E_MYSQL_DATABASE}, HTTPS=${E2E_HTTPS_PORT}, Redis DB=${E2E_REDIS_DB}, BEACON_DSN empty — run make ready-e2e)"
fi
