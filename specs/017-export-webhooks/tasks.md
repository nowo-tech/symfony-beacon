# Tasks: 017-export-webhooks

**Input**: [plan.md](./plan.md), [spec.md](./spec.md)

**Prerequisites**: plan.md, spec.md

## Phase 1: Lifecycle notification categories

- [x] T001 Extend `NotificationCategories` with `issue.resolved`, `issue.reopened`, `issue.assigned`, `issue.commented`, `issue.duplicated`
- [x] T002 [P] Add payload builders for lifecycle events in `NotificationPayloadBuilder`
- [x] T003 Add dispatcher methods mirroring `dispatchNewIssue` (match on lifecycle category)
- [x] T004 [P] EN/ES translation keys for new categories

## Phase 2: Dispatch from issue actions

- [x] T005 Wire `NotificationDispatcher` into `IssueController` (status → resolved/reopened, assign, comment, duplicate)

## Phase 3: Export endpoints

- [x] T006 Add `EventRepository` filtered export query (join issue; environment/release/level/status/q)
- [x] T007 Create `ProjectExportController` — issues/events CSV (`StreamedResponse`) + JSON; Admin+; filter parity; 1000-row cap

## Phase 4: Docs, tests, polish

- [x] T008 Document lifecycle events briefly in `docs/NOTIFICATIONS.md`
- [x] T009 Changelog Unreleased bullets (English)
- [x] T010 `tests/Export/ExportWebhooksTest.php` (export + lifecycle webhook delivery)
- [x] T011 Extend `NotificationDispatcherTest` for a lifecycle category
- [x] T012 Mark plan/tasks complete; run PHPUnit via Docker
