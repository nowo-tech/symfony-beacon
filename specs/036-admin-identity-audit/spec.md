# Feature Specification: Admin Identity Audit Timeline

**Feature Branch**: `036-admin-identity-audit`  
**Created**: 2026-07-31  
**Status**: Implemented  

**Input**: Bring Admin → **User** and Admin → **Group** audit UX to parity with Admin → Project audit (`031-admin-project-audit`): filterable `user_action` timelines (action type + date range) on top of existing AuditKit timestamps/blame. Reuse `UserAction` / `UserActionRecorder`; do not invent a parallel audit store.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Group audit timeline (Priority: P1)

As an instance admin, I open a group in Administration and see a newest-first timeline of group-related actions (create/update/delete, member add/remove, and other recorded `group.*` actions), with empty state when none exist.

**Independent Test**: Perform group member add as admin; open Admin group show → timeline contains the action with actor and timestamp.

**Acceptance Scenarios**:

1. **Given** recorded actions with `group_uuid` (or equivalent) in context, **When** I open Admin group show, **Then** a timeline lists them newest-first.
2. **Given** no group actions, **When** I open the page, **Then** an empty state is shown.
3. **Given** I am not `ROLE_ADMIN`, **When** I request the group admin URL, **Then** access is denied.

### User Story 2 - Filterable user activity (Priority: P1)

As an instance admin, I open a user’s activity page and filter by action type and/or date range (same interaction model as project audit in `031`).

**Acceptance Scenarios**:

1. **Given** mixed action types on a user, **When** I filter to role-change / enable-disable, **Then** only those entries appear.
2. **Given** a date range, **When** applied, **Then** entries outside the range are excluded.
3. **Given** no matching rows after filter, **When** the page renders, **Then** an empty filtered state is shown.

### User Story 3 - AuditKit meta vs append-only history (Priority: P2)

As an instance admin, I still see AuditKit `createdAt` / `updatedAt` / blame on user and group admin pages **in addition to** the `user_action` timeline, and understand they answer different questions (last editor vs event history).

**Acceptance Scenarios**:

1. **Given** a group with blame fields set, **When** I open group show, **Then** AuditKit meta remains visible alongside the timeline section.

## Edge Cases

- Actions recorded without `group_uuid` / user subject MUST NOT appear on the wrong entity’s timeline.
- Auth noise (`issue.*`, project ops) SHOULD be excluded from identity allowlists unless the action’s subject/context clearly targets that user/group.
- SQLite JSON context queries MUST match the portable approach used by `UserActionRepository::findForProject`.
- Very long histories: default page/limit capped (document N); optional “load more” is not required for MVP.

## Requirements *(mandatory)*

- **FR-001**: Admin group show MUST include a filterable `user_action` timeline for that group (`ROLE_ADMIN`).
- **FR-002**: Admin user activity MUST support filters for action type and date range (parity with `031` project audit UX).
- **FR-003**: Queries MUST reuse `user_action` via `UserActionRepository` extensions (`findForGroup`, filtered `findForUser`); no parallel audit table.
- **FR-004**: Document allowlisted `UserActionType` values for user vs group timelines (identity/admin focused).
- **FR-005**: Keep AuditKit timestamp/blame panels; timeline is additive.
- **FR-006**: English catalogues for filters/empty states; key parity for enabled locales.
- **FR-007**: Functional tests: group member add appears on group timeline; user role change filterable; non-admin denied.

## Success Criteria

- **SC-001**: After a group member add/remove in a test, the group timeline shows the matching action.
- **SC-002**: User activity filters reduce the visible set correctly.
- **SC-003**: Non-admins cannot open admin identity audit UI.

## Assumptions

- Many identity actions are already recorded (`UserActionRecorder` + `UserActionType`); this epic is primarily query completeness + UI parity with `031`.
- Project audit (`031`) remains the UX reference for filters and empty states.

## Out of Scope

- CSV/export of audit trails.
- Immutable WORM / compliance-grade storage.
- End-user (non-admin) account security activity (see `037` polish).
- Replacing AuditKit traits on User/UserGroup.
