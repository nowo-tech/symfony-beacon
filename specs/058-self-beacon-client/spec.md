# Feature Specification: Self Beacon Client (Dogfooding)

**Feature Branch**: `058-self-beacon-client`  
**Created**: 2026-07-29  
**Status**: Implemented (2026-07-29; Packagist + `make dogfood` + `ensure-halite-secrets` 2026-07-30; probe/`app:beacon:test` + `.env.local` DSN + reclaim client env 2026-08-16; ignore expected 403s 2026-08-16; earliest ROLE_ADMIN dogfood resolve 2026-08-17 / `103`; dogfood probe suite + explicit client tags 2026-08-17; auto `reload-env` when `BEACON_DSN` stale 2026-08-29)

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
7. **Given** `.env.local` `BEACON_DSN` differs from the php container process env (file already correct or just written), **When** `make seed` / `make ready` finishes, **Then** Make recreates php/messenger (`reload-env`) so Compose reloads `env_file` without a separate manual restart.

### User Story 3 - Dedicated dogfood Make target (Priority: P2)

As a developer whose platform catalogs already exist, I run `make dogfood` to ensure the **Symfony Beacon** dogfood project + API key exist, grant existing `ROLE_ADMIN` membership, and **re-wire** `BEACON_DSN` for `nowo-tech/beacon-bundle` **without** creating a new demo user and without re-running the full platform seed.

**Independent Test**: `make help` lists `dogfood` and `reload-env`; target ensures `var/secrets/`, invokes `app:seed-demo --skip-demo-user --sync-server-dsn`, then `reload-env-if-beacon-dsn-stale` when `.env.local` DSN ≠ container env.

**Acceptance Scenarios**:

1. **Given** a migrated DB with or without dogfood project, **When** I run `make dogfood`, **Then** `app:seed-demo --skip-demo-user --sync-server-dsn` runs and prints the self DSN.
2. **Given** `.env.local` `BEACON_DSN` differs from the php container process env (including “file already matches self DSN” but container still has a previous UUID), **When** `make dogfood` finishes, **Then** Make recreates php/messenger (`make reload-env`) so ingest uses the current project UUID — operators MUST NOT need a separate `make restart` for this case.
3. **Given** `var/secrets/` is missing, **When** I run `make dogfood`, **Then** Make creates the directory before seed so Halite key creation succeeds (`048`).
4. **Given** `BEACON_DSN` already points at an old project UUID, **When** I run `make dogfood`, **Then** `.env.local` (or `.env` fallback) is updated to the current Symfony Beacon loopback DSN (unlike plain `app:seed-demo`, which leaves a non-empty DSN unchanged).
5. **Given** file and container `BEACON_DSN` already match, **When** `make dogfood` finishes, **Then** containers are not recreated (no-op reload check).

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

### User Story 6 - Multi-event dogfood suite for issue UI (Priority: P2)

As a developer, I run `make beacon-suite` (`app:beacon:test --suite`) to create several distinct Issues (message, exception, console, HTTP, messenger, breadcrumbs, DB SQL/connection, long-content) so I can validate issue-detail panels (including Query / truncation) and client/system tags without waiting for a real production failure.

**Independent Test**: With loopback DSN loaded and workers running, `make beacon-suite` ACKs all suite kinds; Issues list shows one new issue per kind sharing `probe_run`; console issue has `extra.console.command` and client tag `console.command`.

**Acceptance Scenarios**:

1. **Given** a valid loopback `BEACON_DSN`, **When** I run `make beacon-suite` (or `app:beacon:test --suite`), **Then** ingest returns HTTP 2xx for every suite kind and the CLI prints a kind → event-id table.
2. **Given** a suite run token `T`, **When** events persist, **Then** Issues can be found via tag `probe_run=T` (and `probe_kind=<kind>`).
3. **Given** the `console` suite event, **When** I open issue detail, **Then** Highlights/Console show command `app:beacon:test` and client tags include `console.command` + `transaction`.
4. **Given** the `http` suite event, **When** I open issue detail, **Then** Request/HTTP panels show URL/route and client tags include `url` + `http.route` (+ `http.method`).
5. **Given** `make beacon-test` without `--suite`, **When** it runs, **Then** behavior remains a single ACK probe (suite is opt-in).

## Implementation (as-built, 2026-08-17)

Host wrapper `app:beacon:test` lives in `App\Ops\Command\BeaconTestCommand`. Default path still delegates to BeaconBundle `BeaconConnectionTester` + `BeaconDogfoodDiagnostics`. `--suite` is opt-in and does **not** go through the Bundle tester: it posts Envelopes with `EnvelopeTransport::sendDetailed()` (sync HTTP, same as the Bundle connection test) so `nowo_beacon.transport.mode` cannot drop or delay suite events.

