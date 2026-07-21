# Tasks: Admin Projects Ops

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Stats & suspend

- [x] T001 `ProjectOpsStatsService` + wire admin projects index/show
- [x] T002 Suspend/resume ingest toggle (`ingestEnabled`) + audit cases
- [x] T003 Envelope rejects when ingest disabled (shared with 018)

## Phase 2: View-as & audit

- [x] T004 Session `_beacon_view_as_member` enable/disable routes (ROLE_ADMIN)
- [x] T005 `ProjectAccessService` forces Member when flag set
- [x] T006 Banner in `base.html.twig`; `ProjectViewAsStarted` / `Ended` audit

## Phase 3: Polish

- [x] T007 Translations `en` + `es`
- [x] T008 `AdminProjectsGovernanceTest` (suspend, stats, view-as)
- [x] T009 CHANGELOG Unreleased; plan/tasks
