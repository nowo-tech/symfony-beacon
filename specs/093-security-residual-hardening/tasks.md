# Tasks: Security residual hardening

**Input**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md)  
**Feature**: `093-security-residual-hardening`  
**Status**: Complete (implemented 2026-08-13)

## Phase 1: Setup

- [x] T001 Feature branch `093-security-residual-hardening`; `.specify/feature.json` points at this directory
- [x] T002 [P] Skim ingest query-auth + Ops flags + hook controllers + `ProcessEnvelopeHandler` notify calls

## Phase 2: Foundational

- [x] T003 Inventory RateLimiter config used by `IngestIpRateLimiter`
- [x] T004 [P] UPGRADING / SECURITY / PRODUCTION outlines

## Phase 3: US1 — Query-auth hard-delete (P1)

- [x] T005 [US1] Remove query credential extraction from `EnvelopeAuthParser`
- [x] T006 [US1] Always reject query credentials in `EnvelopeController`
- [x] T007 [US1] Remove `ingestRejectQueryAuth` from settings/form/portability + migration
- [x] T008 [P] [US1] Update OpenAPI / API.md / DSN.md
- [x] T009 [US1] Unit + functional tests
- [x] T010 [US1] OTLP path unchanged

## Phase 4: US2 + US4 — Security posture warning

- [x] T011 [US2] `SecurityPosture` helper
- [x] T012 [US2] Callout on Ops Overview
- [x] T013 [US4] Metrics-off in same callout + UPGRADING
- [x] T014 [US2] Functional + unit tests

## Phase 5: US3 — Hook IP rate limit

- [x] T015 [US3] `HookIpRateLimiter` + `BEACON_HOOK_IP_RATE_LIMIT`
- [x] T016 [US3] Subscriber excluding assign-me
- [x] T017 [US3] Return 429 when exceeded
- [x] T018 [US3] Unit tests for budget exhaustion

## Phase 6: US5 — Docs hygiene

- [x] T019 [P] [US5] SECURITY.md
- [x] T020 [P] [US5] PRODUCTION.md
- [x] T021 [US5] CHANGELOG / ROADMAP / UPGRADING

## Phase 7: US6 — Thin Ingest→Notifications decoupling

- [x] T022 [US6] `DispatchIngestNotificationsMessage` + handler on `async`
- [x] T023 [US6] Dispatch from `ProcessEnvelopeHandler` after flush
- [x] T024 [US6] NotificationOnIngest / related suites green
- [x] T025 [US6] `check-module-boundaries.sh` passes

## Phase 8: Polish & ship

- [x] T026 Targeted PHPUnit green for touched areas
- [x] T027 `087` non-goals cross-linked; `093` marked implemented
- [x] T028 ROADMAP 6.42 Implemented (unreleased)