| Surface | Behaviour |
|---------|-----------|
| `make beacon-test` | Single ACK probe + local VAPID / push / novelty warnings |
| `make beacon-test ARGS='--check-only'` | Parse DSN; send nothing |
| `make beacon-suite` | `bin/console app:beacon:test --suite` |
| `ARGS='--suite --run-token=T --wait=15'` | Stable fingerprints + diagnostics wait |

CLI options: `--suite`, `--run-token=` (default random hex), `--check-only` (preview kinds, no POST), `--wait=` (Messenger persist, default 10s), `--message=` (single probe only; ignored with `--suite`).

| Class | Role |
|-------|------|
| `BeaconDogfoodProbeSuite` | Build + POST one Envelope per kind; `preview()` / `run()` / `buildEnvelopeBody()` / `caseSpec()` |
| `BeaconDogfoodProbeSuiteReport` | Run token, DSN target, case rows, success flag; `diagnosticEventId()` prefers `console` |
| `BeaconDogfoodProbeCaseResult` | Per-kind HTTP status, ACK, event id, error |
| `BeaconDogfoodProbeSuiteTest` | Unit coverage for fingerprints, extras, and where-explicit client tags |

Fingerprint for every kind: `['beacon-suite', <kind>, <runToken>]`. Shared client tags: `source=dogfood.suite`, `probe_kind=<kind>`, `probe_run=<token>`. Message body: `Beacon dogfood suite [<kind>] run=<token>`.

| Kind | Level | Extra / request | Client tags (plus shared) | Issue UI to check |
|------|-------|-----------------|---------------------------|-------------------|
| `message-info` | info | `source`, `probe_kind` | `transaction=cli://app:beacon:test#message-info` | Message + level badge |
| `message-error` | error | same | `…#message-error` | Message + error badge |
| `exception` | error | `RuntimeException` stack | `transaction=cli://app:beacon:test#exception` | Stack / culprit |
| `console` | error | `extra.console` (`command=app:beacon:test`, command class, exit_code, options.suite) | `console.command`, `transaction=cli://app:beacon:test` | Highlights + Console panel |
| `http` | error | Synthetic `Request` (`project_issues_index`, GET, UA `BeaconDogfoodProbeSuite/1.0`) + `extra.http` (route, controller, status 500) | `url`, `http.route`, `http.method`, `transaction=<route>` | Request / HTTP panels |
| `messenger` | error | `extra.messenger` (DeliverWebPush message class / `async_notify`) + `extra.scheduler` | `messenger.message_class`, `transaction=messenger://…` | Messenger + Scheduler panels |
| `breadcrumbs` | warning | three `dogfood` crumbs via `BreadcrumbBuffer` | `transaction=cli://app:beacon:test#breadcrumbs` | Breadcrumbs trail |
| `db-sql` | error | MySQL unknown-column + `contexts.db` / `db.query` breadcrumb | `db.scenario`, `transaction=…#db-sql` | Query panel / SQL facts |
| `db-connection` | error | Unreachable host + `contexts.db` | `db.scenario`, `transaction=…#db-connection` | Connection / Query facts |
| `long-content` | error | Oversized SQL + filler `extra` (truncation) | `db.scenario`, `transaction=…#long-content` | Truncation / long payload UI |

Operators filter Issues by tag `probe_run=<token>` (printed by the command). Real BeaconBundle listeners still put command/URL in `extra` / request (issue UI promotes those as **system** tags); the suite additionally sets **client** tags so dogfood Issues are searchable without waiting for a production failure.

`--check-only` with `--suite` lists `BeaconDogfoodProbeSuite::KINDS` and does not POST. After ACK, diagnostics wait on the console event id when present. Workers must be running for events to persist (`make worker` / Compose `messenger`).

## Requirements

