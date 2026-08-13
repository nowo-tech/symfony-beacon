# Implementation Plan: Security residual hardening

**Branch**: `093-security-residual-hardening` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)

**Status**: Draft (ready for `/speckit-implement` after tasks).

## Summary

Close High/Medium residuals **H1–M5** from the 2026-08-13 security/architecture audit: remove Envelope query-auth acceptance, surface Ops fail-open posture, rate-limit public hooks, document query-token risks, warn on metrics require-token off, and thin-decouple Ingest→Notifications via Messenger.

## Technical Context

**Language/Version**: PHP 8.3+ / Symfony 7.x  
**Primary Dependencies**: Symfony Security, RateLimiter, Messenger, Doctrine, Twig, existing Nowo kits  
**Storage**: MySQL (`instance_settings` columns may be deprecated/removed for `ingest_reject_query_auth`)  
**Testing**: PHPUnit Unit / Functional / Integration under `tests/`  
**Target Platform**: Docker Compose / FrankenPHP self-host  
**Project Type**: Modular Symfony web app (Beacon server)  
**Performance Goals**: Hook rate limit must not add meaningful latency under budget; ingest ACK path unchanged for header auth  
**Constraints**: Worker-safe; no new `env(): '…'` defaults in `parameters.yaml`; English docs/UI  
**Scale/Scope**: Six user stories; no new product surface beyond Ops warning + rate limits

## Constitution Check

- [x] Spec-first: `specs/093-security-residual-hardening/`
- [x] Canonical stack / Docker-first / worker-safe
- [x] English docs/PHPDoc/UI; tests planned per story
- [x] Env/config: no new forbidden `env()` defaults; Ops flags stay DB-backed
- [x] Prefer nowo-tech kits (SiteBackup setup token config only; no hand-rolled auth)
- [x] No Cursor attribution in commits/PRs

**Post-design**: Same gates hold. Module boundaries script must stay green after R7.

## Technical approach

| ID | Approach |
|----|----------|
| R1 | Remove query credential extraction from `EnvelopeAuthParser::parseFromRequest`; keep `queryContainsCredentials` for reject path in `EnvelopeController` (always reject when present). Remove or lock `ingestRejectQueryAuth` from `InstanceSettings` / `InstanceOpsDefaultsType` / portability allowlist (always reject). Update OpenAPI + DSN docs. |
| R2 | Compute posture DTO from `InstanceOpsDefaults`; render Callout on Ops Overview (and optionally atop Instance Ops form). Labels only — no secrets. |
| R3 | Extend or mirror `IngestIpRateLimiter` + subscriber for `/hooks/(slack\|teams\|email)` (exclude `assign-me` or document). Return 429 with Retry-After if framework allows. |
| R4–R5 | Docs-only: SECURITY.md, PRODUCTION.md (Setup rotate; Teams Assign Referer). |
| R6 | Covered by R2 when `metricsRequireToken === false`; UPGRADING bullet. |
| R7 | Introduce e.g. `EvaluateIssueNotificationsMessage` (name TBD in research) dispatched after successful Envelope write; handler in Notifications consumes on `async`. Strip `NotificationDispatcher` / threshold evaluator from `ProcessEnvelopeHandler`. Preserve existing notification PHPUnit scenarios via handler tests. |

## Project Structure

### Documentation (this feature)

```text
specs/093-security-residual-hardening/
├── spec.md
├── plan.md
├── research.md
├── quickstart.md
├── tasks.md
└── checklists/requirements.md
```

### Source Code (touch list)

```text
src/Ingest/Service/EnvelopeAuthParser.php
src/Ingest/Controller/EnvelopeController.php
src/Ingest/MessageHandler/ProcessEnvelopeHandler.php
src/Shared/Settings/Entity/InstanceSettings.php
src/Shared/Settings/Form/InstanceOpsDefaultsType.php
src/Shared/Settings/Service/InstanceOpsDefaults.php
src/Shared/Settings/Service/InstanceConfigPortability.php  # if flag removed
src/Ops/… or Shared/  # posture warning Twig/controller
src/Notifications/…    # rate limit + notification trigger handler
config/packages/…      # rate limiter if needed
docs/SECURITY.md
docs/PRODUCTION.md
docs/UPGRADING.md
docs/API.md / OpenAPI
tests/Unit|Functional|Integration/…
```

**Structure Decision**: Keep posture chrome under Ops/Shared presentation; rate limit under Notifications (hook owners) or Shared EventSubscriber keyed by route — prefer Notifications to avoid Shared growth. Notification trigger message lives in Notifications; Ingest only dispatches the message DTO.

## Phase 0 / 1 artifacts

- [research.md](./research.md) — decisions for flag removal vs lock, limiter placement, message shape
- [quickstart.md](./quickstart.md) — verify commands for implementers
- No new DB entity required beyond optional column drop migration for `ingest_reject_query_auth` (see research)

## Test plan

| Story | Tests |
|-------|--------|
| US1 | Unit parser; functional Envelope query-only → reject; header OK |
| US2 | Functional Ops Overview with flags toggled |
| US3 | Functional/unit hook rate limit → 429 |
| US4 | Covered by US2 + UPGRADING review |
| US5 | Doc review (manual checklist) |
| US6 | Unit/integration: message dispatched; handler covers notify path; boundaries script |

## Upgrade notes (operator-facing draft)

1. Envelope query-string auth **removed** — update clients to `X-Beacon-Auth` / DSN-in-envelope.
2. Enable **metrics require token** if still off (Ops warning).
3. Hook endpoints may return **429** under abuse — scrapers/monitoring of hooks should back off.
4. Optional: rotate `SITE_SETUP_TOKEN` after first setup.
