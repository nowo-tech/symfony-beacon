#!/usr/bin/env bash
# Ensure Beacon schema exists on shared MySQL primary (server_network).
# Local/dev default: connect as root (MYSQL_USER=root) — only CREATE DATABASE.
# Optional: MYSQL_USER=beacon (or other) also creates that user + grants.
#
# Usage (from symfony-beacon root, with .env loaded / present):
#   ./.scripts/bootstrap-shared-db.sh
#
# Requires: mysql-9.7-primary running (make up-infra, or developer.local.server/server).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "Missing .env — cp .env.dist .env first" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

PRIMARY="${SHARED_MYSQL_PRIMARY:-mysql-9.7-primary}"
DB_NAME="${MYSQL_DATABASE:-symfony_beacon}"
DB_USER="${MYSQL_USER:-root}"
DB_PASS="${MYSQL_PASSWORD:-}"
ROOT_PASS="${SHARED_MYSQL_ROOT_PASSWORD:-${MYSQL_PASSWORD:-${DATABASE_PASSWORD:-}}}"

if [[ -z "$ROOT_PASS" ]]; then
  # Fall back to reading sibling developer.local.server/server/.env if present
  SERVER_ENV="$(cd "$ROOT/../../.." && pwd)/server/.env"
  if [[ -f "$SERVER_ENV" ]]; then
    # shellcheck disable=SC1090
    ROOT_PASS="$(grep -E '^DATABASE_PASSWORD=' "$SERVER_ENV" | head -1 | cut -d= -f2-)"
  fi
fi

if [[ -z "$ROOT_PASS" ]]; then
  echo "Set MYSQL_PASSWORD / SHARED_MYSQL_ROOT_PASSWORD or ensure server/.env has DATABASE_PASSWORD" >&2
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$PRIMARY"; then
	echo "Container $PRIMARY is not running. Start shared MySQL first (make up-infra)." >&2
  exit 1
fi

echo "Ensuring database \`${DB_NAME}\` on ${PRIMARY}…"

docker exec -i -e MYSQL_PWD="${ROOT_PASS}" "$PRIMARY" mysql -uroot <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOSQL

if [[ "$DB_USER" != "root" ]]; then
  echo "Creating app user \`${DB_USER}\`…"
  docker exec -i -e MYSQL_PWD="${ROOT_PASS}" "$PRIMARY" mysql -uroot <<EOSQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
fi

echo "Done. MYSQL_USER=${DB_USER} @ ${PRIMARY}/${DB_NAME} (see docs/ops/SHARED-SERVER.md)."
