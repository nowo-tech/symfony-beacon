# Tasks: Product E2E CI sharding & suite stabilization (`113`)

## Phase 1: CI sharding

- [x] T001 Matrix jobs `E2E (Playwright) N/4` + gate `E2E (Playwright)`
- [x] T002 Per-shard artifacts + ~55m timeout + `PLAYWRIGHT_WORKERS=1`
- [x] T003 Document `ARGS='--shard=1/4'` in Makefile / `e2e/README.md`

## Phase 2: Suite isolation & mailer

- [x] T004 `PLAYWRIGHT_WORKER_SUITE` gate for `e2e/worker/` in `playwright.config.ts` + Make
- [x] T005 `PLAYWRIGHT_MAILER_DSN` in CI + `e2e/support/mailer.ts`

## Phase 3: Shared-session & BreadcrumbKit harness

- [x] T006 `exitViewAsMember` CSRF POST + `expectAuthenticatedPage` cleanup
- [x] T007 UC-ADM-08 banner-by-form + `finally` exit (no toast strict-mode)
- [x] T008 Breadcrumb full-page create/edit + `clearBreadcrumbKitJsonFields`

## Phase 4: Specs / roadmap

- [x] T009 Add `specs/113-e2e-product-sharding/`
- [x] T010 Amend `108` worker suite gating note
- [x] T011 ROADMAP Phase 6.65 + CHANGELOG Unreleased
