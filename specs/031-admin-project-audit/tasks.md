# Tasks: Admin Project Audit Timeline

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Planning

- [x] T001 Write `plan.md` with query/filter approach and constraints
- [x] T002 Expand `tasks.md` into implementation phases

## Phase 2: Backend query + controller

- [x] T003 Add `UserActionRepository::findForProject()` using existing `context.project_uuid`
- [x] T004 Wire Admin project show filters (`action`, `from`, `to`) and newest-first timeline data
- [x] T005 Keep project admin actions queryable by enriching `ProjectViewAsStarted` with project context

## Phase 3: UI + i18n

- [x] T006 Add audit timeline section, filter form, and empty state to Admin -> Project show
- [x] T007 Add English and Spanish translations for audit filter UI

## Phase 4: Tests + docs

- [x] T008 Add functional PHPUnit coverage for empty state, ordering, action filter, and date range filter
- [x] T009 Update `docs/ROADMAP.md` (5.7 Done) and `docs/CHANGELOG.md`
