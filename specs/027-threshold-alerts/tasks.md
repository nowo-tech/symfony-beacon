# Tasks: threshold-alerts

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

## Phase 0-1: Spec and plan

- [x] T000 Draft `spec.md`
- [x] T001 Write `plan.md`
- [x] T002 Expand `tasks.md` with implementation phases

## Phase 2: Data model and counting

- [x] T010 Add `ProjectThresholdRule` entity, repository, and project relation
- [x] T011 Create migration `Version20260721190000.php`
- [x] T012 Add `EventRepository::countReceivedSince()` for error/fatal volume windows with optional environment and release filters

## Phase 3: Notification pipeline

- [x] T020 Add `NotificationCategories::VOLUME_THRESHOLD`
- [x] T021 Add volume threshold payload builder and dispatcher methods
- [x] T022 Add `VolumeThresholdEvaluator` with cooldown and suspended-ingest guard
- [x] T023 Call evaluator from `ProcessEnvelopeHandler` only for persisted error/fatal events

## Phase 4: Settings UI

- [x] T030 Add threshold rule form and CRUD controller routes
- [x] T031 Render threshold alerts section in project Settings for owner/admin
- [x] T032 Add EN + ES translation keys for UI, payload labels, and validation messages

## Phase 5: Tests and docs

- [x] T040 Add functional test for first fire + cooldown suppression
- [x] T041 Add settings access/CRUD test for threshold rules
- [x] T042 Create/update `docs/NOTIFICATIONS.md`
- [x] T043 Update `docs/ROADMAP.md` and `docs/CHANGELOG.md`
