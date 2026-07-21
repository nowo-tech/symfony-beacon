# Tasks: Delivery History

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Persistence

- [x] T001 Create `NotificationDeliveryAttempt` entity + repository
- [x] T002 Add migration `Version20260721192000` for bounded delivery history rows
- [x] T003 Add env-backed history limit (`BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT`, default 20)

## Phase 2: Delivery pipeline

- [x] T004 Add `NotificationDeliveryHistoryRecorder` service
- [x] T005 Update `DeliverNotificationHandler` to append success/failure history and prune older rows
- [x] T006 Keep `NotificationDestination` last-delivery summary fields in sync with recorded attempts

## Phase 3: UI and i18n

- [x] T007 Show expandable recent attempt history in Project Settings → Health
- [x] T008 Add English and Spanish translations for history UI

## Phase 4: Validation and docs

- [x] T009 Add PHPUnit coverage for recording + prune behaviour
- [x] T010 Extend Project Settings health UI smoke test for delivery history
- [x] T011 Mark ROADMAP 5.6 done and update CHANGELOG / UPGRADING
