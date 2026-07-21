# Tasks: Notification Digest and Quiet Hours

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Persistence

- [x] T001 Migration `Version20260721164000` (quiet hours/digest columns + buffer table)
- [x] T002 Entity fields + `NotificationDigestBuffer` + repository

## Phase 2: Runtime

- [x] T003 `QuietHoursEvaluator` + buffer path in `NotificationDispatcher`
- [x] T004 `NotificationDigestFlusher` + `app:notifications:flush-digests`
- [x] T005 Form/UI fields on notification edit (no PagerDuty)

## Phase 3: Polish

- [x] T006 `NotificationDigestTest` + docs (`NOTIFICATIONS.md`, CHANGELOG)
- [x] T007 plan/tasks; ROADMAP 4.7 Done
