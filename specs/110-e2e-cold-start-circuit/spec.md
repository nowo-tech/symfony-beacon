# Feature Specification: E2E cold-start circuit (empty DB → `/setup` → login)

**Feature Branch**: `110-e2e-cold-start-circuit`  
**Created**: 2026-09-10  
**Status**: Shipped (v1.27.0 / Phase 6.62)  
**Roadmap**: Phase 6.62  

**Input**: Product Playwright runs against a **seeded** smoke DB (`ready-e2e`), so cold SETUP / AuthKit-gated-until-setup / wizard-first-admin stayed Out of scope on the warm suite. Operators need a **serial** disposable stack that boots an empty schema, drives SiteBackup `/setup` to completion, then proves AuthKit is usable — a full install circuit from zero without destroying dogfood or warm `app_e2e`.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| C1 | Stack | Compose project `symfony-beacon-e2e-cold`, schema `app_e2e_cold`, HTTPS `:9461` / HTTP `:9086`, Redis DB `2`, `compose.e2e.cold.yaml` SiteBackup volume, env `.env.e2e.cold.local`, FrankenPHP **classic** + `RESET_KERNEL=true` |
| C2 | Make | `wipe-e2e-cold`, `up-e2e-cold`, `down-e2e-cold`, `test-e2e-cold` (parameterized `DC_E2E`; **no** `ready-e2e`) |
| C3 | Circuit | Playwright project `cold` + `e2e/cold/circuit.spec.ts` + `e2e/support/setup-cold.ts` — SETUP-07 → API `fresh_install` → admin → login |
| C4 | Catalog | UC-SETUP-01 (cold), UC-SETUP-07, UC-AUTH-10 (wizard admin) → Covered in `docs/product/E2E-USE-CASES.md` |
| C5 | CI | Job `e2e-cold` (serial, 60m timeout) |
| C6 | Host hardening | `max_execution_time=900`; `SetupAdvanceTimeLimitSubscriber`; idempotent `Version20260815231000` index create |

## Non-goals (v1)

- OPS-14 SiteBackup restore (still destructive; follow-up on this stack)
- SETUP-02 incomplete-catalog repair fixture (follow-up)
- Live OAuth IdP / browser Push permission / Later roadmap
- Running cold tests inside the parallel product suite (must stay serial + dedicated URL)
- Replacing `ready-e2e` smoke DB
- Product E2E under FrankenPHP **worker** on the cold stack (classic avoids shared request-timer edge cases during migrate/seed)

## User Scenarios & Testing

### User Story 1 - AuthKit gated until setup completes (P1)

**Independent Test**: On empty `app_e2e_cold`, `GET /login` and `/register` redirect to `/setup` without embedding `token=`.

### User Story 2 - Cold wizard completes via setup API (P1)

**Independent Test**: With `X-Setup-Token: beacon-local-setup`, loop `POST /setup/api/advance` through guided `fresh_install` (skip DB URL + sample), create admin, reach `phase=completed`. After durable done, `GET /setup/api/progress` MAY 302 to `/` (accepted).

### User Story 3 - First admin can sign in (P1)

**Independent Test**: After done, `/login` no longer redirects to setup; email/password from wizard reach `/dashboard`.

## Functional Requirements

- **FR-001**: Cold stack MUST use distinct Compose project, MySQL schema, ports, Redis DB index, env file, and SiteBackup var volume vs warm E2E.
- **FR-002**: `test-e2e-cold` MUST wipe schema (empty), start stack **without** migrate/seed Make targets, run Playwright workers=1 against cold base URL (`PLAYWRIGHT_COLD=1`).
- **FR-003**: Circuit MUST assert SETUP-07 before advance and successful login after marker.
- **FR-004**: Product `chromium` project MUST `testIgnore` `e2e/cold/` so warm runs never hit the cold URL.
- **FR-005**: Docs (`e2e/README.md`, E2E-USE-CASES, ROADMAP 6.62) MUST describe the cold circuit vs smoke DB.
- **FR-006**: Cold Make defaults MUST use FrankenPHP **classic** (and `RESET_KERNEL=true`) so long SiteBackup Process steps are not killed by a shared worker request timer.
- **FR-007**: Host MUST align web `max_execution_time` / per-`/setup` `set_time_limit` with SiteBackup `process_timeout` (900).

## Success Criteria

- `make wipe-e2e-cold && make up-e2e-cold && make test-e2e-cold` — three cold specs green.
- Warm `make test-e2e-isolated` never executes `e2e/cold/`.
- Catalog OOS no longer lists AUTH-10 / SETUP-07 (SETUP-02 / OPS-14 / Later remain).

## Cross-refs

- Setup product: `specs/056-setup-wizard/`
- Warm E2E: `specs/104-isolated-e2e-stack/`, `e2e/smoke/use-cases-setup-warm.spec.ts`
- Worker-safe (warm): `specs/108-frankenphp-worker-safe-e2e/`
- Product UI manual (setup screenshots on this stack): `specs/111-product-ui-manual/`
- Catalog: `docs/product/E2E-USE-CASES.md`
- Quickstart: `specs/110-e2e-cold-start-circuit/quickstart.md`
