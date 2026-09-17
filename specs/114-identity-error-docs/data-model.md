# Data model: Identity book + error manual (114)

No Doctrine schema changes.

## Document entities (logical)

| Entity | Location | Notes |
|--------|----------|-------|
| Identity book | `docs/identity/README.md` + `images/` | Mark, tokens, type, mascot, applied screens |
| Error chapter | `docs/manual/08-errors.md` | One section per status + maintenance |
| Error page shots | `docs/manual/images/error-*.png` | 1440×900 captures (opaque OK) |
| Runtime mascot | `public/brand/mascot.png` | PNG RGBA transparent |
| Runtime error art | `public/illustrations/error-{code}.png` | PNG RGBA transparent; codes per `063` |
| Legal review record | `docs/product/LEGAL-AND-COOKIES.md` | REQ-CC-010 table (counsel pending) |

## Configuration

| Key | File | Purpose |
|-----|------|---------|
| `nowo_site_backup.templates.panel_login` | `config/packages/nowo_site_backup.yaml` | Host branded panel login Twig |

## Test / harness artifacts

| Artifact | Role |
|----------|------|
| Capture list entries `error-*` | Manual screenshot inventory |
| `html-markup.ts` helpers | UC-UI-13 assertions |
| CSP subscriber unit fixtures | Style nonce stamp |
| `filterManualDashboardProjects` | Docs dashboard / prefs hygiene |
| `clearMaintenanceScheduleForManual` | Avoid absurd MM countdown in docs |
| `lockSetupEnglish` (cold) | Force EN setup PNGs |
| `filterManualAdminGroups` | Docs admin Groups demo row (`Beacon operators`) |
| `UserPreferredLocaleSubscriber` sub-request sync | Keep Translator aligned after fragments |
| `_legal_footer` explicit `|trans` locale | Footer labels match `app.request.locale` |
