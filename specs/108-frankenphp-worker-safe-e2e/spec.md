# Feature Specification: FrankenPHP worker-safe E2E verification

**Feature Branch**: `108-frankenphp-worker-safe-e2e`  
**Created**: 2026-09-10  
**Status**: Shipped (v1.26.0 / Phase 6.60)  
**Roadmap**: Phase 6.60  

**Input**: Product Playwright coverage does not prove safe behaviour under FrankenPHP `FRANKENPHP_MODE=worker` with a shared Kernel (`RESET_KERNEL=false`). Maintainers need a dedicated runtime probe, an isolated-stack harness (worker + configurable `WORKER_NUM`), a serial Kernel-isolation suite, and a CI job — distinct from the product E2E catalog.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| W1 | Health probe | `GET /health/live` returns non-secret `runtime` (`frankenphp_mode`, `frankenphp_worker`, `reset_kernel`, `app_runtime_mode`, `worker_num`) via `FrankenPhpRuntime` |
| W2 | Isolated stack defaults | `.env.e2e.dist` / Make `E2E_FRANKENPHP_*`: `MODE=worker`, `WORKER_NUM=4`, `RESET_KERNEL=false`; `ensure-e2e-env` does not inherit dogfood `classic` |
| W3 | Playwright product parallelism | `fullyParallel: true`; local **4** / CI **2** workers (`PLAYWRIGHT_WORKERS` override); Make passes the env into the Playwright container |
| W4 | Worker-safe suite | `e2e/worker/kernel-isolation.spec.ts` + `e2e/support/frankenphp.ts`; `make test-e2e-worker-safe` forces `WORKER_NUM=1` + Playwright 1 worker |
| W5 | Classic contrast | `make test-e2e-worker-safe-classic` (probe under classic; isolation tests skip) |
| W6 | Seed lite | `make ready-e2e-lite` — migrate + demo seed without `seed-sample` |
| W7 | CI | Job `e2e-worker-safe` on isolated stack (`ready-e2e-lite` + `test-e2e-worker-safe`) |
| W8 | Docs | `e2e/README.md`, `docs/ops/FRANKENPHP-CODING.md` checklist |

## Non-goals

- Replacing product E2E catalog coverage (`docs/product/E2E-USE-CASES.md`)
- Proving zero memory leaks under load (`FRANKENPHP_LOOP_MAX` remains the safety net)
- Enabling `ruleset-worker-strict.neon` (still opt-in; see `094`)
- Changing dogfood default `FRANKENPHP_MODE` in `.env.dist` (remains classic unless `make worker`)
- Multi-user preseeded storage states (`member.json` / `viewer.json`) — still ad-hoc via `loginAsUser`
- SiteBackup SQL dump/restore for smoke DB (seed-only)

## User Scenarios & Testing

### User Story 1 - Know the HTTP runtime contract from a probe (P1)

As a maintainer or CI job, I can `GET /health/live` and see whether the process is in FrankenPHP worker mode, whether the Kernel is shared (`reset_kernel` / `app_runtime_mode`), and the configured worker process count.

**Independent Test**: With E2E stack on worker + `WORKER_NUM=4` + `RESET=false`, `curl -k https://localhost:9460/health/live` shows `frankenphp_mode=worker`, `frankenphp_worker=true`, `reset_kernel=false`, `app_runtime_mode` matching `worker=1`, `worker_num=4`.

**Acceptance Scenarios**:

1. **Given** the PHP HTTP process is running, **When** `GET /health/live`, **Then** JSON includes `status=ok` and a `runtime` object with the fields above.
2. **Given** PHPUnit / KernelBrowser (no FrankenPHP worker loop), **When** `live()` runs, **Then** `frankenphp_worker` is false unless `FRANKENPHP_WORKER=1` is present in `$_SERVER`.
3. **Given** `FRANKENPHP_MODE=classic`, **When** the probe runs, **Then** `worker_num` is null (mode-gated) and `frankenphp_worker` is false.

### User Story 2 - Catch shared-Kernel leaks on the isolated stack (P1)

As a maintainer, I run a short serial Playwright suite that forces **one** PHP worker process so parallel browser contexts share the same Kernel, and asserts locale / theme / session / CSRF do not leak across contexts.

**Independent Test**: `make up-e2e && make ready-e2e-lite && make test-e2e-worker-safe` — all `e2e/worker` tests pass.

**Acceptance Scenarios**:

