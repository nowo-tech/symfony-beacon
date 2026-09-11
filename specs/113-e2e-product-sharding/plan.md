# Implementation Plan: Product E2E CI sharding & suite stabilization (`113`)

**Branch**: `main` (Unreleased)  
**Spec**: `specs/113-e2e-product-sharding/spec.md`

## Technical approach

1. **CI matrix** (`.github/workflows/ci.yml`)
   - Replace monolithic product E2E job with `strategy.matrix.shard: [1,2,3,4]` / `shards: [4]`.
   - Per shard: isolated Compose + Mailpit, `PLAYWRIGHT_WORKERS=1`, `PLAYWRIGHT_MAILER_DSN=smtp://mailer:1025`, `make test-e2e ARGS="--shard=N/4"`.
   - Gate job `E2E (Playwright)` `needs` all shards; fail if any `failure`/`cancelled`.
   - Artifacts: `playwright-report-N-of-4`.

2. **Playwright config**
   - `PLAYWRIGHT_WORKER_SUITE=1` → do **not** ignore `e2e/worker/`; otherwise `testIgnore` includes `**/worker/**` (and cold/manual as before).
   - Make `test-e2e-worker-safe{,-classic}` exports `PLAYWRIGHT_WORKER_SUITE=1`.

3. **Mailer helper**
   - `e2e/support/mailer.ts`: `ensureDeliverableMailer` prefers `PLAYWRIGHT_MAILER_DSN`, else Mailpit HTTP / defaults.
   - AuthKit gated routes call the helper before asserting mail-dependent UI.

4. **View-as-member**
   - `exitViewAsMember`: collect disable form inputs → `page.request.post` → reload.
   - `expectAuthenticatedPage`: exit when disable form present.
   - UC-ADM-08: after enable, goto `/dashboard`; assert `[role=status]` **has** disable form; `finally` exit; re-check banner count 0.

5. **BreadcrumbKit**
   - `clearBreadcrumbKitJsonFields` clears empty/`null`/`[]`/`{}` textareas matching Json/Params/… names.
   - `createEphemeralBreadcrumbCollection`: full-page `/collections/new` → search row for id.
   - `openNewBreadcrumbItemForm`: full-page `/items/new`; depth specs edit via `/items/{id}/edit`.

## Dependencies

- `104` isolated E2E stack
- `108` worker-safe suite (gated, not removed)
- `097` / AuthKit mail + throttle catalog
- BreadcrumbKit admin dashboard (vendor forms)

## Risks

- Playwright shard hash can move tests between shards after file renames — treat shard-local green as provisional.
- CSRF-only disable form field names must match FormKit / `csrf_action_form` markup for POST exit.
- Full-page BreadcrumbKit create still follows `redirectToRefererOr` back to `/new`; assert via `?q=` index, not edit URL.
