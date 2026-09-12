# Feature Specification: Product E2E CI sharding & suite stabilization

**Feature Branch**: `113-e2e-product-sharding` (shipped on `main`)  
**Created**: 2026-09-11  
**Status**: Shipped (v1.28.2 / Phase 6.65)  
**Roadmap**: Phase 6.65  

**Input**: The warm product Playwright catalog (~450 tests) exceeded practical CI wall-clock as a single job (~70m) and shared-session flakes (view-as-member, Mailpit/AuthKit mailer, BreadcrumbKit modal JSON, worker-suite bleed) made the gate unreliable. Maintainers need a sharded GitHub Actions matrix plus harness/helpers that keep each shard green without changing product behaviour.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| S1 | CI matrix | Four parallel jobs `E2E (Playwright) N/4` (`playwright test --shard=N/4`, ~55m timeout, `PLAYWRIGHT_WORKERS=1`, own Compose + Mailpit) |
| S2 | Gate | Aggregate job `E2E (Playwright)` requires all four shards (`needs` + fail-fast on any shard failure) |
| S3 | Worker suite isolation | Product `chromium` `testIgnore`s `e2e/worker/` unless `PLAYWRIGHT_WORKER_SUITE=1`; `make test-e2e-worker-safe` sets that env |
| S4 | Mailer DSN | CI `PLAYWRIGHT_MAILER_DSN=smtp://mailer:1025` (Compose profile `mail`); helper `e2e/support/mailer.ts` (`ensureDeliverableMailer`) |
| S5 | View-as-member session | Sticky `_beacon_view_as_member` cleared before authenticated asserts; UC-ADM-08 asserts banner by disable form (not toast); exit via CSRF POST |
| S6 | BreadcrumbKit writes | Ephemeral collection/item create + edit use full-page forms; clear FormKit JSON textareas that ship literal `"null"` |
| S7 | Docs | `e2e/README.md` shard instructions; this spec + ROADMAP 6.65 |

## Non-goals

- Changing cold-start (`110`) or worker-safe (`108`) job topology beyond env gating for `e2e/worker/`
- Reducing catalog coverage or moving UCs Out of scope
- Multi-browser matrix / Playwright sharding by file tags
- Fixing Dependabot npm advisory jobs (orthogonal to product CI)
- Product UX changes for view-as-member or BreadcrumbKit admin (test harness only)

## User Scenarios & Testing

### User Story 1 - CI product E2E finishes in parallel shards (P1)

As a maintainer, GitHub Actions runs the warm product suite as four shards so wall-clock stays under ~55m per job and a single flake does not re-run the entire catalog.

**Independent Test**: Push to `main` → four `E2E (Playwright) N/4` jobs + gate `E2E (Playwright)` green; artifacts `playwright-report-N-of-4` on failure.

**Acceptance Scenarios**:

1. **Given** CI workflow, **When** product E2E starts, **Then** matrix `shard ∈ {1,2,3,4}` / `shards=4` each runs `make test-e2e ARGS="--shard=N/4"`.
2. **Given** any shard failure, **When** the gate job runs, **Then** `E2E (Playwright)` fails.
3. **Given** local Make, **When** `make test-e2e ARGS='--shard=1/4'`, **Then** only that shard’s tests execute.

### User Story 2 - Worker-safe suite does not poison product chromium (P1)

As a maintainer, `make test-e2e` / CI shards never execute `e2e/worker/`, while `make test-e2e-worker-safe` still finds those tests.

**Independent Test**: Product config ignores `worker/` unless `PLAYWRIGHT_WORKER_SUITE=1`; worker-safe Make exports that flag.

**Acceptance Scenarios**:

1. **Given** default product run, **When** Playwright lists tests, **Then** `e2e/worker/` is ignored.
2. **Given** `PLAYWRIGHT_WORKER_SUITE=1`, **When** `make test-e2e-worker-safe` runs, **Then** Kernel-isolation specs execute (see `108`).

### User Story 3 - Shared PHP session cannot leave view-as-member on (P1)

As a shard sharing `storageState`, UC-ADM-08 and later authenticated tests must not leave `_beacon_view_as_member` set (blank FormKit fields / settings 403).

