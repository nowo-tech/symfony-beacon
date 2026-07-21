# Feature Specification: Admin Project Audit Timeline

**Feature Branch**: `031-admin-project-audit`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: On Administration → Project show, display a filterable timeline of admin project actions already recorded in `user_action` (suspend, delete, membership/role changes, key revoke/rotate, view-as, etc. from `019-admin-projects-ops`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View project audit trail (Priority: P1)

As an instance admin, I open a project in Admin and see recent admin actions related to that project.

**Acceptance Scenarios**:

1. **Given** recorded actions with `project_uuid` (or equivalent) in context, **When** I open Admin project show, **Then** a timeline lists them newest-first.
2. **Given** no actions, **When** I open the page, **Then** an empty state is shown.

### User Story 2 - Filter the timeline (Priority: P2)

As an instance admin, I filter by action type and/or date range.

**Acceptance Scenarios**:

1. **Given** mixed action types, **When** I filter to suspend/resume, **Then** only those entries appear.
2. **Given** a date range, **When** applied, **Then** entries outside the range are excluded.

## Requirements *(mandatory)*

- **FR-001**: Admin project show MUST include an audit timeline section (ROLE_ADMIN).
- **FR-002**: Entries MUST reuse `user_action` (no parallel audit store unless justified in plan).
- **FR-003**: Action types for project admin ops MUST remain queryable (document context keys).
- **FR-004**: Optional filters for type and time range.

## Success Criteria

- **SC-001**: After suspend/resume in a test, the timeline shows the matching action.
- **SC-002**: Non-admins cannot open the admin project audit UI.

## Assumptions

- Many actions already recorded in `019`; this epic is primarily UI + query/filter completeness.

## Out of scope

- Exporting audit CSV (may follow export patterns later).
- Immutable WORM compliance storage.
