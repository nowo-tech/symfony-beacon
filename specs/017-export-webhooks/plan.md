# Implementation Plan: Export and Lifecycle Webhooks

**Branch**: `017-export-webhooks` | **Date**: 2026-07-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/017-export-webhooks/spec.md`

## Summary

Owner/admin CSV and JSON export of filtered issues and related events (streamed CSV, capped). Extend project notification categories with lifecycle event types (`issue.resolved`, `issue.reopened`, `issue.assigned`, `issue.commented`, `issue.duplicated`) and dispatch them from issue workflow actions via the existing `NotificationDispatcher` / Messenger pipeline.

## Technical Context

**Language/Version**: PHP 8.3+ / Symfony 7

**Primary Dependencies**: Doctrine ORM, Symfony HttpFoundation (`StreamedResponse` / `JsonResponse`), Messenger async delivery (sync in test)

**Storage**: No new tables; reuses `notification_destination.categories` JSON

**Testing**: PHPUnit `ExportWebhooksTest` (functional export + lifecycle HTTP delivery)

**Target Platform**: Self-hosted Beacon (Docker Compose + FrankenPHP)

**Project Type**: Symfony web application

**Performance Goals**: Export up to 1,000 filtered rows under 30s in acceptance; CSV streamed to the client

**Constraints**: Export requires project Admin (or Owner); webhook SSRF / encryption unchanged from `009-project-notifications`; English docs/PHPDoc

**Scale/Scope**: Per-project export and destinations; lifecycle categories opt-in (not in default destination categories)

## Constitution Check

- English documentation / PHPDoc / Twig copy
- Reuse notification outbound stack (no new delivery transport)
- No Cursor commit attribution

## Project Structure

### Documentation (this feature)

```text
specs/017-export-webhooks/
├── plan.md
├── tasks.md
├── spec.md
└── checklists/requirements.md
```

### Source Code

```text
src/Notifications/NotificationCategories.php
src/Notifications/Service/NotificationPayloadBuilder.php
src/Notifications/Service/NotificationDispatcher.php
src/Issues/Controller/IssueController.php
src/Project/Controller/ProjectExportController.php
src/Issues/Repository/EventRepository.php          # filtered event export query
docs/NOTIFICATIONS.md
docs/CHANGELOG.md
translations/messages.en.yaml
translations/messages.es.yaml
tests/Export/ExportWebhooksTest.php
tests/Notifications/NotificationDispatcherTest.php
```

## Implementation Decisions

1. **Export routes** — `GET /projects/{uuid}/export/issues.{csv|json}` and `events.{csv|json}`; `ProjectAccessService::requireRole(..., Admin)`; same list filters as issue index where applicable (q, level, status, environment, release, priority, assignee); events join issue and apply environment/release/level/status/q; hard cap 1,000 rows; CSV via `StreamedResponse` + `fputcsv`.
2. **Lifecycle categories** — add string constants to `NotificationCategories::ALL` so destination forms expose them; dispatch matches on category = event type (not issue level).
3. **Dispatch hooks** — `IssueController` status / assign / comment / duplicate call new `NotificationDispatcher` methods after a successful change; payload includes prior/new assignee and duplicate canonical ids when relevant.
4. **Delivery** — unchanged `DeliverNotificationMessage` + retry / SSRF guards.

## Complexity Tracking

| Deferred | Why |
|----------|-----|
| Native PagerDuty | Spec / roadmap → `020-notification-digest` |
| Full event payload dump in export | Spec: list-visible fields only |
| Export UI buttons | Routes + tests sufficient for v1; index links optional follow-up |
