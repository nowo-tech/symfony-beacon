# Tasks: Project Governance

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Data model

- [x] T001 Add nullable governance columns + `ingestEnabled` on `Project`
- [x] T002 Migration `Version20260721163000`
- [x] T003 Env `BEACON_EVENT_QUOTA_DAILY` + `beacon.event_quota_daily` param

## Phase 2: Enforcement

- [x] T004 `RetentionPurger` prefers project override then env defaults
- [x] T005 `IngestRateLimiter` accepts optional per-project limit
- [x] T006 Envelope: `403` when `ingestEnabled` false; quota + rate checks via `ProjectGovernanceResolver`

## Phase 3: Settings UI & keys

- [x] T007 POST `project_governance_save` + Settings governance form (owner/admin)
- [x] T008 Revoke / rotate API key routes + buttons
- [x] T009 Approaching-quota flash (80%, once per session)

## Phase 4: Polish

- [x] T010 Translations `en` + `es`; activity keys for revoke/rotate
- [x] T011 Tests in `AdminProjectsGovernanceTest`
- [x] T012 CHANGELOG Unreleased; plan/tasks
