# Implementation Plan: Issue Workflow

**Branch**: `015-issue-workflow` | **Date**: 2026-07-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/015-issue-workflow/spec.md`

## Summary

Add issue triage workflow: priority (`low`|`medium`|`high`|`critical`, default `medium`), plain-text comments, mark-as-duplicate (self-FK + status ignored), and per-user saved list views. Full event-stream merge is deferred; merge-later relationships are out of this release.

## Technical Context

**Language/Version**: PHP 8.3+ / Symfony 7

**Primary Dependencies**: Doctrine ORM, Twig, Symfony Security / CSRF forms

**Storage**: MySQL/MariaDB via Doctrine migrations (MDK where applicable)

**Testing**: PHPUnit `DatabaseWebTestCase` functional coverage in `tests/Issues/IssueWorkflowTest.php`

**Target Platform**: Self-hosted Beacon (Docker Compose + FrankenPHP)

**Project Type**: Symfony web application

**Performance Goals**: Comment + priority + duplicate actions complete within normal request latency; list filters remain SQL-backed

**Constraints**: Project membership via `ProjectAccessService`; English UI/docs/PHPDoc; CSRF on mutating forms

**Scale/Scope**: Per-project issues; saved views scoped per user + project

## Constitution Check

- English documentation / PHPDoc / Twig copy
- Prefer existing Issues patterns (`IssueController`, history recorder, UserAction) over new kits
- No Cursor commit attribution

## Project Structure

### Documentation (this feature)

```text
specs/015-issue-workflow/
├── plan.md
├── tasks.md
├── spec.md
└── checklists/requirements.md
```

### Source Code

```text
src/Shared/IssuePriority.php
src/Issues/Entity/Issue.php                    # priority, duplicateOf
src/Issues/Entity/IssueComment.php
src/Issues/Entity/IssueSavedView.php
src/Issues/Repository/IssueCommentRepository.php
src/Issues/Repository/IssueSavedViewRepository.php
src/Issues/Repository/IssueRepository.php      # priority filter
src/Issues/Controller/IssueController.php      # priority, comment, duplicate, views
migrations/Version20260721161000.php           # priority + comments + duplicateOf
migrations/Version20260721162000.php           # issue_saved_view
templates/issue/show.html.twig
templates/issue/index.html.twig
translations/messages.en.yaml
translations/messages.es.yaml
tests/Issues/IssueWorkflowTest.php
docs/CHANGELOG.md
```

## Implementation Decisions

1. **Priority** — `App\Shared\IssuePriority` backed enum; column on `issue` default `medium`; filter on index; POST form on detail.
2. **Comments** — `IssueComment` with issue, author, body, createdAt, uuid; POST add; list on detail; optional `UserActionType::IssueCommented`.
3. **Duplicate** — nullable `duplicateOf` ManyToOne self-FK; mark sets link + `IssueStatus::Ignored`; reject self and A↔B cycles; default list already excludes via status=unresolved.
4. **Saved views** — `IssueSavedView` with `queryJson` array of filter/sort keys; save / apply (redirect) / delete.
5. **Merge** — skipped (event count merge / merge-later deferred).

## Complexity Tracking

| Deferred | Why |
|----------|-----|
| Merge-later / event merge | Plan: later release; mark-as-duplicate only |
| Comment Markdown | Spec: plain text for v1 |
| Shared team saved views | Spec: per-user within project |
