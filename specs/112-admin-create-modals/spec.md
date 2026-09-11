# Feature Specification: Admin / alerts create modals

**Feature Branch**: `112-admin-create-modals`  
**Created**: 2026-09-11  
**Status**: Shipped (v1.28.0 / Phase 6.64)  
**Roadmap**: Phase 6.64  

**Input**: Small create forms that were full-page admin (or Settings) routes SHOULD open as `confirm-dialog` modals on the parent list/settings page — same pattern as Add user (`?new=1`) and admin roles (`086` US 2c). Covers **New group**, **New project** (Administration), and **New threshold rule**, plus FormKit catalogue keys so placeholders/help never render as raw keys.

## Summary

| ID | Surface | GET open | POST | Edit |
|----|---------|----------|------|------|
| C1 | Admin groups | `/admin/groups/new` → `/admin/groups?new=1` | `admin_groups_new` | Full page (`…/edit`) |
| C2 | Admin projects | `/admin/projects/new` → `/admin/projects?new=1` | `admin_projects_new` | Full page (`…/edit`) |
| C3 | Threshold rules | `/projects/{id}/threshold-rules/new` → `…/settings/alerts?new_threshold=1` | `project_threshold_rule_new` | Full page |
| C4 | i18n | FormKit `admin_group.*` / `project.*` / `project_threshold_rule.*` in `translations/form.*.yaml` | — | — |
| C5 | Manual | Capture paths use query opens; chapters note modal UX (`111`) | — | — |

## Non-goals

- Converting every admin `*/new` route (roles/permissions already modal; notifications/new, social-login/new, etc. stay as decided elsewhere)
- Changing dashboard product create (`/projects/new` → `/dashboard?new=1` — already `002`)
- Changing group/project **edit** flows to modals
- New notification categories or threshold evaluation semantics (`027`)

## User Scenarios & Testing

### User Story 1 - Create admin group in a modal (P1)

As an instance admin on Administration → Groups, I create a group without leaving the directory.

**Independent Test**: `GET /admin/groups/new` redirects to `?new=1`; modal open-on-connect; POST creates and redirects to group show; invalid POST re-renders index with modal open.

**Acceptance Scenarios**:

1. **Given** `ROLE_ADMIN`, **When** I open `/admin/groups/new`, **Then** I land on `/admin/groups?new=1` with exactly one `data-confirm-dialog-open-on-connect-value="true"`.
2. **Given** a valid name, **When** I submit the modal form, **Then** the group is created and I am redirected to group show.
3. **Given** an empty name, **When** I submit, **Then** I stay on the directory with the modal open and validation errors (no nameless group).

### User Story 2 - Create admin project in a modal (P1)

As an instance admin on Administration → Projects, I create a project from a modal on the fleet list.

**Independent Test**: Same redirect/`open_on_connect` pattern as groups; admin becomes owner + default API key (existing `ProjectFactory` behaviour).

**Acceptance Scenarios**:

1. **Given** `ROLE_ADMIN`, **When** I open `/admin/projects/new`, **Then** I land on `/admin/projects?new=1` with the create modal open.
2. **Given** a valid name, **When** I submit, **Then** I am redirected to admin project show.
3. **Given** product create via `/projects/new`, **When** followed, **Then** behaviour remains dashboard-modal (`002` FR-008) — unchanged by this feature.

### User Story 3 - Create threshold rule in a modal (P1)

As a project member with `project.notifications.manage`, I add a volume threshold from Settings → Alerts without a dedicated create page.

**Independent Test**: `GET …/threshold-rules/new` redirects to settings alerts with `?new_threshold=1`; edit remains full page.

**Acceptance Scenarios**:

1. **Given** manage notifications grant, **When** I open the new-rule URL, **Then** Settings → Alerts opens with the create modal.
2. **Given** valid count/window, **When** I submit, **Then** the rule is stored and listed on Alerts.
3. **Given** FormKit catalogue, **When** the modal renders, **Then** labels/placeholders/help are translated (`project_threshold_rule.*`), not raw keys.

### User Story 4 - Manual inventory matches modal UX (P2)

As a maintainer regenerating `docs/manual/`, create shots open the modal query URLs.

**Independent Test**: `admin-groups-new` → `/admin/groups?new=1`; `admin-projects-new` → `/admin/projects?new=1`; `project-threshold-rules-new` → `…/settings/alerts?new_threshold=1`.

## Functional Requirements

- **FR-001**: Admin group create MUST use list-page confirm-dialog + `?new=1` open; GET `admin_groups_new` MUST redirect; POST MUST stay on `admin_groups_new`.
- **FR-002**: Admin project create MUST use the same pattern (`admin_projects` / `admin_projects_new`).
- **FR-003**: Threshold rule create MUST open on Settings → Alerts via `?new_threshold=1`; edit MAY remain a full-page form.
- **FR-004**: Invalid create POSTs MUST re-render the parent page with the modal open (`open_create` / equivalent).
- **FR-005**: Visible FormKit chrome for these forms MUST resolve from `translations/form.*.yaml` (no raw `{prefix}.{field}.*` keys in EN UI).
- **FR-006**: Product UI manual capture paths and chapter notes MUST document modal opens (`111`).
- **FR-007**: Structured confirm chrome MUST follow `086` FR-003b/c (`confirm-dialog__header` / `__content` / `__actions`, `open_on_connect` attribute rules).

## Success Criteria

- **SC-001**: Functional tests assert redirect + modal open + create for admin groups and admin projects.
- **SC-002**: Threshold settings tests / E2E open create via alerts query (or redirect).
- **SC-003**: Manual PNGs for the three create surfaces show a modal over the parent page.

## Cross-refs

- Pattern predecessors: `086-dry-refactor` (US 2c), `002-identity-project` (dashboard project + admin roles/users)
- Domain: `019-admin-projects-ops`, `036-admin-identity-audit`, `027-threshold-alerts`
- Manual: `111-product-ui-manual`
- Roadmap: Phase 6.64
