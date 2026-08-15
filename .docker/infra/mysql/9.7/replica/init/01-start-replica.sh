#!/bin/bash
# Configure GTID replication from mysql-9.7-primary (runs once on empty datadir).
set -euo pipefail

# App .env may set MYSQL_HOST=primary; force local instance for init.
unset MYSQL_HOST DATABASE_HOST
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

PRIMARY_HOST="${MYSQL_PRIMARY_HOST:-mysql-9.7-primary}"
REPLICATOR_USER="${MYSQL_REPLICATION_USER:-replicator}"
REPLICATOR_PASSWORD="${MYSQL_REPLICATION_PASSWORD:-${MYSQL_ROOT_PASSWORD}}"

# -h localhost => unix socket (do not use MYSQL_HOST / TCP)
mysql_local() {
  mysql -h localhost -uroot "$@"
}

echo "Waiting for primary ${PRIMARY_HOST}…"
for i in $(seq 1 60); do
  if mysqladmin ping -h"${PRIMARY_HOST}" -uroot --silent 2>/dev/null; then
    break
  fi
  sleep 2
done

mysqladmin ping -h"${PRIMARY_HOST}" -uroot --silent

mysql_local -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=OFF;" || true

mysql_local <<EOSQL
STOP REPLICA;
CHANGE REPLICATION SOURCE TO
  SOURCE_HOST='${PRIMARY_HOST}',
  SOURCE_USER='${REPLICATOR_USER}',
  SOURCE_PASSWORD='${REPLICATOR_PASSWORD}',
  SOURCE_AUTO_POSITION=1;
START REPLICA;
SET PERSIST read_only=ON;
SET PERSIST super_read_only=ON;
EOSQL

echo "Replica started from ${PRIMARY_HOST}."
