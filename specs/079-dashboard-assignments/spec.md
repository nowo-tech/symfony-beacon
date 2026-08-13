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

- **FR-001**: Sidebar Dashboard item **Assignments** (seeded) + breadcrumb with parent `dashboard_home` (Projects), so the trail renders under `hide_when_single_root`.
- **FR-002**: Only issues in projects from `findAccessibleByUser`.
- **FR-003**: Scopes: `mine` | `teammates` | `unassigned` | `all`.
- **FR-004**: Filters: `project` (uuid), `level`, `status`, `priority`, `assignee` (user id), `q`, `per_page`, `page`.
- **FR-005**: Paginated table via `PagePagination` + `shared/_table_pagination.html.twig` (alias of `kit/_pagination` → UiKit `_pagination`, `«` / `»` + page numbers — see `081`): project, title, level, status, priority, assignee, last seen.
- **FR-006**: English UI strings + locales in seeder translations.

## Out of scope (v1)

- Cross-project saved views
- Assign/reassign from this panel
- Event tag/url/user filters

## Related

- `080-dashboard-aside-panels` — Summary, Activity, Mentions, Alerts, New in release (same Dashboard aside; shared pagination convention FR-011).
- `081-formkit-uikit-kit-sync` — pagination chrome via UiKit 1.7+; product FormKit profile `filter`.
- `090-csrf-symfony-forms` — GET filter Types / CSRF boundary.
- Breadcrumbs for Assignments nest under `dashboard_home` (Projects) so trails render with `hide_when_single_root`.

## Amendment (FormKit GET filter, 2026-08-13)

`DashboardAssignmentsFilterType` extends `AbstractGetFilterType` (profile `filter`). Contract: `081` FR-003a (`required` false except `per_page`; CSRF off on GET intentional; access still via `findAccessibleByUser`). Twig paints via `form_row` + `_fields` / loop (`077`).
