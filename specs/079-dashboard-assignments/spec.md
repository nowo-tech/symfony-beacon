# Feature Specification: Dashboard assignments panel

**Feature Branch**: `079-dashboard-assignments`  
**Created**: 2026-08-03  
**Status**: Done  
**Input**: Dashboard panel (beside Projects) listing issue assignments: mine, teammates, and unassigned in accessible projects — filterable by scope, project, level, status, priority, assignee, and search.

## User Scenarios & Testing

### User Story 1 - See my queue (Priority: P1)

As a signed-in user, I open **Assignments** in the dashboard sidebar and see unresolved issues assigned to me across projects I can access.

**Acceptance**: Route `/dashboard/assignments` (default scope `mine`); each row links to the issue; empty state when none.

### User Story 2 - Teammates and unassigned (Priority: P1)

As a member, I filter scope to **Teammates** (assigned to others) or **Unassigned** in my projects.

**Acceptance**: Scope select works; optional assignee filter when scope is teammates/all; project filter narrows results.

### User Story 3 - Filters (Priority: P2)

As a member, I filter by level (type), status, priority, text search, and page size.

**Acceptance**: Filters are GET query params; clear resets to defaults (`scope=mine`, `status=unresolved`).

## Requirements

- **FR-001**: Sidebar Dashboard item **Assignments** (seeded) + breadcrumb.
- **FR-002**: Only issues in projects from `findAccessibleByUser`.
- **FR-003**: Scopes: `mine` | `teammates` | `unassigned` | `all`.
- **FR-004**: Filters: `project` (uuid), `level`, `status`, `priority`, `assignee` (user id), `q`, `per_page`, `page`.
- **FR-005**: Paginated table: project, title, level, status, priority, assignee, last seen.
- **FR-006**: English UI strings + locales in seeder translations.

## Out of scope (v1)

- Cross-project saved views
- Assign/reassign from this panel
- Event tag/url/user filters
