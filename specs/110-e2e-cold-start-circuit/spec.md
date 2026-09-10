# Feature Specification: E2E cold-start circuit (empty DB → `/setup` → login)

**Feature Branch**: `110-e2e-cold-start-circuit`  
**Created**: 2026-09-10  
**Status**: Implemented (main / Phase 6.62)  
**Roadmap**: Phase 6.62  

**Input**: Product Playwright runs against a **seeded** smoke DB (`ready-e2e`), so AUTH-10 / cold SETUP / SETUP-07 stay Out of scope. Operators need a **serial** disposable stack that boots an empty schema, drives SiteBackup `/setup` to completion, then proves AuthKit is usable — a full install circuit from zero without destroying dogfood or warm `app_e2e`.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| C1 | Stack | Compose project `symfony-beacon-e2e-cold`, schema `app_e2e_cold`, HTTPS `:9461`, Redis DB `2`, isolated `var/site-backup` volume, FrankenPHP **classic** |
| C2 | Make | `wipe-e2e-cold`, `up-e2e-cold`, `down-e2e-cold`, `test-e2e-cold` (no `ready-e2e`) |
| C3 | Circuit | Playwright `e2e/cold/` — SETUP-07 gate → token → API advance through `fresh_install` → admin → done → login |
| C4 | Catalog | Mark UC-SETUP-01 (cold), UC-SETUP-07, UC-AUTH-10 (wizard admin) Covered |
| C5 | CI | Optional job `e2e-cold` (serial, long timeout) |

## Non-goals (v1)

- OPS-14 SiteBackup restore (still destructive; follow-up on this stack)
- SETUP-02 incomplete-catalog repair fixture (follow-up)
- Live OAuth IdP / browser Push permission / Later roadmap
- Running cold tests inside the parallel product suite (must stay serial + dedicated URL)
- Replacing `ready-e2e` smoke DB

## User Scenarios & Testing

### User Story 1 - AuthKit gated until setup completes (P1)

**Independent Test**: On empty `app_e2e_cold`, `GET /login` and `/register` redirect to `/setup` without embedding `token=`.

### User Story 2 - Cold wizard completes via setup API (P1)

**Independent Test**: With `X-Setup-Token: beacon-local-setup`, loop `POST /setup/api/advance` through guided `fresh_install` (skip DB URL + sample), create admin, reach `phase=completed`.

### User Story 3 - First admin can sign in (P1)

**Independent Test**: After done, `/login` no longer redirects to setup; email/password from wizard reach `/dashboard`.

## Functional Requirements

- **FR-001**: Cold stack MUST use distinct Compose project, MySQL schema, ports, Redis DB index, and SiteBackup var volume vs warm E2E.
- **FR-002**: `test-e2e-cold` MUST wipe schema (empty), start stack **without** migrate/seed Make targets, run Playwright workers=1 against cold base URL.
- **FR-003**: Circuit MUST assert SETUP-07 before advance and successful login after marker.
- **FR-004**: Product `chromium` project MUST `testIgnore` `e2e/cold/` so warm runs never hit the cold URL.
- **FR-005**: Docs (`e2e/README.md`, E2E-USE-CASES) MUST describe the cold circuit vs smoke DB.

## Cross-refs

- Setup product: `specs/056-setup-wizard/`
- Warm E2E: `specs/104-isolated-e2e-stack/`, `e2e/smoke/use-cases-setup-warm.spec.ts`
- Worker-safe (warm): `specs/108-frankenphp-worker-safe-e2e/`