1. **Given** `test-e2e-worker-safe`, **When** the suite starts, **Then** E2E php is recreated with `MODE=worker`, `WORKER_NUM=1`, `RESET_KERNEL=false`, and the runtime probe matches that contract.
2. **Given** admin UI locale switched to `de`, **When** a fresh guest opens `/en/login` on the same worker process, **Then** `html[lang]` is English (not German).
3. **Given** admin theme toggled to dark, **When** a guest context loads `/login`, **Then** guest `data-theme` is not dark.
4. **Given** an ephemeral user logs in then logs out via AuthKit chrome (CSRF), **When** they open `/dashboard`, **Then** they are redirected to login.
5. **Given** an admin CSRF token from `/account/display`, **When** a guest POSTs that token, **Then** the response is not a successful authenticated mutation (3xx/4xx).

### User Story 3 - Product E2E can use four FrankenPHP workers (P2)

As a developer running the product catalog on the isolated stack, FrankenPHP defaults to **four** HTTP worker processes while Playwright may also use multiple workers; worker-safe remains a separate Make target that pins `NUM=1`.

**Independent Test**: After `make up-e2e`, `printenv FRANKENPHP_WORKER_NUM` in E2E php is `4`; `/health/live` reports `worker_num=4`. `make test-e2e-worker-safe` temporarily forces `1`.

**Acceptance Scenarios**:

1. **Given** defaults in `.env.e2e.dist`, **When** `ensure-e2e-env` runs, **Then** dogfood `.env.local` `FRANKENPHP_MODE=classic` does not overwrite E2E worker settings.
2. **Given** `playwright.config.ts`, **When** `PLAYWRIGHT_WORKERS` is unset locally, **Then** Playwright uses 4 workers with `fullyParallel: true` (CI default 2).

### User Story 4 - CI verifies worker-safe without replacing product E2E (P1)

As a maintainer, GitHub Actions runs an isolated worker-safe job in addition to the existing product E2E job (which may remain on dogfood classic).

**Independent Test**: Workflow `.github/workflows/ci.yml` contains job `e2e-worker-safe` that starts `up-e2e`, `ready-e2e-lite`, and `test-e2e-worker-safe`.

**Acceptance Scenarios**:

1. **Given** CI, **When** `e2e-worker-safe` runs, **Then** it does not require `seed-sample` and uploads Playwright artifacts on failure.
2. **Given** product job `e2e`, **When** it runs, **Then** behaviour remains `make test-e2e` on the dogfood CI stack (unchanged by this feature’s default).

## Functional Requirements

- **FR-001**: `FrankenPhpRuntime::snapshot()` MUST derive mode / worker flag / reset / `APP_RUNTIME_MODE` / `WORKER_NUM` from process `$_SERVER` (injectable for unit tests).
- **FR-002**: `HealthController::live()` MUST include `runtime` without secrets or stack traces.
- **FR-003**: Isolated E2E MUST default to `FRANKENPHP_MODE=worker`, `FRANKENPHP_RESET_KERNEL=false`, `FRANKENPHP_WORKER_NUM=4`.
- **FR-004**: `make test-e2e-worker-safe` MUST force `WORKER_NUM=1`, Playwright 1 worker, and `ARGS` scoped to `e2e/worker`.
- **FR-005**: `make test-e2e-worker-safe-classic` MUST contrast classic mode then restore worker defaults.
- **FR-006**: `make ready-e2e-lite` MUST migrate + `seed-e2e` without sample telemetry.
- **FR-007**: CI MUST include job `e2e-worker-safe` using the isolated stack.
- **FR-008**: Docs MUST state that worker-safe ≠ product catalog coverage and that Messenger `messenger` services are not FrankenPHP HTTP workers.

## Success Criteria

- PHPUnit covers `FrankenPhpRuntime` + updated `/health/live` assertions.
- `make test-e2e-worker-safe` passes locally against isolated stack.
- Static gate `094` remains the PHPStan FrankenPHP gate; this feature adds **runtime** verification only.

## Cross-refs

- Coding contract: `docs/ops/FRANKENPHP-CODING.md`
- Isolated stack base: `specs/104-isolated-e2e-stack/`
- PHPStan gate: `specs/094-phpstan-frankenphp-110/`
- Product E2E catalog: `docs/product/E2E-USE-CASES.md` (out of scope for Kernel isolation)
- Operator guide: `e2e/README.md`
