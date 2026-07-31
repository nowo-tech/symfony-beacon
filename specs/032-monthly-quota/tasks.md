# Tasks: Monthly Event Quota (`032`)

**Input**: `specs/032-monthly-quota/spec.md`  
**Status**: Implemented

## Phase 1: Data & resolver

- [x] T001 `Project.eventQuotaMonthly` + migration `event_quota_monthly`
- [x] T002 Env `BEACON_EVENT_QUOTA_MONTHLY` + `beacon.event_quota_monthly`
- [x] T003 `ProjectGovernanceResolver` monthly effective / count / approach / exceed (UTC month)

## Phase 2: Enforce & UI

- [x] T004 Envelope `429 monthly event quota exceeded` + worker drop
- [x] T005 Settings governance field + usage line + 80% flash
- [x] T006 i18n keys (EN + locale parity placeholders)

## Phase 3: Quality

- [x] T007 PHPUnit: worker monthly drop; Settings save + approaching warning
- [x] T008 UPGRADING / CHANGELOG / ROADMAP / DATABASE.md
