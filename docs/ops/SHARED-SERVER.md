# Shared infra (in-repo MySQL + Redis)

Beacon’s **default** local/prod app stacks (`compose.yaml`, `compose.prod.yaml`) do **not** embed MySQL or Redis. Shared data services live in [`compose.infra.yaml`](../../compose.infra.yaml) (Compose project `shared-infra`) on a fixed Docker network so several `repositories/other` apps can reuse one MySQL and one Redis — the same hostname contract as `developer.local.server/server` and little-vps (`server_internal`).

## Topology switch (`MYSQL_TOPOLOGY`)

| Value | Infra services | Recommended hosts |
|-------|----------------|--------------------|
| `simple` (default) | `mysql-9.7-primary` + `redis-8.10.0` | `MYSQL_HOST` / `MYSQL_HOST_RO` → `mysql-9.7-primary` |
| `replica` | primary + Redis + `mysql-9.7-replica` (Compose profile `mysql-replica`) | `MYSQL_HOST_RO=mysql-9.7-replica` |

Set in `.env` (see `.env.dist`):

```env
MYSQL_TOPOLOGY=simple
# or: MYSQL_TOPOLOGY=replica
```

Primary is always GTID-ready and creates a `replicator` user on first empty datadir, so enabling `replica` later does not require recreating the primary data directory.

## Quick start (this repo owns infra)

```bash
cp .env.dist .env.local   # once — defaults already point at shared hosts
make up            # up-infra + app + vite-build
make ready         # migrate + seed
make down          # stops app only; MySQL/Redis stay up for siblings
make down-infra    # stops shared-infra (warn: other apps may break)
```

Production-oriented app (same infra):

```bash
make up-prod       # up-infra + compose.prod.yaml
```

Legacy aliases: `make up-shared` → `make up`; `make down-shared` → `make down`.

## Coexistence with `developer.local.server/server`

Same container names and network:

| Role | Hostname |
|------|----------|
| Network | `${SHARED_DOCKER_NETWORK:-server_network}` |
| Write MySQL | `mysql-9.7-primary` |
| Read MySQL | `mysql-9.7-replica` (when topology/replica is up) |
| Redis | `redis-8.10.0` |
| Mailpit (SMTP catcher) | `mailpit` (`smtp://mailpit:1025`; UI on host `MAILPIT_UI_PORT`) |

`make up-infra` **does not recreate** containers if `mysql-9.7-primary` already exists (e.g. started from `server/`). It starts them if stopped and requires `redis-8.10.0` + the Docker network. Do **not** run both owners’ compose files against empty names at once — pick one owner for the containers.

Alternative (infra only from `server/`):

```bash
cd /path/to/developer.local.server/server
docker compose up -d redis-8.10.0 mysql-9.7-primary mysql-9.7-replica mailpit
# then in Beacon:
make up
# Admin → Mailer DSN: smtp://mailpit:1025  (see docs/ops/MAILPIT.md)
```

On little-vps set `SHARED_DOCKER_NETWORK=server_internal` (infra already present; `up-infra` is a coexistence no-op). Prefer a dedicated app user there instead of root.

## Configure `.env`

`DATABASE_URL` / `DATABASE_URL_RO` always use the same template:

```env
DATABASE_URL="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST}:${MYSQL_PORT}/${MYSQL_DATABASE}?serverVersion=${MYSQL_VERSION}&charset=utf8mb4"
DATABASE_URL_RO="mysql://${MYSQL_USER}:${MYSQL_PASSWORD}@${MYSQL_HOST_RO}:${MYSQL_PORT_RO}/${MYSQL_DATABASE}?serverVersion=${MYSQL_VERSION}&charset=utf8mb4"
```

Redis + Messenger:

```env
REDIS_PASSWORD=
REDIS_URL=redis://${REDIS_HOST}:${REDIS_PORT}
MESSENGER_TRANSPORT_DSN=redis://${REDIS_HOST}:${REDIS_PORT}
# When server/.env (or this compose.infra) sets REDIS_PASSWORD:
# REDIS_URL=redis://:${REDIS_PASSWORD}@${REDIS_HOST}:${REDIS_PORT}
# MESSENGER_TRANSPORT_DSN=redis://:${REDIS_PASSWORD}@${REDIS_HOST}:${REDIS_PORT}
```

`REDIS_PASSWORD` is the **shared Redis** secret (REQ-INFRA-SHARED-003). Empty = no AUTH. Do not interpolate an empty password into the URL.

Defaults from `.env.dist`:

```env
SHARED_DOCKER_NETWORK=server_network
MYSQL_TOPOLOGY=simple
MYSQL_DATABASE=app
MYSQL_USER=app
MYSQL_PASSWORD=!ChangeMe!
MYSQL_ROOT_PASSWORD=!ChangeMeRoot!
MYSQL_HOST=mysql-9.7-primary
MYSQL_HOST_RO=mysql-9.7-primary
REDIS_HOST=redis-8.10.0
```

Optional schema/user helper: `make bootstrap-shared-db` (CREATE DATABASE; optional non-root user). Cold start can also use SiteBackup `/setup`.

**Queue migration:** if you previously used `doctrine://default` Messenger, drain `messenger_messages` (or accept loss of pending rows) before switching to Redis.

## Compose layout

| File | Role |
|------|------|
| `compose.infra.yaml` | Project `shared-infra`: network + MySQL (+ optional replica) + Redis; data under `./.data/infra/` |
| `compose.yaml` | Dev app only (bind-mount); joins external shared network |
| `compose.override.yaml` | App-local Mailpit fallback (`mail`), Mercure expose |
| `compose.prod.yaml` | Prod app only; same external network |

App services (`php`, messengers, vite, mailer, mercure) attach to `${SHARED_DOCKER_NETWORK:-server_network}` as **external**. No host-published MySQL/Redis ports (REQ-FP-006): use `make mysql` or `docker exec -it mysql-9.7-primary mysql …`.

## Join from another project

Sibling apps only need the external network + hostnames:

```yaml
networks:
  shared:
    name: ${SHARED_DOCKER_NETWORK:-server_network}
    external: true
```

Start infra once:

```bash
make -C /path/to/symfony-beacon up-infra
# or use developer.local.server/server
```

## What Redis is used for

| Concern | Adapter |
|---------|---------|
| HTTP sessions | `framework.session.handler_id` = `REDIS_URL` |
| `cache.app` + rate-limit pools | `cache.adapter.redis` |
| Messenger `async_ingest` / `async` / `failed` | `MESSENGER_TRANSPORT_DSN` Redis streams |
| Password-policy flash throttle | `flash_throttle_storage: cache` → `cache.app` |

`prefix_seed: symfony-beacon` isolates keys when several apps share one Redis.

## Contract (local ↔ VPS)

| Role | Local (this repo or `server/`) | little-vps |
|------|--------------------------------|------------|
| Network | `server_network` | `server_internal` |
| Write MySQL | `mysql-9.7-primary` (`MYSQL_HOST`) | Ansible primary hostname |
| Read MySQL | `mysql-9.7-replica` (`MYSQL_HOST_RO`) when replica topology is on | Ansible replica hostname |
| Redis | `redis-8.10.0` (`REDIS_HOST`) | Redis role container |

Beacon does **not** yet route Doctrine reads to the replica; apps write to the primary. `DATABASE_URL_RO` is documented for a future adapter.
