# Tasks: E2E cold-start circuit (`110`)

## Phase 1: Harness

- [x] T001 Parameterize `DC_E2E` + `ensure-e2e-env` Compose project name
- [x] T002 `compose.e2e.cold.yaml` + Make wipe/up/down/test-e2e-cold (classic + RESET)

## Phase 2: Playwright

- [x] T003 `e2e/support/setup-cold.ts` advance helper (durable-done 302)
- [x] T004 `e2e/cold/circuit.spec.ts` (SETUP-07 → wizard → login)
- [x] T005 Playwright config: `cold` project; ignore cold in `chromium`

## Phase 3: Docs / catalog / CI / host

- [x] T006 E2E-USE-CASES + e2e/README + ROADMAP 6.62 + quickstart
- [x] T007 CI job `e2e-cold`
- [x] T008 `SetupAdvanceTimeLimitSubscriber` + `max_execution_time=900` + idempotent index migration
