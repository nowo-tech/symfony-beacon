# Tasks: CSRF via Symfony Forms

**Feature**: `090-csrf-symfony-forms`  
**Status**: Implemented (as-built, 2026-08-12)

## Phase 1: Shared helpers

- [x] T001 `CsrfOnlyType` + empty block prefix + FormKit base
- [x] T002 `HiddenFieldsCsrfType` for POST with hidden fields + CSRF
- [x] T003 `CsrfOnlyFormFactory` (+ Twig `csrf_action_form` / `CsrfActionTwigExtension`)
- [x] T004 Partial `templates/form/_csrf_action.html.twig`
- [x] T005 `AbstractGetFilterType` + `GetFilterFormFactory` (+ `AdminSearchType` / `SearchQueryType`)

## Phase 2: Issues / Mentions / Tours (US1–US3)

- [x] T006 Issue triage Types (`IssueStatusType`, `IssuePriorityType`, `IssueDuplicateType`, `IssueSavedViewType`, `IssueCommentType`) + controller/Twig
- [x] T007 Mentions mark-read Types + dashboard Mentions Twig
- [x] T008 `AccountProductTourReplayType` + Display → Tours
- [x] T009 Update `IssueWorkflowTest` (and related) for Symfony submit shape

## Phase 3: Project Settings / danger / memberships (US2)

- [x] T010 `ProjectClearHistoryType` / `ProjectDeleteType` + danger Twig/tests
- [x] T011 Settings create/import Types (governance, API key, read token, share, group/member, config import, transfer, …)
- [x] T012 `ProjectSettingsPageBuilder` CSRF-only / hidden views for revoke/toggle/delete/test rows
- [x] T013 Member set-active / remove / role Types wired to Forms

## Phase 4: Identity / admin / Shared Settings

- [x] T014 Admin group-member add, role permissions, role-user add, user role confirm, privacy `TypeToConfirmType`
- [x] T015 Locale / guest locale / view-as disable via CSRF-only
- [x] T016 Appearance theme picker, mailer test, instance config import Types
- [x] T017 Admin list CSRF deletes / toggles via factory

## Phase 5: GET filters (US4)

- [x] T018 Issues index + dashboard aside filter Types
- [x] T019 Admin search / audit timeline / analytics / ops overview / release compare filter Types
- [x] T020 Controllers build filters via `GetFilterFormFactory`
- [x] T021 FormKit profile `filter` contract (081): placeholders/`form` catalogue; analytics help; no `help: false` on HiddenType; `required` false except `per_page`; host `type_map.search`; document CSRF/authz non-regression

## Phase 6: Docs / cross-specs

- [x] T021 Spec + plan + tasks + checklist; amend `077` / related (`004`, `011`, `015`, `057`, `080`, `087`)
- [x] T022 ROADMAP 6.39 + CHANGELOG Unreleased note for host Form CSRF migration
- [x] T023 Confirm intentional exceptions: AJAX header CSRF, AuthKit logout, kit modal `data-token`
- [x] T024 E2E / DomCrawler selectors for prefixed `beacon` fields (`project_governance_*`, share/token create, `admin_group_member_add`); amend this spec + `077`/`081`
