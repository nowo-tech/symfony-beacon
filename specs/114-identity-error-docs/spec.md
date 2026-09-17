# Feature Specification: Visual identity book + error manual docs

**Feature Branch**: `114-identity-error-docs` (working tree / Unreleased)  
**Created**: 2026-09-17  
**Status**: Implemented in tree (Unreleased / Phase 6.66) — awaiting PR/tag  
**Roadmap**: Phase 6.66  

**Input**: Contributors need an English **visual identity** book (mark, moss tokens, type, mascot, applied screens) separate from the operator UI catalog. Operators need a **manual chapter for branded HTTP errors** with 1440×900 captures. Runtime mascot / error illustrations MUST be real transparent PNGs. Supporting polish: CSP nonces on inline `<style>`, warm HTML/Twig markup smoke, opt-in Mailpit Make lane, a branded SiteBackup panel-login Twig override, and **manual capture hygiene** so inventory PNGs stay English, free of E2E/XSS seed noise, free of absurd maintenance countdowns, with English legal footers and clean admin Groups rows.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| I1 | Identity book | `docs/identity/` — `README.md` + preview `images/`; linked from docs index, product manual, wiki Home, root README (OTHER **REQ-DOCS-APP-005**) |
| I2 | Error manual | `docs/manual/08-errors.md` + `docs/manual/images/error-*.png` / `error-maintenance.png` (1440×900); wiki + manual README index |
| I3 | Capture | Warm manual suite captures `/_error/{code}` + `/_maintenance_preview` via gate helper; EN UI forced for error shots |
| I4 | Art format | Runtime `public/brand/mascot.png` + `public/illustrations/error-*.png` MUST be PNG **RGBA transparent canvas** (REQ-ERROR-001 item 9); PHPUnit asserts IHDR; amend `063` FR-002 |
| I5 | CSP | `ContentSecurityPolicySubscriber` stamps request nonce onto bare `<style>` (parity with inline scripts) so maintenance / error chrome stays CSP-safe |
| I6 | Markup E2E | `e2e/support/html-markup.ts` + `e2e/smoke/html-twig-standardization.spec.ts` (UC-UI-13) |
| I7 | Mailpit lane | `make test-e2e-mailpit` — Compose `mail` profile + `PLAYWRIGHT_MAILPIT=1` + serial workers (UC-AUTH-18/20) |
| I8 | SiteBackup | Host Twig `kit/site_backup_panel_login.html.twig` wired as `panel_login` override |
| I9 | Specs index | `specs/README.md` points maintainers at Spec Kit folders + constitution |
| I10 | Capture hygiene | Dashboard/prefs demo filter; MM schedule clear; cold setup EN; admin Groups demo row; English legal footer under app chrome |

## Non-goals

- Counsel-approved legal copy (REQ-CC-010 remains ⚠️ placeholders — recorded in LEGAL-AND-COOKIES + ENGINEERING-AUDIT)
- Regenerating identity preview art on every CI run
- Changing error Twig layout / status code set beyond `063`
- Making Mailpit delivery part of default `test-e2e-smoke`
- Dual theme captures for every error code
- Purging every admin identity table (Users/Roles/…) of E2E rows — Groups is the required docs hygiene target

## User Scenarios & Testing

### User Story 1 - Read the visual identity book (P1)

As a designer or implementer, I open `docs/identity/README.md` and see mark, palette, type, mascot rules, and applied 1440×900 screens without treating the wiki as a second brand dump.

**Independent Test**: Links from `docs/README.md`, `docs/manual/README.md`, `docs/wiki/Home.md`, and root README resolve; preview images under `docs/identity/images/` render on GitHub.

### User Story 2 - Document branded HTTP errors (P1)

As an operator, I read `docs/manual/08-errors.md` and see purpose → contribution → screenshot for each supported status (and maintenance preview).

**Independent Test**: Every `docs/manual/images/error-*.png` is referenced; captures are 1440×900; English day chrome (no WDT).

