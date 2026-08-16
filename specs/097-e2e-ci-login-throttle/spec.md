# Feature Specification: E2E CI hardening & AuthKit login throttle (2026-08-14)

**Feature Branch**: `097-e2e-ci-login-throttle`  
**Created**: 2026-08-14  
**Status**: Implemented (v1.13.0)  
**Roadmap**: Phase 6.47  

**Input**: GitHub Actions CI red after expanded Playwright catalog (~289 tests): job timeout at 60m, IP-shared login throttle locking the suite, Mailpit/Gitleaks/Quality false failures, and remaining atomic UC-* catalog gaps.

## Summary

Operators keep per-account login throttling that matches AuthKit nested `login_form[_username]` (not a shared Compose/CI IP bucket). Maintainers get a green CI path for the full Playwright suite (longer E2E budget, Mailpit profile, host `node_modules` reclaim) and a denser E2E use-case catalog (~247 Covered).

## Scope (delivered)

| ID | Area | Deliverable |
|----|------|-------------|
| C1 | Login throttle | `AuthKitAwareLoginRateLimiter` decorates `nowo_login_throttle.database_rate_limiter`: expose nested username; clear DB attempts on successful `reset()` |
| C2 | PHPUnit | `LoginThrottleTest` asserts unrelated username is not locked after peer exhausts attempts on same IP |
| C3 | CI E2E job | `timeout-minutes: 90`; `docker compose --profile mail up -d`; Mailpit readiness wait; `PLAYWRIGHT_MAILPIT_URL`; `sudo chown` before host `pnpm` |
| C4 | Quality / secrets | Gitleaks ignore for dummy Web Push auth; PHPStan `@method addFlash` / CS / Rector `readonly` on throttle decorator |
| C5 | E2E reliability | Governance save on `/settings/general`; ingest via `loadDemoIngestCredentials()`; Mailpit soft-skip on `ECONNREFUSED`; remember-me cookie jar rebuild |
| C6 | Catalog | `e2e/flows/use-cases-atomic-gaps.spec.ts` + `docs/product/E2E-USE-CASES.md` (~247 Covered / ~5 Out of scope) |

## Non-goals

- Upstream fix inside `nowo-tech/login-throttle-bundle` (host decorator is the Beacon cut; bundle may absorb later)
- Parallel Playwright workers (`workers: 1` retained for shared DB)
- Automating Out of scope rows (empty-DB first user, SiteBackup restore, Native / dogfood UI)

## User Scenarios & Testing

### User Story 1 - Failed logins do not lock the whole instance IP (P1)

**Why this priority**: Shared CI/Compose clients all appear as one IP; AuthKit posts nested usernames the bundle limiter ignored, so five guest failures anywhere blocked every subsequent login for 15 minutes.

**Independent Test**: Exhaust five failed attempts for user A; user B’s first failed attempt still shows a normal invalid-credentials alert (not “Too many failed login attempts”).

**Acceptance Scenarios**:

1. **Given** AuthKit login form, **When** five wrong passwords for `a@example.invalid`, **Then** that account is throttled.
2. **Given** the same client IP, **When** a wrong password for `b@example.invalid`, **Then** login is not IP-throttled.
3. **Given** a successful login after prior failures for that username, **When** `reset()` runs, **Then** stored `login_attempts` for that IP+username are cleared.

### User Story 2 - Full Playwright suite finishes on GitHub Actions (P1)

**Why this priority**: Expanded UC coverage pushed wall-clock past the old 60-minute job limit even when tests were otherwise passing.

**Independent Test**: CI job `E2E (Playwright)` completes with conclusion success under `timeout-minutes: 90` with Mailpit profile started.

**Acceptance Scenarios**:

1. **Given** a push to `main`, **When** the E2E job runs, **Then** it is not cancelled solely for exceeding 60 minutes of job runtime.
2. **Given** Mailpit is up, **When** UC-ADM-33 queries the UI API, **Then** the request succeeds or soft-skips only on connection errors (no uncaught `ECONNREFUSED`).
3. **Given** Compose-owned `node_modules`, **When** the host `pnpm install` step runs, **Then** ownership is reclaimed first so install does not fail with `EACCES`.

### User Story 3 - Atomic catalog gaps are Covered in Playwright (P2)

**Why this priority**: Product-surface definition reached 100%; remaining atomic shells needed explicit UC rows and a dedicated spec file.

**Independent Test**: `make test-e2e ARGS='e2e/flows/use-cases-atomic-gaps.spec.ts'` passes against a seeded stack.

**Acceptance Scenarios**:

1. **Given** the seeded demo, **When** atomic-gap specs run, **Then** AUTH-25/26, ACC-24/25, DASH-15, OPS-12/13, SETUP-06, ADM-38..42, and PROJ-27 assert without 5xx.
2. **Given** `docs/product/E2E-USE-CASES.md`, **When** operators review status, **Then** counts reflect ~247 Covered and OPS-14 remains Out of scope.

## Dependencies

- `nowo-tech/login-throttle-bundle` (database storage + `login_attempts`)
- `nowo-tech/auth-kit-bundle` (nested `login_form[*]` fields)
- Compose profile `mail` / Mailpit (`compose.override.yaml`)
- Prior E2E catalog work (`docs/product/E2E-USE-CASES.md`)

## Assumptions

- FrankenPHP / CI clients share a small set of source IPs; per-username (plus IP) throttling is required for realistic E2E and multi-tenant guests.
- Full suite remains single-worker; time budget grows with catalog size rather than parallelism.
- Bundle-level username extraction may later replace the host decorator without changing operator-visible behaviour.

## Success Criteria

- Login throttle functional tests pass, including cross-username isolation on one IP.
- CI Quality, Coverage, Gitleaks, Git hygiene, Docker build, and E2E jobs succeed on the hardening commits.
- E2E use-case catalog documents the atomic-gap batch and updated Covered counts.

## Implementation notes

- Key type: `src/Identity/Security/AuthKitAwareLoginRateLimiter.php` (`#[AsDecorator('nowo_login_throttle.database_rate_limiter')]`).
- Workflow: `.github/workflows/ci.yml` E2E job.
- Specs file: `e2e/flows/use-cases-atomic-gaps.spec.ts`.

## Amendment (`.demo-client.env` host reclaim, 2026-08-16)

After seed writes `.demo-client.env` as container-root mode **600**, host Playwright could not read credentials (`missing/unreadable`). `make seed` / `make dogfood` MUST run `make reclaim-demo-client-env` (chown via PHP container to the host UID). Extends C3 / C5 ingest credential loading (`058` FR-009). Cross-ref: `specs/058-self-beacon-client/`.
