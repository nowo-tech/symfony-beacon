# Research: Security residual hardening (`093`)

**Date**: 2026-08-13  
**Spec**: [spec.md](./spec.md)

## R1 — Query-auth hard-delete vs flag lock

**Decision**: Hard-delete acceptance in `EnvelopeAuthParser` (no query → credentials). Always reject when `queryContainsCredentials` is true. Remove Ops checkbox + entity field (migration drops column or leaves unused column ignored — prefer drop for honesty).

**Alternatives considered**:
- Keep flag, default true, hide in prod — still bypassable; fails H1.
- Reject only when flag true but stop parsing — same as always-reject if flag removed from UI but code path remains; incomplete.

**Consequence**: Breaking for any client still using query auth (deprecated since `049`). Document in UPGRADING.

## R2 / R6 — Posture warning placement

**Decision**: Single “Security posture” callout on Ops Overview listing weakened items; reuse same helper on Instance Ops defaults form header. Metrics-off is one of the items (covers M5 without a second banner).

**Alternatives**: Force-migrate `metrics_require_token=true` — rejected (breaks scrapers; FR-006).

## R3 — Rate limiter placement

**Decision**: Shared-style `HookIpRateLimiter` + `EventSubscriber` on kernel request matching hook routes (pattern aligned with `IngestIpRateLimitSubscriber`). Config via existing RateLimiter framework factory (same cache pool as ingest if practical).

**Exclude**: `/hooks/teams/assign-me` (session + ROLE_USER) from IP throttle in v1 — document in spec US3 scenario 3.

**Defaults**: Match ingest order of magnitude (document exact numbers in implement notes from current ingest limiter config).

## R4 / R5 — Docs only

**Decision**: No Teams OpenUri shape change. Setup: document prefer `X-Setup-Token`; keep query for SiteBackup wizard compatibility unless kit config already allows header-only without breaking `make ready` / setup e2e — verify at implement time; do not break setup.

## R7 — Thin notification decoupling

**Decision**: Dispatch a Notifications-owned Messenger message after successful issue/event write inside Envelope pipeline (from writer completion or thin hook in handler). Consumer runs on `async` (messenger-notify). Name suggestion: `DispatchIngestNotificationsMessage` carrying project id + issue id(s) + event ids needed by current dispatcher.

**Alternatives**:
- Symfony EventDispatcher sync event — still couples in-process; less isolation.
- Full domain-event package — out of scope.

**Constraint**: Preserve volume threshold + status notifications; move calls currently in `ProcessEnvelopeHandler` into the new handler.

## OpenAPI / docs

Update ingest auth sections to remove query credential examples; mark removed not deprecated.
