# Feature Specification: Self Beacon Client (Dogfooding)

**Feature Branch**: `058-self-beacon-client`  
**Created**: 2026-07-29  
**Status**: Implemented (2026-07-29; Packagist + `make dogfood` + `ensure-halite-secrets` 2026-07-30; probe/`app:beacon:test` + `.env.local` DSN + reclaim client env 2026-08-16; ignore expected 403s 2026-08-16; earliest ROLE_ADMIN dogfood resolve 2026-08-17 / `103`)

**Input**: Install `nowo-tech/beacon-bundle` in the Beacon server so the instance can report its own errors to a seeded demo project. First-run path wires a stable DSN to loopback ingest without recursive Envelope amplification.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Bundle installed for self-reporting (Priority: P1)

As an operator running Symfony Beacon, the app includes `nowo-tech/beacon-bundle` so uncaught exceptions and console failures can be sent to this same Beacon instance when `BEACON_DSN` is set.

**Independent Test**: Composer dependency present from Packagist; empty `BEACON_DSN` yields `NullBeaconClient` (no outbound traffic); non-empty DSN configures the client.

**Acceptance Scenarios**:

1. **Given** a fresh install with empty `BEACON_DSN`, **When** the kernel boots, **Then** reporting is disabled and the app runs normally.
2. **Given** `BEACON_DSN` points at the local demo project, **When** an uncaught exception occurs outside Envelope ingest, **Then** an event is accepted by ingest for that project.
3. **Given** Composer resolve, **When** `nowo-tech/beacon-bundle` is required, **Then** it installs from Packagist (no GitHub VCS `repositories` entry required).

### User Story 2 - Ready path seeds demo + server DSN (Priority: P1)

As a developer, after `make up` I run `make ready` (bootstrap + seed) and get a demo admin, demo project with stable public/secret keys, `.demo-client.env` for external clients, and server `BEACON_DSN` set to loopback (`127.0.0.1`) when it was empty.

**Independent Test**: Run seed twice; keys remain stable; server DSN line is written once when empty.

**Acceptance Scenarios**:

1. **Given** no demo project and local `dev`/`test`, **When** I run `app:seed-demo`, **Then** demo project API key uses documented stable public and secret keys.
2. **Given** `BEACON_DSN` empty in `.env` / `.env.local`, **When** seed completes, **Then** the preferred writable env file contains a loopback DSN for the demo project id (prefer `.env.local`).
3. **Given** `BEACON_DSN` already set, **When** seed runs again without `--sync-server-dsn`, **Then** the existing server DSN is left unchanged.
4. **Given** `make bootstrap`, **When** it finishes, **Then** no demo user is created (platform only; demo remains in `make seed` / `make ready` / `make dogfood`).
5. **Given** a non-local `APP_ENV`, **When** `app:seed-demo` runs without `--allow-non-local`, **Then** the command fails closed (`087` / extends `055` FR-002a). Stable DEMO_* keys MUST NOT be installed outside local.
6. **Given** seed wrote `.demo-client.env` from the PHP container, **When** `make seed` / `make dogfood` finishes, **Then** the host user can read the file (ownership reclaimed; mode 600).

### User Story 3 - Dedicated dogfood Make target (Priority: P2)

As a developer whose platform catalogs already exist, I run `make dogfood` to ensure the **Symfony Beacon** dogfood project + API key exist, grant existing `ROLE_ADMIN` membership, and **re-wire** `BEACON_DSN` for `nowo-tech/beacon-bundle` **without** creating a new demo user and without re-running the full platform seed.

**Independent Test**: `make help` lists `dogfood`; target ensures `var/secrets/`, invokes `app:seed-demo --skip-demo-user --sync-server-dsn`, and documents `make restart` when DSN was written.

**Acceptance Scenarios**:

