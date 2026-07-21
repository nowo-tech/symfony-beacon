# Feature Specification: Install & Seed Layers

**Feature Branch**: `055-install-seed-layers`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Split first-install / upgrade seeding into clear layers: (1) extract idempotent platform seed (menus/breadcrumbs) from `app:seed-demo`; (2) leave `app:seed-demo` for identity + demo project + DSN client env only; (3) add `app:seed-sample` with volume profiles for QA/load; (4) document upgrade as migrate + platform seed. Visual onboarding wizard is **out of scope** for this feature (follow-up after CLI is stable).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Platform seed on fresh install and upgrade (Priority: P1)

As an operator installing or upgrading Beacon, I run schema migrations and then a **platform seed** that upserts navigation (menus, breadcrumbs) and other product-required catalog data so the UI works without inventing demo users or fake telemetry.

**Why this priority**: Today upgrades repeatedly say “re-run `make seed`” because menus live inside the demo command. Production and empty installs need the same navigation without demo credentials.

**Independent Test**: Empty DB → migrate → platform seed → login via first-user register → Administration sidebar and breadcrumbs are present; no demo user/project required.

**Acceptance Scenarios**:

1. **Given** a migrated empty database, **When** I run platform seed, **Then** required dashboard menus and breadcrumbs exist (or are updated) for current product routes.
2. **Given** an existing instance whose menus are missing a newly shipped admin item (e.g. Mailer), **When** I run platform seed again, **Then** the missing item appears and existing customizations that share the same stable keys are updated for position/label/permission without duplicating rows.
3. **Given** a production database with real users and projects, **When** I run platform seed, **Then** no demo admin user, demo project, or sample issues/events are created.

---

### User Story 2 - Demo seed for local identity + DSN only (Priority: P1)

As a developer bootstrapping a local stack, I run **demo seed** to get a known admin account, a demo project with an API key, and a client env file for BeaconBundle sync — without relying on demo seed for menus (platform seed already ran or is invoked first).

**Why this priority**: Local pairing with BeaconBundle and README quick start depend on stable credentials and `.demo-client.env`.

**Independent Test**: After migrate + platform seed, run demo seed twice; second run is a no-op for existing user/project; client env is written/refreshed when requested.

**Acceptance Scenarios**:

1. **Given** no demo user, **When** I run demo seed with default options, **Then** a demo admin user and demo project + API key exist and membership is owner.
2. **Given** demo user/project already exist, **When** I run demo seed again, **Then** the command reports they already exist and does not wipe real data outside the demo project.
3. **Given** demo seed completes, **When** client-env write is enabled, **Then** `.demo-client.env` (or configured path) contains a Docker-ready `BEACON_DSN`.
4. **Given** demo seed runs, **When** it finishes, **Then** it does **not** create large sample issue/event volumes (that belongs to sample seed).

---

### User Story 3 - Sample data profiles for QA and load (Priority: P2)

As a developer or QA engineer, I optionally load **sample telemetry** into a designated demo (or chosen) project using named profiles (`dev`, `load`, `huge`) so lists, charts, search, and performance views can be validated at realistic scale.

**Why this priority**: Small N+1/analytics snippets are not enough to validate DataTables, FULLTEXT, analytics charts, or release health under volume.

**Independent Test**: On a project with sample marking, run `dev` profile and assert issue/event counts in documented ranges; run purge/remove sample and assert sample-tagged data is removed without deleting unrelated projects.

**Acceptance Scenarios**:

1. **Given** a target project, **When** I load profile `dev`, **Then** a modest, documented volume of sample issues/events (and related daily stats / light performance samples as specified in plan) appears within a few minutes on a normal developer machine.
2. **Given** profile `load` or `huge`, **When** I run sample seed, **Then** the command warns about time/disk and creates correspondingly larger volumes suitable for stress/QA (exact counts fixed in plan/tasks).
3. **Given** sample data exists, **When** I run sample purge for that project/profile scope, **Then** only sample-tagged data is removed; platform menus and non-sample projects remain.
4. **Given** an interactive/production-like environment without an explicit confirm/force flag, **When** I attempt `huge`, **Then** the command refuses or requires explicit confirmation so accidental prod load is hard.

---

### User Story 4 - Documented install and upgrade paths (Priority: P1)

As an operator reading README / UPGRADING, I follow a single clear recipe: migrate schema, then platform seed; optionally demo seed and sample seed.

**Why this priority**: Without docs, the split commands will not replace the current “always re-run seed-demo” habit.

**Independent Test**: README quick start and UPGRADING “next release” section mention platform seed; `make bootstrap` runs migrate + platform seed (and documents how demo/sample attach).

**Acceptance Scenarios**:

1. **Given** README quick start, **When** I follow first-install steps, **Then** I see migrate + platform seed (bootstrap), then optional demo seed and optional sample seed.
2. **Given** UPGRADING for the release that ships this feature, **When** I upgrade, **Then** I am instructed to run migrations and `app:seed-platform` (or `make seed-platform`) after pull/install.
3. **Given** Makefile help, **When** I list targets, **Then** `bootstrap`, `seed-platform`, `seed` / `seed-demo`, and `seed-sample` are distinguishable.

