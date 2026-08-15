#!/bin/bash
# Create replication user on primary (runs once on empty datadir).
set -euo pipefail

REPLICATOR_PASSWORD="${MYSQL_REPLICATION_PASSWORD:-${MYSQL_ROOT_PASSWORD}}"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOSQL
CREATE USER IF NOT EXISTS 'replicator'@'%' IDENTIFIED BY '${REPLICATOR_PASSWORD}';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
FLUSH PRIVILEGES;
EOSQL

echo "Replication user 'replicator' ready."
