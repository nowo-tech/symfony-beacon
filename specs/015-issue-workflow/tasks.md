# Tasks: 015-issue-workflow

**Input**: [plan.md](./plan.md), [spec.md](./spec.md)

**Prerequisites**: plan.md, spec.md

## Phase 1: Schema & enums

- [x] T001 [P] Add `App\Shared\IssuePriority` enum (`low`, `medium`, `high`, `critical`)
- [x] T002 Extend `Issue` with `priority` (default medium) and nullable `duplicateOf` self-FK
- [x] T003 [P] Create `IssueComment` entity + repository
- [x] T004 Migration `Version20260721161000` (priority + issue_comment + duplicate_of_id)
- [x] T005 [P] Create `IssueSavedView` entity + repository
- [x] T006 Migration `Version20260721162000` (`issue_saved_view`)

## Phase 2: Controllers & list filter

- [x] T007 Priority POST on issue detail + priority filter on issue index (`IssueRepository` / `IssueController`)
- [x] T008 Comment POST + load comments on issue show
- [x] T009 Mark-as-duplicate POST (link + ignored; reject self/circular)
- [x] T010 Saved views: save current filters, apply (redirect), delete
- [x] T011 Optional `UserActionType` for comment / priority / duplicate when straightforward

## Phase 3: UI & i18n

- [x] T012 Twig: priority + comments + duplicate on `templates/issue/show.html.twig`
- [x] T013 Twig: priority filter + saved views on `templates/issue/index.html.twig`
- [x] T014 Translations EN/ES for new issue workflow strings (+ activity labels if added)

## Phase 4: Tests & docs

- [x] T015 `tests/Issues/IssueWorkflowTest.php` (priority, comment, duplicate, saved view)
- [x] T016 Changelog Unreleased bullets (English)
- [x] T017 Mark plan/tasks complete

## Deferred (completed follow-up)

- [x] Merge events into canonical (checkbox on mark-as-duplicate; recompute counts / lastSeen / release fields; archive source)
