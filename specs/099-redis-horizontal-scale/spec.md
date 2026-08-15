# Feature Specification: Redis horizontal scale + payload search + Identity thin controllers

**Feature Branch**: `099-redis-horizontal-scale`  
**Created**: 2026-08-15  
**Status**: Implemented (v1.15.0 / Phase 6.50)  
**Roadmap**: Phase 6.50  

**Input**: Operators planning HTTP replicas need shared session + rate-limit storage and an ingest queue that does not compete with MySQL telemetry; use server-share Redis when available and keep an independent Redis service for standalone `make up`. Then constrain issue tag/url filters that scan `event.payload`. Then thin Identity admin HTTP and split `ProjectAccessService`.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| R1 | Redis dual-mode | Compose `redis` service (standalone); `compose.shared.yaml` profiles it out and uses `redis-8.10.0` on `SHARED_DOCKER_NETWORK` |
| R2 | App wiring | `REDIS_URL` / `REDIS_HOST` → cache.app + rate-limit pools + session handler; Messenger transports on Redis; PHP `ext-redis` |
| R3 | Docs / Make | Extend `SHARED-SERVER.md`; `up-shared` checks Redis; `.env.dist` contract |
| P1 | Payload search | Promote tags + request URL at ingest; filter via indexed columns/table — no full-table `JSON_SEARCH` / `CAST(payload)` |
| I1 | Identity HTTP | Extract application services from fat Admin* / AccountPreferences controllers |
| I2 | AccessZ | Split `ProjectAccessService` into membership resolve, share grants, and require* guard |

## Non-goals

- Replacing Halite key storage with Redis (still file/KMS)
- Doctrine read replica routing (`DATABASE_URL_RO`)
- Full admin UI rewrite / SiteAppearance entity split
- Password / QR / HIBP residual security items (unless trivial while touching AuthKit config)

## User Scenarios & Testing

### User Story 1 - Standalone Redis with the project (P1)

**Why this priority**: Beacon must remain independent (`make up`) without requiring `developer.local.server/server`.

**Independent Test**: Fresh clone + `make up` starts a `redis` container; login and ingest rate limits work; Messenger consumes from Redis streams.

**Acceptance Scenarios**:

1. **Given** default `.env` (`REDIS_HOST=redis`), **When** `make up`, **Then** Compose starts `redis` healthy and `php` depends on it.
2. **Given** the stack up, **When** an operator logs in, **Then** the session is stored in Redis (not `var/cache/*/sessions` files).
3. **Given** Envelope ingest, **When** messages are dispatched, **Then** they land on Redis transport streams (`async_ingest` / `async`) and consumers drain them.

### User Story 2 - Shared server Redis without local Redis container (P1)

**Why this priority**: Multi-app local stacks and little-vps already run `redis-8.10.0`; a second Redis wastes resources.

**Independent Test**: With server Redis up and `REDIS_HOST=redis-8.10.0`, `make up-shared` does not start Beacon's `redis` service; app connects to shared Redis.

**Acceptance Scenarios**:

1. **Given** `REDIS_HOST=redis` while `MYSQL_HOST` is shared, **When** `make up-shared`, **Then** the target refuses or warns to set `REDIS_HOST=redis-8.10.0` (same dual-mode discipline as MySQL).
2. **Given** valid shared env + running `redis-8.10.0`, **When** `make up-shared`, **Then** no Beacon `redis` container runs and rate limits / sessions use the shared instance.
3. **Given** two Beacon workers / pods using the same `REDIS_URL` prefix, **When** ingest hits rate limits, **Then** counters are shared (not per-filesystem).

### User Story 3 - Tag/URL issue filters without payload full scan (P2)

**Why this priority**: `JSON_SEARCH` / `CAST(payload AS CHAR) LIKE` on `event` grows with retention and blocks list UX under load.

**Independent Test**: Filter issues by tag or URL uses promoted indexed data; new events populate promotions on ingest.

**Acceptance Scenarios**:

1. **Given** a new event with `tags` and request URL in payload, **When** ingest persists it, **Then** tag rows and request URL column are written.
2. **Given** an issue list filtered by tag, **When** the query runs, **Then** it does not execute `JSON_SEARCH` on `event.payload`.
3. **Given** historical events without promotions, **When** tag filter runs, **Then** behaviour is documented (empty or optional one-shot backfill command — no silent full-table JSON scan in the hot path).

### User Story 4 - Maintainable Identity admin + access façade (P3)

**Why this priority**: Deferred non-goals in 095/096; ~2k LOC of controllers/services block safe iteration.

**Independent Test**: Admin user/group/role and account preferences behaviour unchanged; unit tests cover extracted services; `ProjectAccessService` responsibilities live in focused types.

**Acceptance Scenarios**:

1. **Given** Admin → Users/Groups/Roles flows, **When** CRUD actions run, **Then** HTTP controllers stay thin (orchestration only) and domain mutations live in application services.
2. **Given** project-scoped `#[IsGranted]` and share links, **When** access is resolved, **Then** membership resolve, share grants, and require* checks are separate collaborators with the same external behaviour.

## Edge Cases

- Switching Messenger from Doctrine to Redis: operators must drain `messenger_messages` before deploy (document in PRODUCTION / SHARED-SERVER).
- Test env (`APP_ENV=test`): keep array/filesystem pools and sync Messenger — no Redis required for PHPUnit.
- Shared Redis without password today (server compose); DSN remains `redis://host:6379` until server enables `--requirepass`.
- Prefix seed isolates Beacon keys when multiple apps share one Redis.

## Assumptions

- Redis image aligns with server share (`redis:8.10.0-alpine`).
- Failed Messenger transport also uses Redis (distinct stream) so MySQL is not required for queue ops.
- Tag promotion stores normalized string values (scalar tags only; nested values JSON-encoded truncated).
- Request URL promoted from `payload.request.url` (Envelope / BeaconBundle shape).

## Success Criteria

- Operators can run standalone or shared Redis with one env switch (`REDIS_HOST`).
- Login sessions and ingest/hook/read-api rate limits survive multi-worker / multi-replica without sticky sessions.
- Ingest async load no longer writes to MySQL `messenger_messages` by default.
- Issue tag/url filters do not full-scan `event.payload` on the request path.
- Identity admin and project access code is split into testable services without behaviour regressions on covered flows.

## Dependencies

- `developer.local.server/server` Redis (`redis-8.10.0`) for shared mode
- PHP extension `redis`; Composer `symfony/redis-messenger`
- Prior shared MySQL dual-mode (`098`)
