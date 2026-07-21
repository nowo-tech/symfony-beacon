# Implementation Plan: Admin Project Audit Timeline

**Branch**: `031-admin-project-audit`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Add a filterable audit timeline to Admin -> Project show using existing `user_action` rows only. The page lists newest-first administrative project actions, supports URL query filters for action type and date range, and shows an empty state when no matching records exist.

## Technical Context

| Area | Decision |
|------|----------|
| Data source | Reuse `App\Identity\Entity\UserAction` + `UserActionRecorder`; no parallel audit table |
| Project scoping | Query `user_action.context.project_uuid` from the existing JSON context |
| Included actions | Project admin operations from `019-admin-projects-ops` and related settings/history/key actions |
| Filters | Query params `action`, `from`, `to` (`Y-m-d`, inclusive end date) |
| Ordering | `created_at DESC, id DESC` |
| Access | Existing `#[IsGranted('ROLE_ADMIN')]` on `AdminProjectController` |
| UI | Extend `templates/admin/projects/show.html.twig`; English UI copy with EN + ES translations |
| Testing | Functional PHPUnit coverage for empty state, timeline ordering, action filter, and date range filter |

## Constitution Check

- Spec-first: yes (`031`)
- Reuse existing audit infrastructure (`UserAction`, `UserActionRecorder`, `UserActionType`)
- English docs / PHPDoc / UI structure respected; translations added for `en` and `es`

## Notes

- `project_uuid` is already recorded in `context` for most project actions; `ProjectViewAsStarted` is enriched with project context when launched from Admin -> Project show so it remains queryable on the project timeline.
- No public/legal surface changes are introduced by this admin-only feature.
