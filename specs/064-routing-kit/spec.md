# Feature Specification: RoutingKit locale paths

**Feature Branch**: `064-routing-kit`  
**Created**: 2026-07-31  
**Status**: Implemented (install + host config — 2026-07-31)  

**Input**: Install [`nowo-tech/routing-kit-bundle`](https://packagist.org/packages/nowo-tech/routing-kit-bundle) so Beacon can manage dual `/path` + `/{locale}/path` URLs for **app** controllers marked `#[Routable]`, without reinventing AuthKit/SiteBackup locale loaders.

## User Scenarios

### US1 — Admin path panel (P1)

As `ROLE_ADMIN`, **When** I open `/admin/_routing/`, **Then** I manage stored locale paths (JSON under `var/routing_kit/`) inside Beacon kit-admin chrome (`kit/routing_kit_panel_layout.html.twig`).

### US2 — Default-locale bare paths (P1)

As an operator configuring RoutingKit, **When** `register_unprefixed_default: true` and a route has a default-locale path, **Then** the unprefixed URL is registered for that locale (same product idea as AuthKit `unlocalized: serve`).

### US3 — Discovery of domain controllers (P2)

As a developer, **When** I add `#[Routable]` on controllers under Beacon domain folders, **Then** discovery `scan_dirs` (Legal, Identity, Project, … — not only `src/Controller`) lists them in the panel.

## Requirements

- **FR-001**: Require `nowo-tech/routing-kit-bundle` (pinned) and register the bundle.
- **FR-002**: Config `nowo_routing_kit` MUST use `%default_locale%` / `%fallback_locales%`, host layout, `security.access_roles: [ROLE_ADMIN]`, and `register_unprefixed_default: true`.
- **FR-003**: Panel routes import via `config/routes/nowo_routing_kit.yaml`; DB/JSON loader `type: nowo_routing_kit` MUST be imported **last** in `config/routes.yaml`.
- **FR-004**: `access_control` MUST require `ROLE_ADMIN` for `^/admin/_routing`. Setup/catalog gates MUST exclude `/admin/_routing`.
- **FR-005**: Administration hub SHOULD link to `nowo_routing_kit_panel` with i18n keys `nav.routing` / `admin.hub.routing`.
- **FR-006**: AuthKit and SiteBackup locale routing remain owned by those bundles (`002`, `056`); RoutingKit MUST NOT be required to dual-serve `/login` or `/setup`.

## Out of Scope

- Migrating legal/AuthKit/SiteBackup onto RoutingKit in this slice.
- SeoKit install (bridge may stay enabled; no-ops without SeoPathBuilder).
- Replacing account-preference locale on dashboard URLs.
