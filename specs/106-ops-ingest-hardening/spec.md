# Feature Specification: Ops ingest hardening (queue depth, quotas, retention, SSRF, PHPStan)

**Feature Branch**: `106-ops-ingest-hardening`  
**Created**: 2026-08-25  
**Status**: Implemented (Unreleased / Phase 6.58)  
**Roadmap**: Phase 6.58  

**Input**: After AuthKit security kits (`105`), close Unreleased ops/ingest/security/QA gaps: Messenger depths from Redis transports (including failed), cached daily/monthly quota counts, one unique-constraint Envelope retry, batched retention deletes, shared private-network SSRF for Mercure + webhooks, a dry-run command to drop redundant Halite API-key ciphertext, AuditKit timestamps on member-alert/push entities, empty PHPStan baseline with injectable FrankenPHP seams, and restore the includable PHPUnit 100% gate.

## Summary

Operators see truthful queue and failed-transport counts; ingest stays fast under burst without `COUNT(*)` on every ACK; retention does not lock huge `event` tables; outbound URL policy is one shared helper; leftover encrypted ingest secrets can be inventoried without revoking working keys. Maintainers keep PHPStan level 6 + FrankenPHP rules green without path-scoped ignores, and `COVERAGE_MIN=100` on includable `src/`.

Prefer official Nowo.tech kits — timestamps use [`nowo-tech/audit-kit-bundle`](https://packagist.org/packages/nowo-tech/audit-kit-bundle) `TimestampableTrait` (do not hand-roll `createdAt`/`updatedAt`). FormKit pin is **2.5.2**. Dashboard Menu admin search needs a host `form.type` tag for kit `SearchQueryType` until the kit tags it.

| ID | Area | Deliverable |
|----|------|-------------|
| H1 | Ops / metrics | `App\Ops\Messenger\MessengerQueueHealth` counts Redis `async_ingest` / `async` / `failed` (`MessageCountAware`); Doctrine `messenger_messages` is fallback; Ops overview shows failed depth; `/metrics` exports `beacon_messenger_failed_pending` |
| H2 | Compose | Prod Messenger Redis DSNs MUST NOT use path `/messages` (path is the stream name, not a DB index) |
| H3 | Quotas | `EventQuotaUsageStore` caches daily/monthly usage in `cache.app`; seed from `EventRepository` on miss; bump on accepted Envelope writes (`032` / `018`) |
| H4 | Ingest | `ProcessEnvelopeHandler` retries flush **once** after `UniqueConstraintViolationException` (fingerprint / daily-stat races); second failure poisons as before |
| H5 | Retention | `RetentionPurger` select-then-delete by `project_id` in batches of **1000**; [docs/ops/EVENT-STORAGE.md](../../docs/ops/EVENT-STORAGE.md) records retention-first growth stages (cold table / MySQL RANGE later) |
| H6 | SSRF | `PrivateNetworkTarget` shared by `OutboundUrlGuard` and `MercureHubUrlGuard`; private IP literals + localhost-style hosts blocked by default; Mercure does **not** DNS-resolve (Docker `mercure` stays usable); cloud metadata always rejected even when `allowPrivateUrls` is on |
| H7 | API keys | `app:project:api-key-legacy-secrets` inventories Halite `secret_key`; `--apply` clears ciphertext only when `secret_hash` already exists; legacy-only keys are reported, not cleared (`096` F2) |
| H8 | Notifications | Member alert preference / event + `PushSubscription` use AuditKit `TimestampableTrait` |
| H9 | FormKit / Menu | FormKit **2.5.2**; host tags `Nowo\DashboardMenuBundle\Form\SearchQueryType` so `/admin/menus/` does not 500 (kit 2.1.9 omits the tag) |
| H10 | QA | PHPStan baseline empty; no `ignoreErrors` in `phpstan.neon.dist` (Doctrine association nullability via `allowNullablePropertyForRequiredField`); injectable Clock / `HostnameDnsLookup` / `HaliteSecretsFilesystem`; drop `phpstan-require-extends` on issue query traits; Rector semantic-only, CS-Fixer owns formatting; PHPUnit suite PHPStan-clean; includable coverage **100%** |

## Non-goals

- Automatic MySQL RANGE partitioning or `event_cold` in default migrations (operator later; EVENT-STORAGE stages 3–4)
- Object storage for Envelope payloads (Later)
- Treating Device ID as a credential (`105`)
- Replacing AuthKit login throttle (`097`)
- Enabling PHPStan `ruleset-worker-strict.neon`
- Revoking or rotating keys from `app:project:api-key-legacy-secrets` (Settings rotate / next successful ingest still upgrade `096` dual-read)
- Changing Envelope wire format or Messenger Redis stream names (`async_ingest` / `async` / `failed`)

## User Scenarios & Testing

### User Story 1 - Ops sees Redis queue and failed depth (P1)

As an instance admin, Ops overview and `/metrics` report pending ingest/async depth from Redis transports and a separate failed-transport count so poison messages are visible. Queue metrics stay instance-wide (`035`).

**Why this priority**: After Redis Messenger (`099`), Doctrine `messenger_messages` counts were wrong or empty; operators could not see failed depth.

**Independent Test**: Stub `MessageCountAware` transports; Ops overview shows pending + failed; Prometheus text includes `beacon_messenger_failed_pending`. When transports cannot count, fallback is Doctrine table or unavailable.

**Acceptance Scenarios**:

1. **Given** Redis Messenger transports, **When** I open Ops overview, **Then** pending depth is `async_ingest` + `async` message counts, labeled instance-wide.
2. **Given** messages on the failed transport, **When** I view Ops overview, **Then** I see failed depth and copy pointing operators to `messenger:failed` CLI.
3. **Given** a metrics scrape, **When** exposition is rendered, **Then** `beacon_messenger_async_pending` and `beacon_messenger_failed_pending` are present (no secrets in labels).
4. **Given** `compose.prod.yaml` Messenger DSNs, **When** Redis URL is built, **Then** the path is not `/messages` (stream names stay those in `messenger.yaml`).

### User Story 2 - Quota checks do not COUNT(*) on every ACK (P1)

As an operator under ingest burst, daily and monthly quota enforcement uses a cache seeded from stored counts and incremented on accepted writes. UTC day/month boundaries unchanged (`032` FR-004). Retention may leave a slightly high cache until expiry (fail-closed for quotas).

**Why this priority**: Repeated `COUNT(*)` on `event` under burst delays ACK (constitution: efficient ingest).

**Independent Test**: PHPUnit covers cache miss seed, increment on write, and unpersisted project falling back to repository count.

**Acceptance Scenarios**:

1. **Given** a project with a daily/monthly cap, **When** an Envelope is accepted, **Then** usage counters increment without a new full-table count on that path.
2. **Given** a cold cache, **When** the first check runs, **Then** counts seed from `EventRepository` and subsequent checks use `cache.app`.

### User Story 3 - Concurrent Envelope persist does not poison on first unique race (P1)

As the ingest worker, two concurrent messages can collide on fingerprint or daily-stat uniqueness. The handler clears the entity manager and reprocesses **once**. A second unique violation fails the message (poison / retry policy unchanged).

**Why this priority**: Burst dogfood and multi-worker Redis were turning first-collision uniqueness into failed transport noise.

**Independent Test**: Unit/handler test simulates `UniqueConstraintViolationException` on first flush and a successful second pass.

**Acceptance Scenarios**:

1. **Given** a first flush unique-constraint race, **When** the handler runs, **Then** it logs and reprocesses once after `EntityManager::clear()`.
2. **Given** a unique violation on the retry, **When** the handler runs, **Then** the exception propagates (no infinite loop).

### User Story 4 - Retention purge does not lock millions of event ids (P1)

As an operator running `app:retention:purge`, age and max-event caps delete in chunks of 1000 per project so locks stay bounded. Product retention rules (`018`) are unchanged.

**Why this priority**: Unbounded ID lists and long deletes stall replicas and backups.

**Independent Test**: `RetentionPurgerTest` covers batched deletes; EVENT-STORAGE documents batch size and later cold/partition stages.

**Acceptance Scenarios**:

1. **Given** a project over max-events or older than retention days, **When** purge runs, **Then** events are selected then deleted in batches of 1000 (or the configured batch size).
2. **Given** EVENT-STORAGE, **When** an operator plans growth, **Then** the recommended order is retention → quota cache → optional cold table → MySQL RANGE (not default migrations).

### User Story 5 - Mercure hub URLs follow the same private-target policy as webhooks (P1)

As an admin saving a Mercure hub URL, private IP literals and localhost-style hostnames are blocked by default. Docker service names remain usable because Mercure validation does not DNS-resolve. Cloud metadata hosts/IPs are always rejected, including when Ops allows private notification URLs (`084`).

**Why this priority**: Hub URL and webhook SSRF had drifted (`095` R3 vs outbound guard).

**Independent Test**: `MercureHubUrlGuard` / `PrivateNetworkTarget` / `OutboundUrlGuard` unit tests; SECURITY.md notes the shared helper.

**Acceptance Scenarios**:

1. **Given** default `allowPrivateUrls` off, **When** hub URL is `http://127.0.0.1:3000`, **Then** save/use is rejected.
2. **Given** hostname `mercure` (no resolve), **When** hub URL is validated, **Then** it is allowed (Compose service name).
3. **Given** `allowPrivateUrls` on, **When** the target is cloud metadata, **Then** it is still rejected.

### User Story 6 - Operators can drop redundant Halite API-key ciphertext (P2)

As an operator after `096` hash-at-rest, I can inventory `secret_key` rows and, with `--apply`, clear ciphertext when `secret_hash` is already present. Keys that still authenticate only via Halite ciphertext are listed and left intact.

**Why this priority**: Dual-read leftover ciphertext is recoverable secret material; clearing it must not revoke working legacy keys.

**Independent Test**: Command dry-run vs `--apply`; `ProjectApiKey` helper that only clears when hash exists.

**Acceptance Scenarios**:

1. **Given** no `--apply`, **When** I run `app:project:api-key-legacy-secrets`, **Then** I see counts of redundant vs legacy-only rows and nothing is written.
2. **Given** `--apply` and a row with both `secret_hash` and `secret_key`, **When** the command runs, **Then** ciphertext is cleared and the key still authenticates via hash.
3. **Given** a legacy-only row (ciphertext, no hash), **When** `--apply` runs, **Then** that row is not cleared (rotate in Settings or wait for ingest upgrade).

## Edge Cases

- Transports not `MessageCountAware` (e.g. sync test): health reports unavailable or Doctrine fallback; UI must not look like a hard error (`035` healthy-empty).
- Unpersisted project (no id) on quota path: skip cache, count from repository.
- Quota cache after retention delete: counter may stay high until TTL (fail-closed; may 429 until expiry).
- Unique retry after `clear()`: identity maps must be reloaded; do not flush a mixed old/new graph.
- Mercure `allowPrivateUrls` must never open link-local metadata (`169.254.169.254`, `metadata.google.internal`).
- Dashboard Menu without the host `SearchQueryType` tag: FormFactory skips `FormOptionsMerger` → HTTP 500 on `/admin/menus/`.

## Functional Requirements

- **FR-001**: Messenger queue health MUST live under `App\Ops\Messenger` and prefer transport message counts for `async_ingest`, `async`, and `failed`.
- **FR-002**: Ops overview MUST show failed-transport depth when available; `/metrics` MUST export `beacon_messenger_failed_pending`.
- **FR-003**: Production Compose Messenger Redis DSNs MUST NOT set path `/messages`.
- **FR-004**: Daily and monthly quota usage MUST be cached (`EventQuotaUsageStore` / `cache.app`); UTC boundaries unchanged; fail-closed if cache is stale-high after purge.
- **FR-005**: Envelope persist MUST retry flush at most once on unique-constraint races.
- **FR-006**: Retention event deletes MUST be batched (default 1000) per project; EVENT-STORAGE MUST document growth stages without enabling partitioning in recipes.
- **FR-007**: Mercure hub and outbound webhook guards MUST share `PrivateNetworkTarget`. Mercure MUST NOT DNS-resolve hostnames. Metadata targets MUST always be blocked.
- **FR-008**: `app:project:api-key-legacy-secrets` MUST be dry-run by default; `--apply` MUST clear `secret_key` only when `secret_hash` is present.
- **FR-009**: Member alert preference/event and push subscription timestamps MUST use AuditKit `TimestampableTrait` (not host-copied trait logic).
- **FR-010**: Host MUST tag Dashboard Menu `SearchQueryType` as `form.type` until the kit does. FormKit pin MUST be **≥ 2.5.2**.
- **FR-011**: `phpstan.neon.dist` MUST NOT rely on `ignoreErrors` or a populated baseline; `src/` and `tests/` MUST pass level 6 + FrankenPHP `rules.neon`. Process-wide sleep/DNS/umask/FS in request or test paths MUST go through injectable seams (Clock, `HostnameDnsLookup`, `HaliteSecretsFilesystem`).
- **FR-012**: Rector MUST NOT own PER-CS / import / native-function formatting (PHP-CS-Fixer does). `make qa-fix` MUST run Rector before CS-Fixer.
- **FR-013**: Includable PHPUnit statement coverage MUST remain **100%** (`033` / REQ-QA-002). PHPUnit MUST cover queue health, quota store, unique retry, batched purge, SSRF helper, legacy-secret command, and Dashboard Menu type tag behaviour.

## Key Entities

- **Messenger depths**: Instance-wide pending (`async_ingest` + `async`) and failed transport counts; not project-scoped.
- **Quota usage cache**: Per-project UTC day/month counters in `cache.app`; not a source of truth if Redis is flushed (re-seed from `event`).
- **Legacy API secret**: Halite `secret_key` ciphertext that is redundant once `secret_hash` exists.
- **Private network target**: Hostname and IP predicates shared by webhook and Mercure guards.

## Success Criteria

- **SC-001**: An admin can see Redis pending and failed Messenger depth on Ops overview without opening Redis CLI.
- **SC-002**: Ingest ACK path does not issue a full `COUNT(*)` per accepted Envelope for quota checks after the cache is warm.
- **SC-003**: A single unique-constraint race does not land the Envelope on the failed transport.
- **SC-004**: Retention purge on a large project does not delete in one unbounded statement.
- **SC-005**: Saving `http://127.0.0.1` as Mercure hub fails; saving `http://mercure:3000` can succeed.
- **SC-006**: `make phpstan` and `COVERAGE_MIN=100` pass on the includable tree without PHPStan path ignores.

## Assumptions

- Redis Messenger from `099` remains the default; Doctrine transport is leftover/fallback.
- Hash-at-rest ingest secrets (`096` F2) remain the authentication source of truth; this feature only removes redundant ciphertext.
- SiteBackup durable-done short-circuit and AuthKit kits (`105` / `056`) are unchanged.
- PHPStan FrankenPHP production gate (`094`) stays on `rules.neon`; worker-strict stays opt-in.

## Cross-links

- Prior: `018`, `032`, `033`, `035`, `038`, `084`, `087`, `091`, `094`, `095`, `096`, `099`, `081`, `101`, `105`
- Docs: [docs/ops/EVENT-STORAGE.md](../../docs/ops/EVENT-STORAGE.md), [docs/PRODUCTION.md](../../docs/PRODUCTION.md), [SECURITY.md](../../SECURITY.md)
- Kits: AuditKit, FormKit **2.5.2**, Dashboard Menu (host type tag)