1. **Given** a migrated DB with or without dogfood project, **When** I run `make dogfood`, **Then** `app:seed-demo --skip-demo-user --sync-server-dsn` runs and prints the self DSN.
2. **Given** `BEACON_DSN` was empty or stale (previous dogfood UUID) and seed wrote/updated it, **When** the command finishes, **Then** help text instructs `make restart` so the Kernel reloads the env.
3. **Given** `var/secrets/` is missing, **When** I run `make dogfood`, **Then** Make creates the directory before seed so Halite key creation succeeds (`048`).
4. **Given** `BEACON_DSN` already points at an old project UUID, **When** I run `make dogfood`, **Then** `.env.local` (or `.env` fallback) is updated to the current Symfony Beacon loopback DSN (unlike plain `app:seed-demo`, which leaves a non-empty DSN unchanged).

### User Story 4 - No ingest feedback loop (Priority: P1)

As an operator, failures while processing Envelope ingest must not recursively re-ingest themselves endlessly.

**Independent Test**: Unit test `before_send` drops events whose request path matches Envelope ingest; config uses async transport.

**Acceptance Scenarios**:

1. **Given** an event whose request URL/path contains `/envelope/`, **When** `before_send` runs, **Then** the event is dropped.
2. **Given** a normal dashboard exception, **When** `before_send` runs, **Then** the event is kept.
3. **Given** an `AccessDeniedException` or `AccessDeniedHttpException` (expected 403 from admin firewall / project ACL), **When** the automatic HTTP exception listener runs, **Then** the event is not reported (see Amendments — ignore expected access denials).

### User Story 5 - Probe dogfood DSN without assuming Web Push (Priority: P2)

As a developer, I run `make beacon-test` to verify Envelope auth + ACK against this instance, and I get explicit warnings when a browser push will not fire (duplicate fingerprint / no `push_subscription` / missing VAPID / Messenger lag).

**Independent Test**: With loopback DSN loaded, `make beacon-test` returns success and prints dogfood diagnostics; repeating the default message warns that the issue already existed.

**Acceptance Scenarios**:

1. **Given** a valid loopback `BEACON_DSN`, **When** I run `make beacon-test ARGS='--check-only'`, **Then** the DSN target is printed and no Envelope is sent.
2. **Given** the same DSN, **When** I run `make beacon-test`, **Then** ingest returns HTTP 2xx and diagnostics list project, VAPID, and push subscription count.
3. **Given** a repeated default probe message, **When** the event persists, **Then** diagnostics warn that the issue already existed (no “new issue” member alert).
4. **Given** zero `push_subscription` rows, **When** the probe succeeds, **Then** diagnostics warn that HTTP 200 is only an ingest ACK.

## Requirements

- **FR-001**: Require `nowo-tech/beacon-bundle` (**1.7.3+**) from Packagist and register configuration with empty-DSN-safe env defaults.
- **FR-002**: Demo seed MUST use stable public and secret keys; MAY write server `BEACON_DSN` to loopback when empty (prefer `.env.local`).
- **FR-003**: `make ready` MUST run bootstrap then seed; `make dogfood` MUST invoke `app:seed-demo --skip-demo-user --sync-server-dsn` (after `ensure-halite-secrets`) so server `BEACON_DSN` is re-wired to the current dogfood project; docs MUST document dogfooding and empty-DSN off switch.
- **FR-003a** (2026-08-15): `make restart` MUST recreate php/messenger containers so updated `.env.local` (including `BEACON_DSN`) is visible inside the runtime — plain Compose `restart` is insufficient.
- **FR-004**: A `before_send` service MUST drop self-ingest Envelope request paths; transport MUST be async by default for the server.
- **FR-004a**: Host `nowo_beacon.ignore_exceptions` MUST exclude expected access-denial classes so dogfood issues stay signal (see Amendments 2026-08-16 — ignore expected 403s).
- **FR-005**: `composer.json` MUST NOT require a private VCS repository entry for `nowo-tech/beacon-bundle` once the package is on Packagist.
- **FR-006**: `make dogfood` / related seed Make targets MUST create `var/secrets/` before console so Halite can persist `.Halite.default.key`.
- **FR-007**–**FR-010**: See Amendments (2026-08-16).

## Success Criteria

