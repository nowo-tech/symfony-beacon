# Implementation Plan: Site-wide maintenance mode

**Branch**: `092-maintenance-mode` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)

**Status**: Implemented (host integration of `nowo-tech/maintenance-mode-bundle` **1.4.x**).

## Summary

Wire MaintenanceModeBundle into Beacon Administration with kit chrome, seed nav/breadcrumbs, and brand the public 503 page with `illustrations/error-503.png` (shared with `063`).

## Technical approach

| Area | Choice |
|------|--------|
| Package | `nowo-tech/maintenance-mode-bundle` (composer) |
| Config | `config/packages/nowo_maintenance_mode.yaml` + `config/routes/nowo_maintenance_mode.yaml` |
| Panel access | Symfony session + `ROLE_ADMIN`; `password_protection: false` |
| Panel chrome | `kit/maintenance_mode_panel_layout.html.twig` → `_kit_admin_layout` |
| Panel Twig | Host forks: `panel/index.html.twig`, `panel/history.html.twig`, `panel/_nav.html.twig` |
| Public page | Host fork: `maintenance/page.html.twig` + `error-503.png` |
| Nav | `src/Setup/Demo/fixtures/menus.json` + `breadcrumbs.default.json`; matcher prefixes in `AdministrationMenuCurrentMatcher` |
| Exclusions | AuthKit, health, assets, `/_error`, panel prefix (bundle auto), SiteBackup paths aligned |

## Constitutions / kits

Prefer official nowo-tech kits (MaintenanceMode + UiKit + DashboardMenu + Breadcrumb). Do not reintroduce a custom maintenance controller.

## Test plan

- Functional: `NowoKitsUiTest::testMaintenancePanelUsesBeaconAdminChrome`
- Integration: `HttpErrorPagesTest` includes 503 template + asset
- Manual: `/_maintenance_preview`, enable/disable round-trip, aside after `make seed-platform`

## Upgrade notes

Operators upgrading an existing DB MUST run `make seed-platform` (or `app:seed-platform`) so Administration menus/breadcrumbs pick up Maintenance items.