### User Story 3 - Regenerate error screenshots (P1)

As a maintainer with warm `ready-e2e` (`APP_ENV=dev`), I run `make docs-manual-screenshots` and refresh error inventory PNGs from `/_error/{code}` and `/_maintenance_preview`.

**Independent Test**: Capture list includes `error-400`…`503` + `error-maintenance`; gate fails the suite if any error-page shot is not EN / production-like.

### User Story 4 - Transparent runtime art (P1)

As a QA gate, PHPUnit fails if mascot or error illustrations are JPEG-renamed or opaque RGB boxes.

**Independent Test**: `HttpErrorPagesTest` (or equivalent) asserts PNG signature + color type 6 (RGBA) for `public/brand/mascot.png` and each `public/illustrations/error-{code}.png`.

### User Story 5 - CSP-safe inline styles (P2)

As a visitor on maintenance / error chrome with inline `<style>`, the response includes `nonce=` matching the CSP header so styles are not blocked when `style-src-elem` is nonce-based.

**Independent Test**: Unit test stamps nonce; warm markup smoke reads raw HTML and asserts style/script nonces (UC-UI-13).

### User Story 6 - Manual capture hygiene (P1)

As a maintainer regenerating docs screenshots, inventory PNGs MUST look like a production English demo — not a polluted E2E DB or leftover MM schedule.

**Independent Test**:
- `dashboard.png` / `prefs-private-theme*.png` show the demo project only (search `Symfony Beacon`); no `onerror=` / XSS payload titles; legal footer labels are English when `html[lang^=en]`.
- `admin-groups.png` shows a human demo group (`Beacon operators`); no `E2E` row names in main.
- `error-maintenance.png` has no multi-year countdown (clear schedule before preview, or short until).
- `setup-gate.png` (and other cold setup shots) render English (`FIRST RUN` / `Continue`) even when cold `DEFAULT_LOCALE` was temporarily `es`.

### User Story 7 - Authenticated legal footer matches UI locale (P1)

As a signed-in user with preferred locale `en`, legal footer link labels MUST be English even when the page embeds a Twig fragment sub-request (`render(controller(…))`) after the main body.

**Independent Test**: Dashboard HTML with preferred locale `en` contains `Legal notice` / `Privacy policy` (not `Aviso legal`); unit test covers sub-request locale sync on `UserPreferredLocaleSubscriber`.

## Functional Requirements

- **FR-001**: `docs/identity/` MUST document shipped Beacon moss identity (mark, tokens, type, mascot, applied screens) in English and MUST NOT duplicate the operator screenshot catalog.
- **FR-002**: Product UI manual MUST include chapter `08-errors.md`; wiki Home and manual README MUST index it; identity book MUST be linked as a related companion (not a wiki dump).
- **FR-003**: Manual capture suite MUST produce 1440×900 PNGs for supported error codes + maintenance preview; inventory commits MUST reference every error PNG.
- **FR-004**: Runtime mascot and error illustrations MUST be real PNG with transparent canvas (RGBA); documentation page screenshots under `docs/manual/images/error-*.png` remain ordinary captures (may be opaque).
- **FR-005**: CSP subscriber MUST stamp the request nonce onto inline `<style>` elements that lack one (same pattern as inline scripts).
- **FR-006**: Warm product E2E MUST include HTML/Twig standardization smoke (`html-twig-standardization.spec.ts`) without requiring Mailpit.
- **FR-007**: `make test-e2e-mailpit` MUST bring up profile `mail`, set `PLAYWRIGHT_MAILPIT=1` / `PLAYWRIGHT_WORKERS=1` / deliverable DSN, and run Mailpit auth specs; default smoke MUST remain free of that dependency.
- **FR-008**: SiteBackup `panel_login` template override MUST be configured to the host kit Twig when branding the backup panel login.
- **FR-009**: `specs/063-branded-http-errors` FR-002 MUST record the transparent-PNG rule; `specs/111-product-ui-manual` MUST list chapter `08` and identity cross-ref.
- **FR-010**: Before `dashboard` / private prefs theme shots, the capture harness MUST filter projects to the demo name (`Symfony Beacon` via `/dashboard?q=` or submitted search) and MUST fail if XSS payload markers remain visible in main.
- **FR-011**: Before `error-maintenance` capture, the harness MUST clear (or replace with a short) maintenance schedule so leftover E2E far-future dates do not render absurd countdowns.
- **FR-012**: Cold setup captures MUST force English UI (`lockSetupEnglish` / path-locale `a[hreflang=en]` / `ensureEnglishUi`) and assert `html[lang^=en]` before writing PNGs.
- **FR-013**: Before `admin-groups` capture, the harness MUST ensure a human demo group (`Beacon operators`) and filter (`/admin/groups?q=Beacon`) so Playwright `E2E*` seed rows do not dominate the table; `make docs-manual-screenshots` MUST purge `%e2e%` groups beforehand when possible.
- **FR-014**: Authenticated app chrome legal footer labels MUST use `app.request.locale` (explicit `|trans` locale on `_legal_footer`); `UserPreferredLocaleSubscriber` MUST sync fragment sub-requests from the main request locale so a shared Translator cannot remain on `DEFAULT_LOCALE` after `render(controller(…))`.

