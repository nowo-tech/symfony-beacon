# Tasks: Project Notifications

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Data model

- [x] T001 Create `NotificationDestinationType` enum (`slack`, `http`)
- [x] T002 Create `NotificationDestination` entity + repository + migration
- [x] T003 Cascade destinations from `Project` (orphanRemoval / onDelete CASCADE)

## Phase 2: Dispatch & delivery

- [x] T004 `DeliverNotificationMessage` + handler (HttpClient POST; Slack text vs JSON body)
- [x] T005 `NotificationDispatcher` (match enabled destinations + categories)
- [x] T006 Wire into `ProcessEnvelopeHandler` (new issue, regression, N+1)
- [x] T007 Reopen `ignored` → `unresolved` like `resolved`
- [x] T008 Route `DeliverNotificationMessage` to `async` Messenger transport

## Phase 3: Settings UI

- [x] T009 Form + controller CRUD (create/edit/toggle/delete) under project Settings
- [x] T010 Mask webhook URL in list; validate URL on save
- [x] T011 Send-test action
- [x] T012 English translations `en` + `es`

## Phase 4: Tests & docs

- [x] T013 Unit: dispatcher filter matching + occurrence rules
- [x] T014 Functional: permissions + CRUD + ingest notify (mock HttpClient)
- [x] T015 Update ROADMAP/CHANGELOG/UPGRADING/NOTIFICATIONS.md; spec In progress until tagged release
