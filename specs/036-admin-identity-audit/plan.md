# Plan: Admin Identity Audit Timeline (`036`)

**Branch**: `036-admin-identity-audit` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

## Summary

Extend `UserActionRepository` with group lookup + filtered user lookup (mirror `findForProject`). Wire Admin group show + Admin user activity filters. Reuse Twig patterns from `templates/admin/projects/show.html.twig` audit section.

## Technical Context

| Area | Decision |
|------|----------|
| Store | Existing `user_action` / `UserAction` / `UserActionType` |
| Query | JSON context `group_uuid` (same portability as project UUID queries) |
| UI | Filters: `action`, `from`, `to` query params |
| Auth | `ROLE_ADMIN` |
| Reference | `031-admin-project-audit`, `AdminProjectController::show` |

## Constitution Check

| Gate | Status |
|------|--------|
| Spec-first | Pass |
| Prefer kits | Pass — AuditKit meta stays; timeline is Beacon `UserAction` |
| English UI | Pass |
| Tests | Pass — FR-007 |

## Implementation

1. `UserActionRepository::findForGroup` + filtered `findForUser` (allowlists in controller or repository).
2. `AdminGroupController::show` — pass timeline + filter form state.
3. `AdminUserController::activity` — add filters (keep existing page).
4. Twig partial optional `templates/admin/_audit_timeline.html.twig` shared by project/user/group if low-cost.
5. i18n + PHPUnit (`AdminGroupsTest` / `AdminUsersTest` extensions).
6. CHANGELOG / ROADMAP.

## Risks

- Over-broad user timelines showing unrelated `issue.*` — enforce allowlist.
- SQLite vs MySQL JSON path differences — copy proven `findForProject` SQL strategy.
