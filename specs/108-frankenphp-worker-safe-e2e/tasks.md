# Tasks: FrankenPHP worker-safe E2E (`108`)

**Status**: All complete (implemented on main, 2026-09-10)

## Phase 1: Health runtime probe

- [x] T001 `FrankenPhpRuntime` + unit tests
- [x] T002 Extend `HealthController::live` + PHPUnit / functional asserts

## Phase 2: Isolated harness

- [x] T003 `.env.e2e.dist` worker defaults (`MODE`, `WORKER_NUM=4`, `RESET=false`)
- [x] T004 `ensure-e2e-env.sh` isolation + Make `E2E_FRANKENPHP_*` / `compose.e2e.yaml`
- [x] T005 Playwright multi-worker config + Make env passthrough
- [x] T006 `ready-e2e-lite`, `test-e2e-worker-safe`, `test-e2e-worker-safe-classic`

## Phase 3: Isolation suite + CI + docs

- [x] T007 `e2e/support/frankenphp.ts` + `e2e/worker/kernel-isolation.spec.ts`
- [x] T008 CI job `e2e-worker-safe`
- [x] T009 Docs: `e2e/README.md`, `docs/ops/FRANKENPHP-CODING.md`
