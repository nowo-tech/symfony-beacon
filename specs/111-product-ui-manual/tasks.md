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

## Phase 5: Operator prose polish (2026-09-11)

- [x] T013 Rewrite `docs/manual/` README + chapters `00`–`07` with purpose / contribution prose before each screenshot (no bare image stacks)
- [x] T014 Reference all companion PNGs (`*-2`) from chapters; verify zero orphan images under `docs/manual/images/`

## Phase 6: Errors chapter + identity companion (`114`, 2026-09-17)

- [x] T015 Add `docs/manual/08-errors.md` + warm capture paths for `error-*` / `error-maintenance`
- [x] T016 Index chapter 08 + link `docs/identity/` from manual README and wiki Home
- [x] T017 Amend this spec (FR-013 / FR-014) and point follow-through at `specs/114-identity-error-docs/`

## Phase 7: Manual capture hygiene (`114`, 2026-09-17)

- [x] T018 Amend FR-015 / FR-016 (dashboard filter, MM schedule clear, cold setup EN)
- [x] T019 Recapture hygiene PNGs via warm + cold Make targets (see `114` T024–T027)

## Phase 8: Footer locale + admin Groups (`114`, 2026-09-17)

- [x] T020 Amend FR-017 (admin Groups demo row + EN legal footer)
- [x] T021 Recapture `dashboard` / prefs / `admin-groups` (see `114` T029–T032)
