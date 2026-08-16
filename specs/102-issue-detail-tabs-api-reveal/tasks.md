# Tasks: Issue detail tabs + temporary API DSN reveal

**Feature**: `102-issue-detail-tabs-api-reveal`  
**Status**: Done (v1.18.4 / Phase 6.53)

## Phase 1: Issue detail tabs

- [x] T001 Add `IssueShowTab` enum + route `{tab}` on `issue_show` (default `main`, 404 unknown)
- [x] T002 Thread tab through `IssueShowPageBuilder` / Twig; shared `_tabs` links for main/similar/history
- [x] T003 Move similar + history panels into tab bodies; keep Main triage/comments/aside
- [x] T004 Issue back-link partial + event/show chrome spacing; flat app-paper SCSS

## Phase 2: Temporary API DSN reveal

- [x] T005 `ProjectApiKey::maskDsn()` + Settings builder attach flash DSN to matching active key
- [x] T006 Stimulus `temporary-reveal` (30s, clear-on-hide) on key row + flash; rotate confirm
- [x] T007 Catalogue strings for show/hide/cleared/copy DSN

## Phase 3: Tests + docs

- [x] T008 PHPUnit visibility / builder / controller show tests; Vitest temporary-reveal
- [x] T009 E2E thresholds-health / partials assert issue-detail-tabs
- [x] T010 Spec `102`; amend `041` / `015` / `002` / `018` / `087`; ROADMAP **6.53**; CHANGELOG Unreleased; `feature.json`
