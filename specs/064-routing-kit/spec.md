# Feature Specification: RoutingKit locale paths

**Feature Branch**: `064-routing-kit`  
**Created**: 2026-07-31  
**Status**: Implemented (install + host config — 2026-07-31; panel FormKit + UiKit pagination — **1.3.0** / `081-formkit-uikit-kit-sync` — 2026-08-05; Administration panel chrome — 2026-08-12)  

**Input**: Install [`nowo-tech/routing-kit-bundle`](https://packagist.org/packages/nowo-tech/routing-kit-bundle) so Beacon can manage dual `/path` + `/{locale}/path` URLs for **app** controllers marked `#[Routable]`, without reinventing AuthKit/SiteBackup locale loaders.

## User Scenarios

### US1 — Admin path panel (P1)

As `ROLE_ADMIN`, **When** I open `/admin/_routing/`, **Then** I manage stored locale paths (JSON under `var/routing_kit/`) inside Beacon kit-admin chrome (`kit/routing_kit_panel_layout.html.twig`). Create/edit uses RoutingKit **≥ 1.3** Symfony FormKit form (`RoutePathDefinitionType`, profile `routing_kit`); host Twig MUST paint via `form_*` inside a Beacon `panel`. List pagination uses `kit/_pagination`. Index list MUST use Administration chrome (`section.panel` + sand/ink table, actions last, `_action_inner` honoring `row_actions_display`); Import MUST be a modal; Export / Clear cache / Import / New in `kit_admin_header_actions` (secondary → primary); host i18n for Export/Import/enabled badges/confirm. Host `panel/base` MUST NOT re-append `nowo-ui.css`.

### US2 — Default-locale bare paths (P1)

As an operator configuring RoutingKit, **When** `register_unprefixed_default: true` and a route has a default-locale path, **Then** the unprefixed URL is registered for that locale (same product idea as AuthKit `unlocalized: serve`).

### US3 — Discovery of domain controllers (P2)

As a developer, **When** I add `#[Routable]` on controllers under Beacon domain folders, **Then** discovery `scan_dirs` (Legal, Identity, Project, … — not only `src/Controller`) lists them in the panel.

## Requirements

- **FR-001**: Require `nowo-tech/routing-kit-bundle` **≥ 1.3.0** (host pin **1.4.0**: panel export/clear-cache/import/delete as Symfony forms; host Twig uses `export_form` / `delete_forms`) and register the bundle (FormKit + UiKit dependencies come from the kit).
- **FR-002**: Config `nowo_routing_kit` MUST use `%default_locale%` / `%fallback_locales%`, host layout, `security.access_roles: [ROLE_ADMIN]`, and `register_unprefixed_default: true`.
- **FR-003**: Panel routes import via `config/routes/nowo_routing_kit.yaml`; DB/JSON loader `type: nowo_routing_kit` MUST be imported **last** in `config/routes.yaml`.
- **FR-004**: `access_control` MUST require `ROLE_ADMIN` for `^/admin/_routing`. Setup/catalog gates MUST exclude `/admin/_routing`.
- **FR-005**: Administration hub SHOULD link to `nowo_routing_kit_panel` with i18n keys `nav.routing` / `admin.hub.routing`.
- **FR-006**: AuthKit and SiteBackup locale routing remain owned by those bundles (`002`, `056`); RoutingKit MUST NOT be required to dual-serve `/login` or `/setup`.
- **FR-007**: Host FormKit profile `routing_kit` MUST set `translation_domain: NowoRoutingKitBundle` and `auto_help` / `auto_placeholder: false` (FormKit **≥ 2.2**; see `081`).
- **FR-008**: Panel create/edit MUST use the kit Symfony form; any host Twig override MUST call `form_start` / unrendered rows / `form_end` (no parallel hand-rolled field POST map).
- **FR-009**: Panel index pagination MUST use UiKit `_pagination` or Beacon `kit/_pagination` (same contract as product lists).
- **FR-010**: Panel index MUST NOT duplicate hub intro (`kit_admin_intro`) with an extra `panel.subtitle` line; Import UI MUST NOT be an always-visible inline form below the table when a modal is available.

## Amendment (Panel list chrome, 2026-08-12)

Host `templates/bundles/NowoRoutingKitBundle/panel/{base,index,form}.html.twig` aligned with `081` Administration chrome (header CTAs, `panel` table / form, text row actions, Import modal + layout portals, no double `nowo-ui.css`). See `081` amendment “Kit Administration chrome sync”.

## Related

- `081-formkit-uikit-kit-sync` — kit pin sync, FormKit profiles, pagination chrome, kit admin table/filter convention.

## Out of Scope

- Migrating legal/AuthKit/SiteBackup onto RoutingKit in this slice.
- SeoKit install (bridge may stay enabled; no-ops without SeoPathBuilder).
- Replacing account-preference locale on dashboard URLs.