- **FR-001**: Require `nowo-tech/beacon-bundle` (**1.7.3+**) from Packagist and register configuration with empty-DSN-safe env defaults.
- **FR-002**: Demo seed MUST use stable public and secret keys; MAY write server `BEACON_DSN` to loopback when empty (prefer `.env.local`).
- **FR-003**: `make ready` MUST run bootstrap then seed; `make dogfood` MUST invoke `app:seed-demo --skip-demo-user --sync-server-dsn` (after `ensure-halite-secrets`) so server `BEACON_DSN` is re-wired to the current dogfood project; docs MUST document dogfooding and empty-DSN off switch.
- **FR-003a** (2026-08-15): `make restart` MUST recreate php/messenger containers so updated `.env.local` (including `BEACON_DSN`) is visible inside the runtime — plain Compose `restart` is insufficient.
- **FR-003b** (2026-08-29): `make seed` / `make dogfood` / `make ready` MUST call `reload-env-if-beacon-dsn-stale` after writing/syncing the server DSN: if `.env.local` `BEACON_DSN` differs from the php container process env, Make MUST recreate php/messenger (`make reload-env`, no Vite). Manual `make reload-env` and `make restart` (recreate + vite-build) remain available.
- **FR-004**: A `before_send` service MUST drop self-ingest Envelope request paths; transport MUST be async by default for the server.
- **FR-004a**: Host `nowo_beacon.ignore_exceptions` MUST exclude expected access-denial classes so dogfood issues stay signal (see Amendments 2026-08-16 — ignore expected 403s).
- **FR-005**: `composer.json` MUST NOT require a private VCS repository entry for `nowo-tech/beacon-bundle` once the package is on Packagist.
- **FR-006**: `make dogfood` / related seed Make targets MUST create `var/secrets/` before console so Halite can persist `.Halite.default.key`.
- **FR-007**–**FR-010**: See Amendments (2026-08-16) — single probe, wait diagnostics, ignore 403s, client env reclaim.
- **FR-011**–**FR-014**: See Amendments (2026-08-17) — earliest ROLE_ADMIN dogfood resolve + isolated E2E `--server-env-file`.
- **FR-015**–**FR-016** (incl. **FR-015b**): See Amendments (2026-08-17) — dogfood probe suite, `--check-only` preview, explicit where-it-happened client tags.

## Success Criteria

- **SC-001**: After `make ready` or `make dogfood`, operators can open the Symfony Beacon project and optionally receive self-reported errors when DSN is set — container `BEACON_DSN` matches `.env.local` without a separate manual recreate when the stale check fires.
- **SC-002**: Empty `BEACON_DSN` disables all client sends.
- **SC-003**: Envelope path events are never re-queued via the client `before_send`.
- **SC-003a**: Expected 403 access denials are not reported by the automatic dogfood listener (see Amendments).
- **SC-004**: Makefile help distinguishes `dogfood` from `seed` / `seed-platform`.
- **SC-005**: `make dogfood` succeeds on a fresh `var/` without a pre-existing `secrets/` directory.
- **SC-006**: See Amendments (2026-08-16) — `make beacon-test` verifies dogfood ingest without implying Web Push.
- **SC-008**: See Amendments (2026-08-17) — `make beacon-suite` creates distinct issues with actionable client tags.

## Out of scope

- Reporting PHP parse/syntax errors that kill the process before Kernel listeners run (BeaconBundle limitation).
- Auto-creating a non-demo “self” project on every boot without seed.
- BeaconBundle automatically promoting `extra.console` / request URL into **client** tags on real failures (system tags are derived in the issue UI from `extra` / request; suite sets explicit client tags for dogfood clarity).

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
- **SC-006**: `make beacon-test` / `make beacon-test ARGS='--check-only'` succeed against a loopback dogfood DSN after `make ready` / `make dogfood` (auto `reload-env` when file/container DSN diverge; manual `make reload-env` / `make restart` still valid).

**Server DSN file**

- **FR-002a**: When writing loopback `BEACON_DSN`, seed/dogfood MUST prefer writable `.env.local` (Compose `env_file`) over `.env`. Plain seed still leaves a non-empty operator DSN unchanged unless `--sync-server-dsn` / `make dogfood`.

**Client env readability (CI / host Playwright)**

- **FR-009**: After writing `.demo-client.env` (mode **600**), `make seed` / `make dogfood` MUST reclaim host ownership (`make reclaim-demo-client-env`) so the GitHub Actions / host Playwright process can read credentials. Container-root `chmod 600` alone is insufficient for E2E.

**Bundle pin**

- **FR-001** (amended): Require `nowo-tech/beacon-bundle` **1.7.3+** (connection probe command; PHP 8.2-safe DSN parser).

### 2026-08-15 — `make restart` reloads `.env.local` (`100`)

`make restart` now runs `docker compose up -d --force-recreate --no-deps php messenger messenger-notify` (not plain `restart`). Compose injects `.env.local` only on create; a soft restart left a stale `BEACON_DSN` after `make dogfood` / seed. Operator working file is `.env.local` (REQ-ENV-003). See `specs/100-phone-input-profile/` O1 / FR-006.

### 2026-08-29 — Auto `reload-env` when dogfood DSN is stale

Operators still hit ingest **401** when seed/dogfood left `.env.local` correct but the php container retained a previous project UUID (Compose `env_file` only applied on create). Manual “run `make restart`” was easy to miss after “BEACON_DSN already matches”.

