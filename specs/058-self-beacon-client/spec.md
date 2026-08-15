# Feature Specification: Self Beacon Client (Dogfooding)

**Feature Branch**: `058-self-beacon-client`  
**Created**: 2026-07-29  
**Status**: Implemented (2026-07-29; Packagist + `make dogfood` + `ensure-halite-secrets` 2026-07-30)

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
2. **Given** `BEACON_DSN` empty in `.env`, **When** seed completes, **Then** `.env` contains a loopback DSN for the demo project id.
3. **Given** `BEACON_DSN` already set, **When** seed runs again, **Then** the existing server DSN is left unchanged.
4. **Given** `make bootstrap`, **When** it finishes, **Then** no demo user is created (platform only; demo remains in `make seed` / `make ready` / `make dogfood`).
5. **Given** a non-local `APP_ENV`, **When** `app:seed-demo` runs without `--allow-non-local`, **Then** the command fails closed (`087` / extends `055` FR-002a). Stable DEMO_* keys MUST NOT be installed outside local.

### User Story 3 - Dedicated dogfood Make target (Priority: P2)

As a developer whose platform catalogs already exist, I run `make dogfood` to ensure the **Symfony Beacon** dogfood project + API key exist, grant existing `ROLE_ADMIN` membership, and **re-wire** `BEACON_DSN` for `nowo-tech/beacon-bundle` **without** creating a new demo user and without re-running the full platform seed.

**Independent Test**: `make help` lists `dogfood`; target ensures `var/secrets/`, invokes `app:seed-demo --skip-demo-user --sync-server-dsn`, and documents `make restart` when DSN was written.

**Acceptance Scenarios**:

1. **Given** a migrated DB with or without dogfood project, **When** I run `make dogfood`, **Then** `app:seed-demo --skip-demo-user --sync-server-dsn` runs and prints the self DSN.
2. **Given** `BEACON_DSN` was empty or stale (previous dogfood UUID) and seed wrote/updated it, **When** the command finishes, **Then** help text instructs `make restart` so the Kernel reloads the env.
3. **Given** `var/secrets/` is missing, **When** I run `make dogfood`, **Then** Make creates the directory before seed so Halite key creation succeeds (`048`).
4. **Given** `BEACON_DSN` already points at an old project UUID, **When** I run `make dogfood`, **Then** `.env` is updated to the current Symfony Beacon loopback DSN (unlike plain `app:seed-demo`, which leaves a non-empty DSN unchanged).
### User Story 4 - No ingest feedback loop (Priority: P1)

As an operator, failures while processing Envelope ingest must not recursively re-ingest themselves endlessly.

**Independent Test**: Unit test `before_send` drops events whose request path matches Envelope ingest; config uses async transport.

**Acceptance Scenarios**:

1. **Given** an event whose request URL/path contains `/envelope/`, **When** `before_send` runs, **Then** the event is dropped.
2. **Given** a normal dashboard exception, **When** `before_send` runs, **Then** the event is kept.

## Requirements

- **FR-001**: Require `nowo-tech/beacon-bundle` (^1.6.7) from Packagist and register configuration with empty-DSN-safe env defaults.
- **FR-002**: Demo seed MUST use stable public and secret keys; MAY write server `BEACON_DSN` to loopback when empty.
- **FR-003**: `make ready` MUST run bootstrap then seed; `make dogfood` MUST invoke `app:seed-demo --skip-demo-user --sync-server-dsn` (after `ensure-halite-secrets`) so server `BEACON_DSN` is re-wired to the current dogfood project; docs MUST document dogfooding and empty-DSN off switch.
- **FR-004**: A `before_send` service MUST drop self-ingest Envelope request paths; transport MUST be async by default for the server.
- **FR-005**: `composer.json` MUST NOT require a private VCS repository entry for `nowo-tech/beacon-bundle` once the package is on Packagist.
- **FR-006**: `make dogfood` / related seed Make targets MUST create `var/secrets/` before console so Halite can persist `.Halite.default.key`.

## Success Criteria

- **SC-001**: After `make ready` or `make dogfood` (+ restart if DSN written), operators can open the Symfony Beacon project and optionally receive self-reported errors when DSN is set.
- **SC-002**: Empty `BEACON_DSN` disables all client sends.
- **SC-003**: Envelope path events are never re-queued via the client `before_send`.
- **SC-004**: Makefile help distinguishes `dogfood` from `seed` / `seed-platform`.
- **SC-005**: `make dogfood` succeeds on a fresh `var/` without a pre-existing `secrets/` directory.

## Out of scope

- Reporting PHP parse/syntax errors that kill the process before Kernel listeners run (BeaconBundle limitation).
- Auto-creating a non-demo “self” project on every boot without seed.
