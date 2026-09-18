# Feature Specification: Product UI manual (Playwright screenshots)

**Feature Branch**: `docs/product-ui-manual`  
**Created**: 2026-09-10  
**Status**: Implemented (PR #53 / Phase 6.63); prose catalog amendment 2026-09-11; **errors chapter + identity companion** (Phase 6.66 / `114`, Unreleased)  
**Roadmap**: Phase 6.63 (core); chapter `08` + identity link → Phase 6.66  

**Input**: Operators and contributors need an English, production-looking **product UI manual** with screenshots of setup, auth, dashboard, account, projects, admin, legal, **branded HTTP errors**, and theme surfaces. Each chapter MUST read as an operator screen catalog: for every page, state what it is and what it contributes, then show the capture. Captures MUST hide development chrome (Symfony WDT / Twig Inspector / Vite overlay), use a **fixed 1440×900** viewport (no stretched full-page images that break aside chrome), demonstrate **day/night** and **language** switching once on a public route and once on a private route, then keep the inventory in **English + day**. Setup wizard shots MUST use the disposable cold stack (`110`), never warm `app_e2e` / dogfood. Brand rules live in the separate visual identity book (`docs/identity/`), not as a wiki dump.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| M1 | Docs | `docs/manual/` — README + chapters `00`…`08` (setup, getting started, dashboard, account, projects, admin, legal, theme/language, **errors**) |
| M2 | Images | `docs/manual/images/*.png` at **1440×900**; tall pages use same-size `-2` companions |
| M3 | Capture | `e2e/manual/capture-screens.spec.ts` + helpers `prepareProductionScreenshot` / `captureManualScreenshot` / `setManualTheme` / `ensureEnglishUi` |
| M4 | Setup shots | `e2e/manual/capture-setup.spec.ts` on cold stack (`PLAYWRIGHT_MANUAL=1` + `PLAYWRIGHT_COLD=1`) |
| M5 | Make | `docs-manual-screenshots` (warm isolated); `docs-manual-screenshots-setup` (cold) |
| M6 | Theme/locale | Prefs demos: `prefs-public-*` / `prefs-private-*` (theme day/night + open locale menu); inventory stays EN + day |
| M7 | Playwright | `PLAYWRIGHT_MANUAL=1` project; product `chromium` `testIgnore`s `e2e/manual/` |
| M8 | Prose | Each screen documented as purpose → what it contributes → screenshot; no bare consecutive image stacks; every committed PNG referenced from a chapter |

## Non-goals

- CI gate that regenerates screenshots on every PR
- Dual theme (or dual locale) for every screen in the inventory
- GitHub Pages site (native GitHub Markdown rendering is enough)
- Capturing Envelope/OTLP/profiler/webhook-only surfaces
- Advancing `/setup/api/*` against warm `app_e2e` or dogfood

## User Scenarios & Testing

### User Story 1 - Read the manual on GitHub (P1)

As an operator, I open `docs/manual/README.md` on GitHub and see inline screenshots that look like production (no WDT), with consistent framing. Each chapter explains what a screen is for and what it contributes **before** showing the image (including `*-2` companions).

**Independent Test**: View PR / main tree; images render; sample PNGs are 1440×900; no chapter dumps consecutive images without intervening prose.

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
- **FR-009**: Each documented screen MUST include short English prose stating (a) what the page/section is and (b) what it contributes to operators, placed **before** its screenshot(s). Chapters MUST NOT stack multiple inventory images with only alt captions and no explanatory body text between them.
- **FR-010**: Every PNG under `docs/manual/images/` that is committed for the inventory MUST be referenced from at least one chapter (including `*-2` companions when present).
- **FR-011**: `docs/manual/README.md` MUST explain how to read the manual and that `*-2.png` companions continue the same viewport (scroll), not a different layout.
- **FR-012**: Wiki Home (`docs/wiki/Home.md`) MUST remain an index into `docs/manual/` (not a second copy of screenshots) and MAY summarize the prose convention.
- **FR-013**: Manual MUST include chapter `08-errors.md` covering branded HTTP error pages + maintenance preview (`/_error/{code}`, `/_maintenance_preview`), with inventory PNGs `error-*.png` / `error-maintenance.png` captured at 1440×900 under English day chrome (see `063`, `114`).
- **FR-014**: Manual README (and wiki Home) MUST link the visual identity book at `docs/identity/` as a related companion; identity MUST NOT duplicate the operator screenshot catalog.
- **FR-015**: Dashboard home and private prefs theme shots MUST filter to the demo project (`Symfony Beacon`) so E2E/XSS seed titles do not appear in committed inventory PNGs (`114` FR-010).
- **FR-016**: Cold setup captures MUST force English UI before writing PNGs; maintenance preview captures MUST clear leftover far-future schedules first (`114` FR-011 / FR-012).
- **FR-017**: Admin Groups inventory shot MUST show a human demo group (`Beacon operators`, filtered) without Playwright `E2E*` row names; authenticated legal footer labels MUST match UI locale (`114` FR-013 / FR-014).
- **FR-018**: Admin and legal chapters MUST document Legal pages with `admin-legal.png` (list, built-in badges) and `admin-legal-edit.png` (English notice, styled editor). The suite MUST wait until the editor is visible. A scroll companion that only shows open toolbar menus (`admin-legal-edit-2.png`) MUST NOT be committed (`115`).

## Success Criteria

- `docs/manual/` published and linked; images render on GitHub.
- All committed documentation PNGs are 1440×900.
- `make docs-manual-screenshots` and `make docs-manual-screenshots-setup` are documented and runnable locally.
- Warm product E2E does not execute the manual capture specs.
- Chapters read as an operator screen catalog (purpose + contribution per screen); inventory PNG reference set is complete (no orphan PNGs).
- Chapter `08` documents every supported error capture; identity book is linked from manual/wiki indexes.
- Inventory hygiene: EN setup gate, clean dashboard card, EN legal footer, clean admin-groups row, maintenance preview without absurd ETA.

## Amendment (`112-admin-create-modals`, 2026-09-11)

- Create shots that used full-page `…/new` MUST capture the modal open query instead:
  - `admin-groups-new` → `/admin/groups?new=1`
  - `admin-projects-new` → `/admin/projects?new=1`
  - `project-threshold-rules-new` → `/projects/{uuid}/settings/alerts?new_threshold=1`
- Chapters MAY note that create opens as a modal on the parent page (edit may stay full page).
- Wiki Home remains an index into `docs/manual/` (not a second copy of screenshots).

## Amendment (operator prose polish, 2026-09-11)

- Rewrite chapters `00`–`07` + README so each screen has purpose / contribution prose before images (closes bare image stacks).
- Reference previously unlinked companions (`projects-new-2`, `project-settings-access-2`, `project-settings-alerts-2`, `project-notifications-help-2`, `admin-users-new-2`, `admin-user-activity-2`, …).
- Align `docs/wiki/Home.md` conventions with the prose rule; capture tooling unchanged.

## Amendment (errors chapter + identity companion, 2026-09-17 / `114`)

- Add chapter `08-errors.md` + capture inventory for `/_error/{code}` and `/_maintenance_preview`.
- Link `docs/identity/` from manual README and wiki Home (REQ-DOCS-APP-005); brand book stays separate from the screen catalog.
- Runtime transparent PNG rule remains in `063` FR-002; documentation page shots under `docs/manual/images/error-*.png` may be opaque captures.

## Amendment (manual capture hygiene, 2026-09-17 / `114`)

- Dashboard / private prefs: `filterManualDashboardProjects` before PNG write.
- Maintenance preview: `clearMaintenanceScheduleForManual` before capture (avoids E2E 2099 schedules).
- Cold setup: `lockSetupEnglish` + path-locale EN; assert English chrome.
- See `114` FR-010…012 / US6.

## Amendment (footer locale + admin Groups, 2026-09-17 / `114`)

- Admin Groups: `filterManualAdminGroups` + `/admin/groups?q=Beacon`; Makefile purges `%e2e%` groups before warm docs captures.
- App chrome legal footer must stay in sync with UI locale (sub-request Translator sync + explicit footer `|trans`).
- See `114` FR-013 / FR-014 / US7; this package FR-017.

## Amendment (operator legal editor shots, 2026-09-18 / `115`)

- Capture list adds `admin-legal` (`/admin/legal`) and `admin-legal-edit` (`/admin/legal/notice/en`, single viewport).
- Editor CSS in that PNG depends on the legal-admin style policy in `053` / `115` FR-009 (no style nonce on that path).

## Amendment (form dialog width, 2026-09-18 / `112`)

- `project-threshold-rules-new`, `admin-roles-new`, and `admin-permissions-new` were recaptured after `112` FR-008. They MUST NOT show the scrollbar hairline. Width rules live in `112`, not here.

## Cross-refs

- Warm E2E: `specs/104-isolated-e2e-stack/`
- Cold setup circuit: `specs/110-e2e-cold-start-circuit/`, `specs/056-setup-wizard/`
- Appearance / theme: `specs/082-appearance-theme-presets/`
- Create-modal inventory: `specs/112-admin-create-modals/`
- Branded errors: `specs/063-branded-http-errors/`
- Identity book + errors docs package: `specs/114-identity-error-docs/`
- Operator legal editor: `specs/115-operator-legal-editor/`
- Manual tree: `docs/manual/`
- Identity tree: `docs/identity/`
- Quickstart: `specs/111-product-ui-manual/quickstart.md`
