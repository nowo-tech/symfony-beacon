# Feature Specification: Isolated Playwright E2E stack

**Feature Branch**: `104-isolated-e2e-stack`  
**Created**: 2026-08-17  
**Status**: Done (v1.21.0 / Phase 6.55)  
**Roadmap**: Phase 6.55  

**Input**: Local `make test-e2e` mutates the dogfood MySQL schema (`MYSQL_DATABASE`). Operators need a parallel Compose project on schema `app_e2e` so development data stays clean, while optionally dogfooding test-time errors via BeaconBundle.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| I1 | Compose | `compose.e2e.yaml` + versioned `.env.e2e.dist` → generated `.env.e2e.local` (gitignored); project `symfony-beacon-e2e` (`-p`); ports `:9085` / `:9460`; Redis DB index `1`; named volumes for `var/cache` / `var/log` |
| I2 | Make | `up-e2e` / `ready-e2e` / `seed-e2e` / `test-e2e-isolated` / `down-e2e`; force process env for ports so a sourced `.env.local` cannot steal bindings |
| I3 | Seed | `app:seed-demo --server-env-file=.env.e2e.local` writes loopback `BEACON_DSN` only there; `--write-client-env=.demo-client.e2e.env`; never rewrite dogfood `.env.local` |
| I4 | Playwright | `PLAYWRIGHT_ISOLATED=1` → base URL `:9460`, auth `e2e/.auth/admin.e2e.json`, credentials from `.demo-client.e2e.env` |
| I5 | Dogfood | `E2E_BEACON_TARGET=self\|dogfood\|off` — E2E BeaconBundle reports into `app_e2e` (default), dogfood project, or nowhere |

## Non-goals

- Changing CI default (`make test-e2e` still seeds the ephemeral CI dogfood DB)
- Parallel Playwright workers
- Automating Out of scope UC rows
- Doctrine migrations / production operator steps

## User Scenarios & Testing

### User Story 1 - Keep dogfood DB clean while running Playwright (P1)

As a developer with HTTPS `:9447` and a populated dogfood schema, I run isolated E2E without creating ephemeral users/projects in my development database.

**Independent Test**: After `make ready-e2e` + a smoke suite, `COUNT(*)` on dogfood `user` is unchanged; `app_e2e.user` holds the demo admin.

**Acceptance Scenarios**:

1. **Given** dogfood is up, **When** `make up-e2e && make ready-e2e`, **Then** MySQL schema `app_e2e` is migrated and seeded; Compose project `symfony-beacon-e2e` listens on `:9460`.
2. **Given** that stack, **When** `make test-e2e-isolated ARGS='e2e/smoke/public.spec.ts'`, **Then** tests pass against `:9460` and dogfood `:9447` remains healthy.
3. **Given** `make down-e2e`, **When** containers stop, **Then** schema `app_e2e` is retained for the next run.

### User Story 2 - Detect errors during tests via BeaconBundle (P1)

As a developer, exceptions / Monolog `error+` on the app under test appear as Beacon issues without rewriting dogfood `BEACON_DSN` in `.env.local`.

**Independent Test**: `nowo:beacon:test --check-only` inside the E2E `php` container reports reporting enabled with the `app_e2e` project UUID (target `self`).

**Acceptance Scenarios**:

1. **Given** `E2E_BEACON_TARGET=self` (default) after `ready-e2e`, **When** the E2E container boots, **Then** `BEACON_DSN` is the loopback self DSN for the `app_e2e` Symfony Beacon project.
2. **Given** `E2E_BEACON_TARGET=dogfood`, **When** env is regenerated, **Then** E2E containers use `.demo-client.env` client DSN (issues land on `:9447`).
3. **Given** `E2E_BEACON_TARGET=off`, **When** env is regenerated, **Then** E2E `BEACON_DSN` is empty.

### User Story 3 - Seed must not clobber dogfood Compose env (P1)

As an operator, isolated seed must not rewrite `.env.local` `BEACON_DSN` or recreate the dogfood Compose project.

**Independent Test**: `app:seed-demo --server-env-file=.env.e2e.local` updates only that file; `docker compose -p symfony-beacon-e2e …` never recreates `symfony-beacon-php-1`.

**Acceptance Scenarios**:

1. **Given** `--server-env-file=.env.e2e.local`, **When** seed runs, **Then** `.env.local` is unchanged and `.env.e2e.local` gets the loopback DSN.
2. **Given** Make `DC_E2E`, **When** `up` / `force-recreate` runs, **Then** project name is `symfony-beacon-e2e` (`-p`) and published ports are `E2E_HTTP_PORT` / `E2E_HTTPS_PORT`.

## Functional Requirements

- **FR-001**: Makefile MUST expose `up-e2e`, `ready-e2e`, `seed-e2e`, `test-e2e-isolated`, `down-e2e` (and helpers `ensure-e2e-env` / `ensure-e2e-db`).
- **FR-002**: Isolated stack MUST use a distinct MySQL schema (default `app_e2e`), Redis DB index (default `1`), Compose project name (`symfony-beacon-e2e`), and host ports (defaults `9085` / `9460`).
- **FR-003**: `app:seed-demo` MUST support `--server-env-file` (write loopback `BEACON_DSN` only there) and MUST remain mutually exclusive with `--skip-server-dsn` / `--sync-server-dsn`.
- **FR-004**: Playwright isolated mode MUST use separate storage state and `.demo-client.e2e.env` credentials.
- **FR-005**: `E2E_BEACON_TARGET` MUST select self / dogfood / off reporting for E2E containers without mutating dogfood `.env.local`.
- **FR-006**: Docs (`e2e/README.md`, CONTRIBUTING, INSTALL, UPGRADING, CHANGELOG) MUST describe the parallel-stack workflow.

## Success Criteria

- Dogfood and E2E HTTPS health checks both return 200 while coexisting.
- Smoke Playwright suite passes under `make test-e2e-isolated`.
- `nowo:beacon:test --check-only` succeeds in the E2E `php` service when target is `self`.

## Amendments

### 2026-08-17 — Morphicons chrome (v1.21.0)

Lucide-driven spring morphs for theme toggle, burger/sidebar, content-width, and password reveal (Vitest `assets/lib/morphicons/`; E2E UC-ACC-26). Does not change Compose isolation.

### 2026-08-20 — Messenger Redis DB index (v1.23.3 / REQ-MESSENGER-001)

`MESSENGER_TRANSPORT_DSN` MUST use Redis `?dbindex=N` (the URL path is the **stream name**, not the DB index). `compose.e2e.yaml` MUST use `env_file: !override` and MUST NOT re-interpolate `DATABASE_URL` / `REDIS_URL` / `MESSENGER_TRANSPORT_DSN` / `BEACON_DSN` from a polluted process environment. Make `DC_E2E` forces Redis DSNs on the process env so isolated workers do not share dogfood streams.

### 2026-08-25 — Device collect on E2E/SUT (`105`)

Isolated stack inherits `/_device` PUBLIC_ACCESS + PWA deny-cache. No extra Compose service. See `specs/105-authkit-security-kits/`.

### 2026-08-26 — `.env.e2e.dist` template

Isolated E2E MUST version `.env.e2e.dist` (sibling of `.env.dist`). Working file `.env.e2e.local` MUST stay gitignored. `ensure-e2e-env.sh` copies the dist template, overlays shared infra / secrets from `.env.local` (not isolation keys), then applies Make `E2E_*` overrides. Never commit `.env.local` or `.env.e2e.local`.
