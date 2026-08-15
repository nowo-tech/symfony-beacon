# Shared server mode (developer.local.server `server/` or little-vps)

Beacon stays **standalone by default** (`make up` → Compose service `database`, `MYSQL_HOST=database`).  
Use **shared mode** when you want one MySQL (and Redis) for several `repositories/other` apps — the same contract later used on little-vps (`server_internal`).

## Prerequisites

1. Shared infra up (from `developer.local.server/server`):

```bash
cd /path/to/developer.local.server/server
cp -n .env.dist .env   # once
docker compose up -d redis-8.10.0 mysql-9.7-primary mysql-9.7-replica
# or: make up-mysql   # MySQL pair; Redis via make up-base
```

2. Docker network `server_network` exists (created by the server compose file).

## Configure Beacon `.env`

`DATABASE_URL` / `DATABASE_URL_RO` always use the same template:

```env
DATABASE_URL="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST}:${MYSQL_PORT}/${MYSQL_DATABASE}?serverVersion=${MYSQL_VERSION}&charset=utf8mb4"
DATABASE_URL_RO="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST_RO}:${MYSQL_PORT_RO}/${MYSQL_DATABASE}?serverVersion=${MYSQL_VERSION}&charset=utf8mb4"
```

Standalone (`make up`) defaults:

```env
MYSQL_DATABASE=app
MYSQL_USER=app
MYSQL_PASSWORD=!ChangeMe!
MYSQL_HOST=database
MYSQL_HOST_RO=database
```

Shared mode (local/dev) — connect as **MySQL root** (same password as `server/.env` `DATABASE_PASSWORD`). No app user to create:

```env
SHARED_DOCKER_NETWORK=server_network
MYSQL_DATABASE=symfony_beacon
MYSQL_USER=root
MYSQL_PASSWORD=toChange!          # = server/.env DATABASE_PASSWORD
MYSQL_HOST=mysql-9.7-primary
MYSQL_HOST_RO=mysql-9.7-replica
```

On little-vps, set `SHARED_DOCKER_NETWORK=server_internal` (same MySQL hostnames). Prefer a dedicated app user there instead of root.

`make up-shared` runs `bootstrap-shared-db` automatically (ensures `CREATE DATABASE IF NOT EXISTS`). With `MYSQL_USER=root` it does not create an extra user.

## Start / stop

```bash
make up-shared     # ensures schema + compose.yaml + override + compose.shared.yaml
make ready         # migrate + seed (same as standalone)
make down-shared
```

`make up` remains the independent path (embeds MySQL 9.7 in the project Compose file).  
`make up-shared` refuses to start if `MYSQL_HOST` is still `database`.

## What shared Compose does

| Change | Detail |
|--------|--------|
| No local `database` | Service tagged with profile `standalone-db` (not started) |
| External network | `${SHARED_DOCKER_NETWORK:-server_network}` |
| App services | `php`, messengers, vite, mailer, mercure join that network |

A marker file `.compose-mode` (`shared`) makes other Make targets use the shared Compose file set while the stack is up.

## Contract (local ↔ VPS)

| Role | Local `server/` | little-vps |
|------|-----------------|------------|
| Network | `server_network` | `server_internal` |
| Write MySQL | `mysql-9.7-primary` (`MYSQL_HOST`) | Ansible primary hostname |
| Read MySQL | `mysql-9.7-replica` (`MYSQL_HOST_RO`) | Ansible replica hostname |
| Redis | `redis-8.10.0` | Redis role container |

Beacon does **not** yet route Doctrine reads to the replica; apps write to the primary. `DATABASE_URL_RO` is documented for a future adapter.