**Independent Test**: UC-ADM-08 enable → assert banner with disable form → exit via CSRF POST → banner absent; subsequent ACC/PROJ settings specs pass in the same shard.

**Acceptance Scenarios**:

1. **Given** enable form on admin project show (redirect often settings/403), **When** the test continues, **Then** it asserts on `/dashboard` using `[role=status]` **with** the disable form (not the success toast).
2. **Given** the disable form is present, **When** `exitViewAsMember` runs, **Then** it POSTs form fields via `page.request` and reloads (no flaky button click).
3. **Given** `expectAuthenticatedPage`, **When** the disable form is visible, **Then** it exits before assertions.

### User Story 4 - BreadcrumbKit ephemeral CRUD is reliable in CI (P2)

As a maintainer, UC-ADM-23 depth / D1 create–edit–delete collections and items without depending on modal `_modal` partial responses or FormKit `"null"` JSON textareas.

**Independent Test**: `createEphemeralBreadcrumbCollection` / item new+edit full-page paths + `clearBreadcrumbKitJsonFields` — green on shard that hosts kit admin specs.

**Acceptance Scenarios**:

1. **Given** collection create, **When** submitted from `/breadcrumb-kit-admin/collections/new`, **Then** the row appears under `?q=` (kit may redirect to Referer `/new`).
2. **Given** JSON textareas with `null` / `[]` / `{}`, **When** submitting, **Then** helpers clear them to empty string first.
3. **Given** item create/edit, **When** using full-page `/items/new` and `/items/{id}/edit`, **Then** list asserts see the label/route.

### User Story 5 - AuthKit mail flows hit Compose Mailpit in CI (P2)

As a CI job with Compose profile `mail`, Playwright configures a deliverable SMTP DSN so gated AuthKit mail routes do not soft-fail.

**Independent Test**: Workflow sets `PLAYWRIGHT_MAILER_DSN=smtp://mailer:1025`; `ensureDeliverableMailer` prefers that env over Mailpit HTTP guessing.

## Functional Requirements

- **FR-001**: Product CI MUST shard the warm Playwright suite into 4 parallel jobs with an all-green gate.
- **FR-002**: Each shard MUST use its own isolated E2E Compose stack (same pattern as pre-shard product E2E) and `PLAYWRIGHT_WORKERS=1`.
- **FR-003**: Product `chromium` MUST ignore `e2e/worker/` unless `PLAYWRIGHT_WORKER_SUITE=1`.
- **FR-004**: CI MUST set a deliverable `PLAYWRIGHT_MAILER_DSN` when the mail profile is up.
- **FR-005**: Shared-session helpers MUST clear view-as-member before authenticated page asserts; UC-ADM-08 MUST always attempt exit in `finally`.
- **FR-006**: BreadcrumbKit ephemeral write helpers MUST prefer full-page forms and clear FormKit JSON `"null"` textareas.
- **FR-007**: Docs (`e2e/README.md`, ROADMAP 6.65, CHANGELOG `[1.28.2]`) MUST describe sharding and harness rules.

## Success Criteria

- GitHub Actions run on `main` shows Quality, Coverage, Docker, Gitleaks, cold-start, worker-safe, all four product shards, and gate `E2E (Playwright)` **success**.
- Local `make test-e2e ARGS='--shard=2/4'` can reproduce shard-local failures without running the full catalog.
- Worker-safe Make still discovers `e2e/worker/` tests.

## Assumptions

- Shard assignment is Playwright’s default file/test hash (no custom shard map).
- Demo admin `storageState` remains shared within a shard; tests must not rely on sticky view-as-member.
- BreadcrumbKit modal UX remains the primary operator path in the product UI; E2E uses full-page for reliability only.

## Cross-refs

- Warm isolated stack: `specs/104-isolated-e2e-stack/`
- Worker-safe: `specs/108-frankenphp-worker-safe-e2e/`
- Cold-start: `specs/110-e2e-cold-start-circuit/`
- Create modals (admin/threshold E2E helpers): `specs/112-admin-create-modals/`
- Catalog: `docs/product/E2E-USE-CASES.md`
- Harness: `e2e/README.md`, `e2e/support/helpers.ts`, `e2e/support/mailer.ts`
- Quickstart: `specs/113-e2e-product-sharding/quickstart.md`