---

### Edge Cases

- Platform seed on a DB that never had menu tables migrated → clear failure pointing at migrations first.
- Demo seed without platform seed → still creates user/project; menus may be incomplete until platform seed runs (document order: platform then demo).
- Sample seed against a non-demo project → allowed only with an explicit project selector; default targets the demo project when present.
- Re-running platform seed after operator renamed a menu label in admin → upsert policy: stable route/permission keys win for structure fields defined by the product; document that product seed may overwrite label/position/permission for seeded keys (same behavior as today’s menu seeder sync).
- Concurrent sample seed runs → last writer wins or command uses a lock/advisory message; must not corrupt unrelated projects.
- First-user AuthKit register path remains valid on empty DB without demo seed.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide an idempotent **platform seed** command that upserts product-required navigation catalogs (dashboard menus and breadcrumbs at minimum) without creating demo users, projects, or telemetry samples.
- **FR-002**: System MUST provide a **demo seed** command (evolved from current `app:seed-demo`) limited to demo identity, demo project, API key, and optional client-env DSN write; it MUST invoke or document prerequisite platform seed so menus are not a side effect of demo-only data.
- **FR-003**: System MUST provide a **sample seed** command with named profiles (`dev`, `load`, `huge`) that generate tagged sample telemetry for validation/load testing.
- **FR-004**: Sample data MUST be identifiable (project slug and/or explicit sample marker) so purge can remove it without deleting unrelated operator data.
- **FR-005**: `make bootstrap` MUST run schema migrate + platform seed; demo and sample MUST be separate optional Make targets (or clearly optional steps after bootstrap).
- **FR-006**: Upgrade documentation MUST prescribe `migrate` + platform seed as the default post-upgrade data step for navigation catalogs (replacing “re-run seed-demo for menus”).
- **FR-007**: Platform seed MUST be safe to run repeatedly on production-like databases (no demo credentials, no sample flood).
- **FR-008**: Lightweight N+1 / analytics snippets currently created by demo seed MUST move under sample seed (at least profile `dev`) so demo seed stays identity+project+DSN focused.
- **FR-009**: English operator docs (README, UPGRADING, CHANGELOG, CONTRIBUTING or INSTALL note) MUST describe the three layers and when to use each.
- **FR-010**: Automated tests MUST cover: platform seed idempotency; demo seed create-once behavior; sample `dev` create + purge; bootstrap Make/contract smoke as appropriate for the repo’s test style.

### Key Entities

- **Platform catalog entries**: Menu items and breadcrumb definitions keyed by stable route (and locale labels where applicable).
- **Demo identity**: Known local admin user + demo project + API key used for Envelope DSN examples.
- **Sample telemetry**: Issues, events, performance rows, and/or daily stats generated for testing, marked as sample for purge.
- **Seed revision (optional)**: Record of applied platform seed version so operators/support can see whether catalogs are current (nice-to-have in plan if low cost; not blocking MVP).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new contributor completes empty-DB install to a usable logged-in UI (menus present) using migrate + platform seed + first-user register **or** demo seed, without undocumented manual SQL.
- **SC-002**: Running platform seed twice in a row produces zero duplicate menu/breadcrumb rows for the same stable keys.
- **SC-003**: After this feature ships, UPGRADING no longer requires demo seed solely to fix missing admin navigation items.
- **SC-004**: Profile `dev` sample load finishes on a developer machine in under 5 minutes and yields enough rows to exercise issue list pagination and analytics charts.
- **SC-005**: Sample purge removes sample-tagged data while leaving at least one unrelated non-sample project untouched in a multi-project test fixture.
- **SC-006**: PHPUnit (or equivalent) coverage for the three commands’ primary acceptance scenarios stays green in CI.

## Assumptions

- Schema continues to come from Doctrine migrations only (no frozen SQL dump as source of truth in this feature).
- Existing `BreadcrumbDemoSeeder` / `DashboardMenuDemoSeeder` upsert behavior is the basis for platform seed (rename/move under platform naming as needed).
- Default demo credentials remain `admin@symfony-beacon.local` / `admin123` unless overridden by options (local/dev convenience; not for production hardening).
- Visual **setup wizard UI** is deferred to a later spec after CLI layers are stable.
- `load` / `huge` exact row counts and batching strategy are deferred to plan/tasks; this spec only requires distinct profiles and safe defaults (`dev` default).
- Production Compose operators may run platform seed from the `php` service the same way they run migrations.

## Out of Scope

- Visual / HTTP onboarding wizard (empty-instance UI).
- Replacing AuthKit first-user registration.
- Dumping/restoring full MySQL snapshots as the primary install path.
- Generating sample data that mimics other tenants’ PII (use synthetic emails/messages only).
- SSO, monthly quota, or ops-overview features.
- Changing Envelope ingest protocol or DSN format.
