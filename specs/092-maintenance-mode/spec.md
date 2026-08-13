# Feature Specification: Site-wide maintenance mode

**Feature Branch**: `092-maintenance-mode`  
**Created**: 2026-08-13  
**Status**: Implemented (2026-08-13)

**Input**: Integrate `nowo-tech/maintenance-mode-bundle` so operators can enable, schedule, and preview site-wide HTTP **503** maintenance from Administration, with Beacon-branded public page art and kit admin chrome (same look and feel as other kit panels). Prefer the official kit over a hand-rolled maintenance stack.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Enable / disable from Administration (Priority: P1)

As an instance **ROLE_ADMIN**, I open **Administration → Maintenance** (`/admin/maintenance/`) and can enable or disable site-wide maintenance with an optional public message, without a second ops password gate (AuthKit session + `ROLE_ADMIN` is enough).

**Why this priority**: Core operator need during upgrades and incidents.

**Independent Test**: Sign in as admin; enable maintenance; anonymous request to a public route returns 503 with the branded page; disable; public routes work again. Panel prefix stays reachable while maintenance is on.

**Acceptance Scenarios**:

1. **Given** maintenance disabled, **When** an admin enables it with a message, **Then** guests see the public maintenance page (503) and the configured message.
2. **Given** maintenance enabled, **When** an admin opens `/admin/maintenance/`, **Then** the panel loads (auto-excluded from 503) and they can disable.
3. **Given** `password_protection: false`, **When** the panel renders, **Then** no maintenance-panel logout control is shown.

---

### User Story 2 - Schedule window (Priority: P2)

As an admin, I can set scheduled enable/disable timestamps (and message) from the same panel, and clear the schedule.

**Independent Test**: Save a future enable/disable pair; observe state fields; clear schedule; fields empty.

**Acceptance Scenarios**:

1. **Given** a scheduled enable time in the future, **When** the clock passes it, **Then** maintenance becomes effectively on.
2. **Given** a schedule, **When** the admin clears it, **Then** scheduled fields are empty and history records the clear.

---

### User Story 3 - Preview without downtime (Priority: P1)

As an admin or developer, I can open **`/_maintenance_preview`** (and the Administration hub / sidebar preview link) to see the public maintenance page **without** enabling downtime.

**Independent Test**: With maintenance off, open preview; page shows branded art and copy; site remains available.

**Acceptance Scenarios**:

1. **Given** maintenance off, **When** I open `/_maintenance_preview`, **Then** I see the same public chrome as a real 503 maintenance response (illustration + copy).
2. **Given** preview enabled in config (null = on when `kernel.debug`), **When** `APP_ENV` is not debug and preview is forced off, **Then** preview is unavailable.

---

### User Story 4 - Beacon public page art (Priority: P1)

As a visitor on the maintenance page (live 503 or preview), I see **`public/illustrations/error-503.png`** as the hero illustration (shared with branded `error503` from `063`), plus brand mark, theme toggle, and calm copy — not the default bundle demo layout.

**Independent Test**: Curl `/_maintenance_preview` and assert `illustrations/error-503.png` in HTML; visual check matches other error pages’ figure chrome.

**Acceptance Scenarios**:

1. **Given** the host Twig override for the maintenance page, **When** rendered, **Then** the figure uses `asset('illustrations/error-503.png')` and `error.503.image_alt` (or equivalent) for alt text.
2. **Given** a scheduled disable time, **When** the page renders, **Then** a countdown / ETA MAY appear without breaking the layout.

---

### User Story 5 - Administration chrome + nav (Priority: P1)

As an admin, Maintenance appears in the **Administration** sidebar (Instance) and hub tiles; the panel uses **kit admin** layout (page header, beacon tabs for Panel / History, `.panel` cards, stacked forms, kit button metrics) consistent with Http Log / RoutingKit. History is a table in a panel. A header action links to the public preview.

**Independent Test**: After `app:seed-platform`, aside shows Maintenance; `/admin/maintenance/` has `[data-testid="admin-maintenance"]`, tabs, and status/manual/schedule panels; history route shows a table panel.

**Acceptance Scenarios**:

1. **Given** platform menus seeded, **When** an admin opens Administration, **Then** sidebar includes Maintenance → `nowo_maintenance_mode_panel_index`.
2. **Given** the panel index, **When** rendered, **Then** duplicate vendor page titles are not shown (kit header owns the title/intro) and controls use Beacon primary/danger/secondary button patterns.
3. **Given** Observability menu items, **When** seeded, **Then** Maintenance preview → `nowo_maintenance_mode_preview` is available.

---

### Edge Cases

- AuthKit login/register/reset and health endpoints remain reachable during maintenance (configured exclusions).
- Panel routes under `/admin/maintenance` are always excluded from the 503 gate.
- Empty message falls back to translated bundle default.
- History empty state shows a calm empty row, not a raw exception.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Depend on `nowo-tech/maintenance-mode-bundle`; configure via `config/packages/nowo_maintenance_mode.yaml` (panel path `/admin/maintenance`, preview `/_maintenance_preview`, `ROLE_ADMIN`, no password gate).
- **FR-002**: Host layout `kit/maintenance_mode_panel_layout.html.twig` extends `kit/_kit_admin_layout.html.twig` with UiKit tokens remapped like other kits (`css_framework: tailwind`, `icon_set: ux_icon`).
- **FR-003**: Host Twig forks under `templates/bundles/NowoMaintenanceModeBundle/` for public `maintenance/page.html.twig` and panel `index` / `history` / `_nav` (beacon tabs).
- **FR-004**: Public page hero MUST use `public/illustrations/error-503.png` (see `063` FR-007).
- **FR-005**: Platform seed (`menus.json` + breadcrumbs) MUST include Maintenance and Maintenance preview; `AdministrationMenuCurrentMatcher` MUST highlight the panel for `nowo_maintenance_mode_*` child routes.
- **FR-006**: Security / SiteBackup / setup gates MUST keep panel + preview + AuthKit paths reachable as documented in config exclusions.
- **FR-007**: Automated tests MUST cover kit chrome smoke (panels/tabs/preview link) and MUST NOT expect a panel logout when password protection is off.

### Key Entities

- **MaintenanceState**: enabled flag, message, activated/deactivated times, scheduled enable/disable, updatedBy (bundle-owned file/JSON storage).
- **History entry**: append-only action log (enable/disable/schedule/clear) for the History tab.

## Success Criteria *(mandatory)*

- **SC-001**: An admin can enable and disable maintenance in under a minute from the Administration panel without leaving the app shell.
- **SC-002**: Guests hit a Beacon-branded 503 page with `error-503.png` while operators can still sign in and open the panel.
- **SC-003**: Preview works without enabling downtime.
- **SC-004**: Panel UI matches kit Administration look and feel (tabs, panels, buttons) — no bare vendor demo layout.

## Assumptions

- Operators are already `ROLE_ADMIN` via AuthKit; no separate maintenance password is required for this host.
- State/history files live under `var/maintenance/` (bundle defaults) and are environment-local.
- Legal / cookie surfaces are unchanged by maintenance (public page is noindex guest chrome).

## Out of Scope

- Per-project maintenance or partial route allowlists beyond bundle exclusions.
- Translating baked-in PNG strings (“UNDER MAINTENANCE”).
- Replacing Symfony profiler pages in debug mode.
