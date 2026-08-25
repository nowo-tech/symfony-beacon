# Feature Specification: Audit follow-up hardening (2026-08-13)

**Feature Branch**: `096-audit-follow-up-hardening`  
**Created**: 2026-08-13  
**Status**: Implemented (v1.12.0)  
**Roadmap**: Phase 6.46  

**Input**: Close High/Medium residuals from the same full-tree audit that shipped `095` / 6.45: Read API abuse surface, reversible ingest secrets at rest, `InstancePermissionVoter` affirmative-strategy bypass on `Project` subjects, panel/admin membership write-path drift, FormKit filters decoupled from query parse, Slack user-id hijack residual, and related Doctrine/OTLP DRY.

## Summary

Operators get IP rate limits on Bearer Read API, SHA-256 ingest secrets (no Halite plaintext recovery), QR login disabled until real phone OTP, tighter Slack Assign mapping, and shorter Teams/Slack interaction tokens. Maintainers get shared membership/group write services, filter DTOs / resolvers, request-scoped accessible-project cache, OTLP resource iteration helper, and explicit issue status transitions — without a hexagonal rewrite.

## Scope (delivered)

| ID | Area | Deliverable |
|----|------|-------------|
| F1 | Read API (`042`) | `BEACON_READ_API_RATE_LIMIT` + `ReadApiRateLimitSubscriber` / `ReadApiRateLimiter` (sliding window per IP; 429 + `Retry-After`) |
| F2 | Ingest credentials | `project_api_key.secret_hash` (SHA-256); plaintext only in one-shot DSN flash; legacy encrypted `secret_key` dual-read + upgrade on successful ingest |
| F3 | AuthZ | `InstancePermissionVoter` abstains when subject is `Project` so `ROLE_PROJECT_*` cannot bypass membership under affirmative strategy |
| F4 | Project membership | `ProjectMembershipPolicy` + `ProjectGroupAccessManager`; role POSTs use `ProjectMemberRoleType` / `ProjectGroupRoleType` (not CsrfOnly + raw `role`) |
| F5 | Filters | `IssueIndexFilters` / `AnalyticsFilters` / `PerformanceFilters` + resolvers; Read API `ProjectIssuesListQuery` via `MapQueryString` |
| F6 | Identity / AuthKit | `qr_login.mode: disabled` until OTP sets `phoneVerifiedAt`; Slack user ID change requires current password + uniqueness |
| F7 | Notifications | `InteractionActionToken` TTL **24h** (was 7d) for Assign OpenUri / Resolve hooks |
| F8 | Performance / Doctrine | `AccessibleProjectsProvider` request memoization; indexes `idx_issue_project_first_release`, `idx_event_issue_user_identifier` |
| F9 | Ingest OTLP / Issues | `OtlpResourceIterator`; `IssueStatusTransition`; `IssueJsonView` + normalizer helper |
| F10 | Deps | `nowo-tech/beacon-bundle` **1.7.0** |

## Amendments

### 2026-08-15 — QR enabled only under dev/test (`100`)

Default `qr_login.mode: disabled` (this feature) stayed correct for prod. Host now re-enables QR under `when@dev` / `when@test` so UC-AUTH-21/22 and local dual-device flows work without shipping QR in production. Phone UX on Account → Profile moved to `nowo-tech/phone-input-bundle` — see `specs/100-phone-input-profile/`.

## Non-goals

- Shipping SMS OTP / re-enabling QR login
- Token-scoped Read API limiter (IP only in this cut; token key optional on limiter API for later)
- Api Platform / Serializer `#[Groups]` on Read API
- Full Identity admin controller rewrite
- MySQL RANGE partitioning / `event_cold` in default migrations (operator later; `106` ships batched `RetentionPurger` + EVENT-STORAGE docs instead of streaming)

## User Scenarios & Testing

### User Story 1 - Read API storms are throttled (P1)

**Acceptance**: Exceeding `BEACON_READ_API_RATE_LIMIT` requests/minute from one IP to `/api/projects/…` returns HTTP 429 with `Retry-After: 60` and JSON `rate_limit_exceeded`. Limit `0` disables.

### User Story 2 - Ingest secrets are not recoverable from the DB (P1)

**Acceptance**: New/rotated keys store only `secret_hash`; Settings never re-shows secret except one-shot flash; legacy encrypted rows still authenticate and upgrade to hash on next successful ingest.

### User Story 3 - Instance ROLE_PROJECT_* does not open every project (P1)

**Acceptance**: User with instance catalog role containing `project.view` but **no** membership gets 403 on `#[IsGranted(ProjectPermission::VIEW, 'project')]` (voter abstain + membership voter). Covered by `InstanceRoleProjectPermissionBypassTest`.

### User Story 4 - Membership role POSTs go through Choice forms (P2)

**Acceptance**: Panel and Admin change-role flows bind `ProjectMemberRoleType` / group role Type; invalid role choice fails validation; shared policy enforces assignable roles.

## Dependencies

- Builds on `042` (Read API), `087` / `093` / `095` (security), `083` / `086` (boundaries/DRY), `090` (forms), `071` (Slack Assign mapping).

## Implementation notes

- Migrations: `Version20260813180000` (indexes), `Version20260813190000` (`secret_hash`).
- Add `BEACON_READ_API_RATE_LIMIT=120` to operator `.env` from `.env.dist`.
- Docs: SECURITY / PRODUCTION / DSN / API / ROLES / UPGRADING.

## Amendment (redundant Halite ciphertext, 2026-08-25 / `106`)

F2 dual-read stays: legacy encrypted `secret_key` still authenticates and upgrades to `secret_hash` on successful ingest. Operators can inventory and `--apply` drop **redundant** ciphertext via `app:project:api-key-legacy-secrets` without revoking legacy-only keys. See `specs/106-ops-ingest-hardening/` US6.
