# Implementation Plan: Visual identity book + error manual docs

**Branch**: `114-identity-error-docs` | **Date**: 2026-09-17 | **Spec**: [spec.md](./spec.md)  
**Roadmap**: Phase 6.66  

## Summary

Publish the English visual identity book, complete the product UI manual with chapter **08 Errors**, enforce transparent PNG runtime art, stamp CSP nonces on inline styles, and add warm markup + Mailpit Make lanes that keep default smoke lean.

## Technical Context

| Item | Choice |
|------|--------|
| Docs | Markdown under `docs/identity/`, `docs/manual/08-errors.md`; English only |
| Art | Runtime PNG RGBA under `public/brand/` + `public/illustrations/`; PHPUnit IHDR checks |
| Captures | Playwright `e2e/manual/capture-screens.spec.ts` + `manualPageGate` / EN error UI; hygiene helpers `filterManualDashboardProjects`, `clearMaintenanceScheduleForManual`, `filterManualAdminGroups`; cold `lockSetupEnglish` |
| Locale | `UserPreferredLocaleSubscriber` syncs fragment sub-requests; `_legal_footer` passes request locale into `|trans` |
| CSP | `ContentSecurityPolicySubscriber` style-elem nonce stamp |
| E2E | `html-markup.ts` + smoke; `make test-e2e-mailpit` |
| SiteBackup | `config/packages/nowo_site_backup.yaml` → `kit/site_backup_panel_login.html.twig` |

## Constitution Check

| Principle | Notes |
|-----------|-------|
| I Spec-driven | Spec + plan + tasks + quickstart before / with Unreleased ship |
| II Security | CSP style nonces; no new tracking cookies |
| III Quality | PHPUnit art format + warm E2E markup; Mailpit opt-in |
| IV DX | Make targets documented; identity book linked from README / wiki |
| V English docs | All new docs English |
| VI Tests | Capture gate + unit CSP + art assertions |
| VII Kits | SiteBackup panel_login override only; CookieConsent / legal still placeholder-aware |
| X No Cursor attribution | N/A for docs |

## Project Structure (touched)

```text
docs/identity/                          # brand book + preview images
docs/manual/08-errors.md
docs/manual/images/error-*.png
docs/manual/README.md                   # chapter index
docs/wiki/Home.md                       # links
docs/product/LEGAL-AND-COOKIES.md       # REQ-CC-010 review table
docs/ops/ENGINEERING-AUDIT.md           # identity ✅ / counsel ⚠️
public/brand/mascot.png
public/illustrations/error-*.png
src/Shared/EventSubscriber/ContentSecurityPolicySubscriber.php
tests/Unit/.../ContentSecurityPolicySubscriberTest.php
tests/... HttpErrorPages / art format assertions
e2e/manual/capture-screens.spec.ts
e2e/support/helpers.ts                # filterManual* / clearMaintenance* / ensureEnglishUi
e2e/support/html-markup.ts
e2e/support/manual-page-gate.ts
e2e/smoke/html-twig-standardization.spec.ts
Makefile                                # test-e2e-mailpit; docs-manual-screenshots E2E group purge
src/Identity/EventSubscriber/UserPreferredLocaleSubscriber.php
templates/_legal_footer.html.twig
templates/kit/site_backup_panel_login.html.twig
specs/063-branded-http-errors/spec.md   # FR-002 amend
specs/111-product-ui-manual/spec.md     # chapter 08 + identity
specs/114-identity-error-docs/          # this package
specs/README.md
```

## Implementation Phases

1. **Identity book** — write `docs/identity/`, link from indexes, mark REQ-DOCS-APP-005 in audit.
2. **Error chapter** — `08-errors.md`, captures, wiki/manual index; amend `111`.
3. **Art format** — regenerate / verify RGBA PNGs; PHPUnit; amend `063` FR-002.
4. **CSP + markup** — style nonce subscriber + unit test; html-twig smoke.
5. **Mailpit Make** — `test-e2e-mailpit` + e2e README.
6. **SiteBackup** — panel_login Twig + yaml.
7. **Capture hygiene** — dashboard demo filter, clear MM schedule, cold setup EN lock; recapture PNGs.
8. **Pre-tag polish** — legal footer locale (sub-request sync + explicit footer `|trans`); admin Groups docs filter; recapture.
9. **Close-out** — ROADMAP 6.66, CHANGELOG Unreleased, this tasks checklist.

## Complexity Tracking

| Topic | Note |
|-------|------|
| Identity vs manual | Identity = brand rules; manual = operator screenshots — keep separate |
| Transparent PNG | Page shots stay opaque; only runtime illustrations must be RGBA |
| Mailpit | Opt-in Make only — never block default smoke / CI shards |
| Capture hygiene | E2E DB pollution + 2099 MM schedules must not ship in docs PNGs |
| Footer locale lag | Fragment sub-request leaves shared Translator on DEFAULT_LOCALE — sync + explicit `|trans` locale |
| Admin Groups noise | Purge `%e2e%` groups + filter to `Beacon operators` for docs |

## Risks

| Risk | Mitigation |
|------|------------|
| Opaque / JPEG art slips in | PHPUnit IHDR + color type 6 |
| Error shots in ES locale | Gate forces EN for `error-*` captures |
| CSP breaks maintenance CSS | Style nonce stamp + smoke assertion |
| Dashboard shows XSS/E2E titles | `filterManualDashboardProjects` + assert no `onerror=` |
| Maintenance absurd countdown | `clearMaintenanceScheduleForManual` before preview |
| Cold setup ES when DEFAULT_LOCALE=es | `lockSetupEnglish` + path-locale `hreflang=en` |
| Footer ES while UI EN | Sub-request locale sync + `_legal_footer` explicit locale |
| Admin-groups E2E rows | `filterManualAdminGroups` + Makefile purge |

## Progress

| Area | Status |
|------|--------|
| Identity book + links | Done (Unreleased tree) |
| Chapter 08 + captures | Done (Unreleased tree) |
| Transparent PNG + 063 amend | Done / partial assert |
| CSP style nonce | Done (Unreleased tree) |
| html-twig smoke | Done (Unreleased tree) |
| `make test-e2e-mailpit` | Done (Unreleased tree) |
| SiteBackup panel_login | Done (Unreleased tree) |
| Capture hygiene + recaptures | Done (Unreleased tree) |
| Footer locale + admin Groups polish | Done (Unreleased tree) |
| Specs 114 + ROADMAP 6.66 | This package |