- **FR-003b**: After `make seed` / `make dogfood` / `make ready`, Make MUST compare `.env.local` `BEACON_DSN` to `printenv BEACON_DSN` inside php and recreate php/messenger (`make reload-env`) when they differ. Matching values MUST skip recreate. `make reload-env` MUST recreate without Vite; `make restart` MAY still rebuild assets.
- **SC-001** (amended): Dogfood probe suite / `make beacon-test` MUST succeed after `make dogfood` without a separate manual recreate when the file/container DSN had diverged.
- Docs: `docs/DSN.md`, Makefile help, CHANGELOG/UPGRADING Unreleased.

### 2026-08-29 — Suite kinds for Query / long content (`107` dogfood)

- **FR-015** (amended): Suite kinds MUST also include `db-sql` (unknown-column + `contexts.db`), `db-connection` (unreachable host), and `long-content` (oversized SQL/extra for truncation UI). Client tags MUST include `db.scenario` for those kinds.
- Docs: `docs/DSN.md` operator matrix; CHANGELOG.

### 2026-08-17 — Dogfood earliest ROLE_ADMIN (`103`)

- **FR-011**: With `--skip-demo-user`, owner resolution MUST use `UserRepository::findFirstInstanceAdmin()` (lowest id with `ROLE_ADMIN`, excluding anonymized). `--email` and leftover `admin@symfony-beacon.local` MUST NOT override that choice.
- **FR-012**: Membership grants MUST cover every `findInstanceAdmins()` result (oldest first), not a single preferred owner only.
- **SC-007**: Integration/unit tests assert multi-admin membership and that a later `admin@…` does not become the `.demo-client.env` login hint when an earlier admin exists.
- Cross-ref: `055` FR-011a, `docs/INSTALL.md`, `docs/product/ROLES.md`.

### 2026-08-17 — Dogfood probe suite (`app:beacon:test --suite`)

Operators validating issue detail panels need more than a single `info` connection-test message (no `extra.console` / HTTP / messenger). A vague client tag `source=app:beacon:test` is not enough to show **where** the failure happened.

- **FR-015**: `app:beacon:test --suite` (Make: `beacon-suite`) MUST POST several synthetic Envelopes **synchronously** (not via `nowo_beacon.transport.mode`) for kinds `message-info`, `message-error`, `exception`, `console`, `http`, `messenger`, `breadcrumbs`, `db-sql`, `db-connection`, `long-content`. Each Envelope MUST use fingerprint `['beacon-suite', <kind>, <runToken>]` (`--run-token=` or random hex). Default `make beacon-test` without `--suite` MUST remain a single ACK probe.
- **FR-015a**: Suite events MUST carry client tags `source=dogfood.suite`, `probe_kind=<kind>`, and `probe_run=<token>` so operators can find them in Issues.
- **FR-015b**: `--suite --check-only` MUST validate the DSN and list planned kinds without POSTing. `--message=` MUST be ignored when `--suite` is set.
- **FR-016**: Suite client tags MUST be **where-explicit** by kind: `console` → `console.command` + `transaction`; `http` → `url` + `http.route` + `http.method` (+ `transaction`); `messenger` → `messenger.message_class` (+ `transaction`). Console extra MUST include `extra.console.command=app:beacon:test`. HTTP extra MUST include route `project_issues_index` and a Request on the stack. Messenger extra MUST include a message class plus optional `extra.scheduler`. Breadcrumbs kind MUST attach a short `BreadcrumbBuffer` trail. Real BeaconBundle listeners continue to put command/URL in `extra` / request (issue UI promotes those as **system** tags); the suite additionally sets client tags for dogfood clarity.
- **SC-008**: After `make beacon-suite` against a loopback dogfood DSN, ingest ACKs all suite kinds; the `console` event persists with `extra.console.command` and client tag `console.command=app:beacon:test`; the `http` event persists with client tags `url` and `http.route` (Messenger workers running).

### 2026-08-17 — Isolated E2E server-env file (`104`)

Playwright may run against a parallel Compose stack (schema `app_e2e`) that must dogfood via BeaconBundle **without** rewriting the operator `.env.local`.

- **FR-013**: `app:seed-demo` MUST accept `--server-env-file=<path>` to write the loopback `BEACON_DSN` only into that file (force update). It MUST NOT combine with `--skip-server-dsn` or `--sync-server-dsn`.
- **FR-014**: Isolated E2E Make targets MUST pass `--server-env-file=.env.e2e.local` and `--write-client-env=.demo-client.e2e.env`, then recreate the E2E containers so `BEACON_DSN` is loaded.
- Cross-ref: `specs/104-isolated-e2e-stack/`, `specs/097-e2e-ci-login-throttle/` (isolated amendment).