- **SC-001**: After `make ready` or `make dogfood` (+ restart if DSN written), operators can open the Symfony Beacon project and optionally receive self-reported errors when DSN is set.
- **SC-002**: Empty `BEACON_DSN` disables all client sends.
- **SC-003**: Envelope path events are never re-queued via the client `before_send`.
- **SC-003a**: Expected 403 access denials are not reported by the automatic dogfood listener (see Amendments).
- **SC-004**: Makefile help distinguishes `dogfood` from `seed` / `seed-platform`.
- **SC-005**: `make dogfood` succeeds on a fresh `var/` without a pre-existing `secrets/` directory.
- **SC-006**: See Amendments (2026-08-16) — `make beacon-test` verifies dogfood ingest without implying Web Push.

## Out of scope

- Reporting PHP parse/syntax errors that kill the process before Kernel listeners run (BeaconBundle limitation).
- Auto-creating a non-demo “self” project on every boot without seed.

## Amendments

### 2026-08-16 — Ignore expected access denials in dogfood listener

Functional / BrowserKit traffic without `ROLE_ADMIN` (and project ACL 403s) produced noisy dogfood issues while security behaved correctly.

- **FR-010**: `config/packages/nowo_beacon.yaml` MUST list at least:
  - `Symfony\Component\Security\Core\Exception\AccessDeniedException`
  - `Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException`
  under `ignore_exceptions` so the automatic HTTP/console/Messenger listeners skip them. Manual `captureException()` remains unaffected (BeaconBundle semantics).
- **SC-003a**: After config reload, repeating an unauthenticated/forbidden hit on `/admin/*` or a project ACL denial MUST NOT create a new dogfood issue for those classes.

### 2026-08-16 — Connection probe + `.env.local` + readable `.demo-client.env`

**Dogfood verify (`app:beacon:test` / `make beacon-test`)**

- **FR-007**: Operators MUST be able to probe the configured `BEACON_DSN` via BeaconBundle `nowo:beacon:test` wrapped by host `app:beacon:test` (`make beacon-test`). A successful HTTP ACK MUST NOT be documented as proof of Web Push delivery.
- **FR-008**: After ACK, `app:beacon:test` MUST wait for Messenger persistence (configurable `--wait=`) and warn when: the issue already had events (no “new issue” member alert), `push_subscription` count is zero, VAPID is unset, or the event never appears.
- **SC-006**: `make beacon-test` / `make beacon-test ARGS='--check-only'` succeed against a loopback dogfood DSN after `make ready` / `make dogfood` + recreate when needed.

**Server DSN file**

- **FR-002a**: When writing loopback `BEACON_DSN`, seed/dogfood MUST prefer writable `.env.local` (Compose `env_file`) over `.env`. Plain seed still leaves a non-empty operator DSN unchanged unless `--sync-server-dsn` / `make dogfood`.

**Client env readability (CI / host Playwright)**

- **FR-009**: After writing `.demo-client.env` (mode **600**), `make seed` / `make dogfood` MUST reclaim host ownership (`make reclaim-demo-client-env`) so the GitHub Actions / host Playwright process can read credentials. Container-root `chmod 600` alone is insufficient for E2E.

**Bundle pin**

- **FR-001** (amended): Require `nowo-tech/beacon-bundle` **1.7.3+** (connection probe command; PHP 8.2-safe DSN parser).

### 2026-08-15 — `make restart` reloads `.env.local` (`100`)

`make restart` now runs `docker compose up -d --force-recreate --no-deps php messenger messenger-notify` (not plain `restart`). Compose injects `.env.local` only on create; a soft restart left a stale `BEACON_DSN` after `make dogfood` / seed. Operator working file is `.env.local` (REQ-ENV-003). See `specs/100-phone-input-profile/` O1 / FR-006.

### 2026-08-17 — Dogfood earliest ROLE_ADMIN (`103`)

- **FR-011**: With `--skip-demo-user`, owner resolution MUST use `UserRepository::findFirstInstanceAdmin()` (lowest id with `ROLE_ADMIN`, excluding anonymized). `--email` and leftover `admin@symfony-beacon.local` MUST NOT override that choice.
- **FR-012**: Membership grants MUST cover every `findInstanceAdmins()` result (oldest first), not a single preferred owner only.
- **SC-007**: Integration/unit tests assert multi-admin membership and that a later `admin@…` does not become the `.demo-client.env` login hint when an earlier admin exists.
- Cross-ref: `055` FR-011a, `docs/INSTALL.md`, `docs/product/ROLES.md`.
