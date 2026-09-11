# Implementation Plan: Product UI manual (`111`)

**Branch**: `docs/product-ui-manual`  
**Spec**: `specs/111-product-ui-manual/spec.md`

## Technical approach

1. **Helpers** (`e2e/support/helpers.ts`): inject CSS to strip WDT/Twig/Vite; `ensureEnglishUi`; `setManualTheme` (`beacon-theme` + `data-theme`); `captureManualScreenshot` scrolls in viewport-height steps (max 3 parts), never `fullPage`.
2. **Warm crawl** (`e2e/manual/capture-screens.spec.ts`): serial groups — prefs demos → guest/legal → dashboard → account → project/issues → admin; `PLAYWRIGHT_MANUAL=1` + isolated auth.
3. **Cold crawl** (`e2e/manual/capture-setup.spec.ts`): gate + wizard + admin + done via setup API helpers from `110`.
4. **Playwright config**: mutually exclusive projects — `manual` (warm), `manual-setup` (cold+manual), ignore `manual/` in product `chromium`.
5. **Make**: Docker Playwright image with `--shm-size` / memory cap; group runs locally if WSL OOM.
6. **Docs**: English chapters under `docs/manual/`; chapter 07 owns theme/locale; other chapters stay day EN.
7. **Prose catalog**: Each screen section uses purpose → contribution → screenshot; README explains `*-2` companions; wiki Home stays an index. Capture paths unchanged when only prose is refreshed.

## Dependencies

- Specs `104` (warm stack), `110` (cold stack), `056` (setup product), `082` (theme presets), `112` (create-modal inventory URLs).

## Risks

- WSL Docker Chromium OOM on long crawls → run Make greps / groups; viewport-only reduces weight vs fullPage.
- Auth setup `waitForURL` load hang → use `waitUntil: 'domcontentloaded'` in `auth.setup.ts`.
- Dirty E2E dashboard (ephemeral projects) → filter search to “Symfony Beacon” for dashboard prefs/home shots.
- Prose drift after UI renames → when recapturing, update the matching chapter section in the same change (FR-009 / FR-010).