## Success Criteria

- Identity book published and linked (REQ-DOCS-APP-005 evidence in ENGINEERING-AUDIT).
- Manual chapter 08 + error PNGs committed and regenerable via Make.
- PHPUnit rejects non-transparent runtime error/mascot PNGs.
- Markup smoke + CSP style nonce green on warm E2E.
- `make test-e2e-mailpit` documented in `e2e/README.md` / Makefile help.
- ROADMAP Phase 6.66 / CHANGELOG Unreleased describe this feature.
- Recaptured inventory passes L&F hygiene: EN setup gate, clean dashboard card, EN legal footer, clean admin-groups row, maintenance preview without absurd ETA.

## Assumptions

- `/_error/{code}` remains `when@dev` only (`063` FR-004).
- Legal placeholders stay until counsel (REQ-CC-010).
- Identity preview assets under `docs/identity/images/` may be lossy copies for Markdown preview; runtime files under `public/` are canonical.
- Other admin identity tables (Users / Roles / …) MAY still show E2E-named rows; Groups is the required hygiene shot for this package.

## Amendment (manual capture hygiene, 2026-09-17)

- Helpers: `filterManualDashboardProjects`, `clearMaintenanceScheduleForManual`; setup `lockSetupEnglish`; `ensureEnglishUi` prefers path-locale `<a hreflang="en">`.
- Amends `111` FR-015 / FR-016.

## Amendment (footer locale + admin Groups docs, 2026-09-17)

- Root cause: Twig `render(controller(…))` sub-requests left shared Translator on `DEFAULT_LOCALE` after the main body; footer `|trans` without explicit locale then rendered Spanish while UI stayed English.
- Fix: sub-request sync in `UserPreferredLocaleSubscriber` + explicit locale on `_legal_footer` `|trans`.
- Docs: `filterManualAdminGroups` + Makefile E2E group purge; recapture `dashboard` / prefs / `admin-groups`.
- Amends `111` FR-017; adds FR-013 / FR-014 / US7 here.

## Cross-refs

- Branded errors: `specs/063-branded-http-errors/`
- Product UI manual: `specs/111-product-ui-manual/`
- Maintenance: `specs/092-maintenance-mode/`
- Cold setup: `specs/110-e2e-cold-start-circuit/`, `specs/056-setup-wizard/`
- E2E stack / sharding: `specs/104-isolated-e2e-stack/`, `specs/113-e2e-product-sharding/`
- OTHER: REQ-DOCS-APP-005, REQ-ERROR-001, REQ-CC-010, REQ-DOCS-APP-003
- Quickstart: `specs/114-identity-error-docs/quickstart.md`
