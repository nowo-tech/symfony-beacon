# Tasks: Site-wide maintenance mode

**Feature**: `092-maintenance-mode`  
**Status**: Complete (implemented 2026-08-13)

## Phase 1 — Bundle + config

- [x] T001 Add `nowo-tech/maintenance-mode-bundle` and Flex/config packages
- [x] T002 Configure panel `/admin/maintenance`, preview `/_maintenance_preview`, `ROLE_ADMIN`, no password gate, AuthKit/health exclusions

## Phase 2 — Public page + 503 art

- [x] T003 Host override `maintenance/page.html.twig` with error-page chrome
- [x] T004 Use `public/illustrations/error-503.png` as hero (shared with `063`)
- [x] T005 Add `error.503.*` catalogue keys (enabled locales) and `error503.html.twig`

## Phase 3 — Admin chrome

- [x] T006 `kit/maintenance_mode_panel_layout.html.twig` + host panel index/history/_nav (beacon tabs, panels, form stack)
- [x] T007 Seed Administration menu + breadcrumbs; matcher for `nowo_maintenance_mode_*`
- [x] T008 Hub tiles for Maintenance + Maintenance preview

## Phase 4 — Verification

- [x] T009 Functional kit chrome assertions; HttpErrorPages 503 asset/template coverage
- [x] T010 Document upgrade seed step in plan / UPGRADING
