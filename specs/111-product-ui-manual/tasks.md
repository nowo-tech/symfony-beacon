# Tasks: Product UI manual (`111`)

## Phase 1: Capture tooling

- [x] T001 Add `prepareProductionScreenshot` / `captureManualScreenshot` / `setManualTheme` / `ensureEnglishUi` in `e2e/support/helpers.ts`
- [x] T002 Playwright `PLAYWRIGHT_MANUAL` projects; `chromium` ignores `e2e/manual/`
- [x] T003 `e2e/manual/capture-screens.spec.ts` (prefs demos + inventory)
- [x] T004 `e2e/manual/capture-setup.spec.ts` (cold only)
- [x] T005 Make targets `docs-manual-screenshots` + `docs-manual-screenshots-setup`

## Phase 2: Documentation

- [x] T006 Write `docs/manual/` chapters `00`–`07` + README (English)
- [x] T007 Link from `docs/README.md` and root `README.md`
- [x] T008 Note `e2e/manual/` in `e2e/README.md`

## Phase 3: Capture & publish

- [x] T009 Run warm captures (1440×900) against `ready-e2e`
- [x] T010 Run cold setup captures (`wipe-e2e-cold` + `up-e2e-cold`)
- [x] T011 Open GitHub PR with Markdown + PNGs (`docs/product-ui-manual`)

## Phase 4: Specs / roadmap

- [x] T012 Add `specs/111-product-ui-manual/` + amend `104` / `110` + ROADMAP 6.63
