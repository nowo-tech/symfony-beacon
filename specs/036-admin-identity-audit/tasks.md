# Tasks: Admin Identity Audit Timeline (`036`)

**Input**: `specs/036-admin-identity-audit/spec.md`  
**Status**: Implemented

## Phase 1: Repository

- [x] T001 Document identity/group allowlists for `UserActionType`
- [x] T002 `UserActionRepository::findForGroup` (context `group_uuid`, newest-first, limit)
- [x] T003 Filtered `findForUser` (action + from/to), mirroring project audit filters

## Phase 2: Admin UI

- [x] T004 Admin group show — timeline section + filters + empty state
- [x] T005 Admin user activity — action/date filters (parity with `031`)
- [x] T006 Optional shared Twig partial for audit rows if it reduces duplication without forking kits

## Phase 3: Quality

- [x] T007 i18n keys for filters/empty states; locale parity
- [x] T008 PHPUnit: group member add on timeline; user filter; non-admin 403
- [x] T009 CHANGELOG / ROADMAP
