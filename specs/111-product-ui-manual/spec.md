# Feature Specification: Product UI manual (Playwright screenshots)

**Feature Branch**: `docs/product-ui-manual`  
**Created**: 2026-09-10  
**Status**: Implemented (PR #53 / Phase 6.63)  
**Roadmap**: Phase 6.63  

**Input**: Operators and contributors need an English, production-looking **product UI manual** with screenshots of setup, auth, dashboard, account, projects, admin, and legal surfaces. Captures MUST hide development chrome (Symfony WDT / Twig Inspector / Vite overlay), use a **fixed 1440×900** viewport (no stretched full-page images that break aside chrome), demonstrate **day/night** and **language** switching once on a public route and once on a private route, then keep the inventory in **English + day**. Setup wizard shots MUST use the disposable cold stack (`110`), never warm `app_e2e` / dogfood.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| M1 | Docs | `docs/manual/` — README + chapters `00`…`07` (setup, getting started, dashboard, account, projects, admin, legal, theme/language) |
| M2 | Images | `docs/manual/images/*.png` at **1440×900**; tall pages use same-size `-2` companions |
| M3 | Capture | `e2e/manual/capture-screens.spec.ts` + helpers `prepareProductionScreenshot` / `captureManualScreenshot` / `setManualTheme` / `ensureEnglishUi` |
| M4 | Setup shots | `e2e/manual/capture-setup.spec.ts` on cold stack (`PLAYWRIGHT_MANUAL=1` + `PLAYWRIGHT_COLD=1`) |
| M5 | Make | `docs-manual-screenshots` (warm isolated); `docs-manual-screenshots-setup` (cold) |
| M6 | Theme/locale | Prefs demos: `prefs-public-*` / `prefs-private-*` (theme day/night + open locale menu); inventory stays EN + day |
| M7 | Playwright | `PLAYWRIGHT_MANUAL=1` project; product `chromium` `testIgnore`s `e2e/manual/` |

## Non-goals

- CI gate that regenerates screenshots on every PR
- Dual theme (or dual locale) for every screen in the inventory
- GitHub Pages site (native GitHub Markdown rendering is enough)
- Capturing Envelope/OTLP/profiler/webhook-only surfaces
- Advancing `/setup/api/*` against warm `app_e2e` or dogfood

## User Scenarios & Testing

### User Story 1 - Read the manual on GitHub (P1)

As an operator, I open `docs/manual/README.md` on GitHub and see inline screenshots that look like production (no WDT), with consistent framing.

**Independent Test**: View PR / main tree; images render; sample PNGs are 1440×900.

### User Story 2 - Regenerate product screenshots (P1)

As a maintainer with `ready-e2e`, I run `make docs-manual-screenshots` and refresh PNGs without mutating dogfood.

**Independent Test**: Isolated stack live → Make target → PNGs under `docs/manual/images/` updated; `chromium` product suite still ignores `e2e/manual/`.

### User Story 3 - Document theme and language once (P1)

As a reader, I learn how to switch day/night and locale on a **public** page and a **private** page, then see the rest of the manual in English day theme.

**Independent Test**: Chapter `07-appearance.md` shows `prefs-public-*` and `prefs-private-*`; other chapters reference it instead of duplicating dark variants.

### User Story 4 - Document first-time setup (P2)

As an operator, I see the cold-install wizard flow (gate → wizard → admin → done) without confusing it with warm E2E.

**Independent Test**: `make wipe-e2e-cold && make up-e2e-cold && make docs-manual-screenshots-setup` writes `setup-*.png`; never calls `ready-e2e`.

## Functional Requirements

- **FR-001**: Screenshots MUST use viewport **1440×900** and MUST NOT use Playwright `fullPage: true` for documentation PNGs.
- **FR-002**: Before capture, the suite MUST hide Symfony WDT / Twig Inspector / Vite error overlay and dismiss cookie consent + product tour.
- **FR-003**: Inventory captures MUST force English UI and day theme after the prefs demos.
- **FR-004**: Theme/language demos MUST cover at least one public route (`/login`) and one private route (`/dashboard`).
- **FR-005**: Setup captures MUST run only with `PLAYWRIGHT_COLD=1` against the cold base URL; warm product capture MUST use isolated `:9460`.
- **FR-006**: Product Playwright `chromium` MUST ignore `e2e/manual/`; manual mode MUST NOT run as part of `test-e2e-isolated` by default.
- **FR-007**: Docs index (`docs/README.md`) and root README MUST link the manual.
- **FR-008**: Tall content MAY emit same-size scroll companions (`{name}-2.png`, optional `-3`); companions MUST keep 1440×900.

## Success Criteria

- `docs/manual/` published and linked; images render on GitHub.
- All committed documentation PNGs are 1440×900.
- `make docs-manual-screenshots` and `make docs-manual-screenshots-setup` are documented and runnable locally.
- Warm product E2E does not execute the manual capture specs.

## Cross-refs

- Warm E2E: `specs/104-isolated-e2e-stack/`
- Cold setup circuit: `specs/110-e2e-cold-start-circuit/`, `specs/056-setup-wizard/`
- Appearance / theme: `specs/082-appearance-theme-presets/`
- Manual tree: `docs/manual/`
- Quickstart: `specs/111-product-ui-manual/quickstart.md`
