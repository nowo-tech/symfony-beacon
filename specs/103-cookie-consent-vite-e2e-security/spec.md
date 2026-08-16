# Feature Specification: Cookie consent Vite bundle + dogfood admin resolve + E2E security denials

**Feature Branch**: `103-cookie-consent-vite-e2e-security`  
**Created**: 2026-08-17  
**Status**: Done (v1.20.0 / Phase 6.54)  
**Roadmap**: Phase 6.54  

**Input**: Keep CookieConsent kit skin visible under common ad blockers (bundle kit CSS into Vite `app`); harden `make dogfood` ownership so leftover `admin@…` / `--email` never win over the earliest registered `ROLE_ADMIN`; close the negative-access Playwright catalog (`UC-SEC-01`…`12`).

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| C1 | Cookie CSS delivery | Import kit `nowo-cookie-consent.css` from `assets/app.ts`; layouts set `data-nowo-cookie-consent-external-css="true"` (no `/bundles/nowocookieconsent/*` `<link>`) |
| C2 | Host bridge | Thin `assets/styles/_cookie_consent.scss` for footer clearance + bottom-left position fallbacks; Tailwind `@source` vendor CookieConsent Twig |
| C3 | PWA | Bump `nowo_pwa` service-worker `cache_version` to `v5` so stale SW caches drop |
| D1 | Dogfood owner | `--skip-demo-user` ignores `--email` / leftover `admin@symfony-beacon.local`; `UserRepository::findFirstInstanceAdmin()` (lowest id) owns / hints `.demo-client.env` |
| D2 | Membership | Grant **every** existing `ROLE_ADMIN` membership on the Symfony Beacon project (`findInstanceAdmins`) |
| E1 | E2E security | `e2e/security/` + `e2e/support/security.ts`; catalog §16 `UC-SEC-01`…`12` (~259 Covered) |

## Non-goals

- Changing CookieConsent Packagist pin or DB profile seed layout (`055` bottom-left remains)
- Creating a hard-coded personal email as preferred dogfood owner
- Automating Out of scope UC rows (empty-DB first user, SiteBackup restore, Native / dogfood UI)
- Doctrine migrations

## User Scenarios & Testing

### User Story 1 - Consent modal survives ad blockers (P1)

As a guest on a public AuthKit/legal shell, I see a styled Cookie Consent modal even when an ad blocker would strip `/bundles/nowocookieconsent/*` assets.

**Independent Test**: Built `/build/assets/app-*.css` contains kit CMP rules; HTML has `data-nowo-cookie-consent-external-css="true"` and no kit pack `<link>` for `nowo-cookie-consent.css`.

**Acceptance Scenarios**:

1. **Given** Vite `app` entry, **When** assets build, **Then** kit `nowo-cookie-consent.css` is imported into the app CSS bundle.
2. **Given** `base` / `guest_shell`, **When** the page renders, **Then** `<html>` carries `data-nowo-cookie-consent-external-css="true"` and does **not** link `/bundles/nowocookieconsent/nowo-cookie-consent.css`.
3. **Given** that marker, **When** `nowo-consent-modal.js` boots, **Then** it skips injecting a skin `<style>` tag.
4. **Given** the seeded bottom-left profile, **When** the modal opens, **Then** host `_cookie_consent.scss` keeps the box clear of `.site-legal-footer` and preferences bubble bottom-right (or seeded corner).

### User Story 2 - Dogfood prefers first registered admin (P1)

As a developer with a real `ROLE_ADMIN` plus a leftover `admin@symfony-beacon.local` from an old `make seed`, `make dogfood` grants the project to every admin and writes `.demo-client.env` with the **earliest** admin email — never a hard-coded personal address and never preferring leftover `admin@…` / `--email`.

**Independent Test**: `DemoIdentitySeederTest` + `SeedDemoCommandTest` dogfood cases; `UserRepository::findFirstInstanceAdmin` / `findInstanceAdmins`.

**Acceptance Scenarios**:

1. **Given** two `ROLE_ADMIN` users (earlier id ≠ `admin@…`), **When** `app:seed-demo --skip-demo-user` runs, **Then** ownership / login hint is the earliest admin and both receive membership.
2. **Given** `--skip-demo-user`, **When** `--email=admin@symfony-beacon.local` is also passed, **Then** that email is ignored for owner resolution.
3. **Given** no `ROLE_ADMIN`, **When** dogfood seed runs, **Then** it fails with a clear `LogicException` telling the operator to register first or run without `--skip-demo-user`.

### User Story 3 - Negative access control in Playwright (P1)

As a maintainer, CI proves guest redirects, role denials, membership demotion/deactivation, and auth gates stay closed — not only happy-path login.

**Independent Test**: `make test-e2e ARGS='e2e/security'`; catalog §16 marks `UC-SEC-01`…`12` Covered.

**Acceptance Scenarios**:

1. **Given** a guest, **When** protected routes are opened, **Then** login redirect (UC-SEC-01).
2. **Given** `ROLE_USER` without `ROLE_ADMIN`, **When** `/admin` is opened, **Then** branded 403 (UC-SEC-02).
3. **Given** viewer / member / project admin fixtures, **When** over-privileged surfaces are hit, **Then** 403 per matrix UC-SEC-04…06.
4. **Given** demotion or deactivated membership, **When** project settings are opened, **Then** access is revoked (UC-SEC-07/08).
5. **Given** invalid credentials / disabled account / closed register, **When** auth forms are used, **Then** gates hold (UC-SEC-09…12).

## Functional Requirements

- **FR-001**: Kit CookieConsent skin CSS MUST be imported into the Vite `app` CSS entry so delivery does not depend on `/bundles/nowocookieconsent/*` URLs.
- **FR-002**: Public layouts MUST set `data-nowo-cookie-consent-external-css="true"` and MUST NOT `<link>` the kit pack stylesheet on those pages.
- **FR-003**: Host MAY keep a thin `_cookie_consent.scss` bridge for footer collision and position fallbacks; Tailwind MUST `@source` CookieConsent vendor Twig utilities.
- **FR-004**: After this CSS delivery change, PWA SW `cache_version` MUST bump so browsers drop stale precaches.
- **FR-005**: With `--skip-demo-user`, demo seed MUST resolve owner via `findFirstInstanceAdmin()` and MUST ignore `--email` / leftover demo addresses for ownership.
- **FR-006**: Dogfood MUST grant Symfony Beacon project membership to **all** `findInstanceAdmins()` results (idempotent).
- **FR-007**: Playwright MUST cover `UC-SEC-01`…`12` under `e2e/security/` with shared helpers in `e2e/support/security.ts`; product catalog MUST list them.

## Success Criteria

- **SC-001**: Guest shells show styled CMP without kit pack CSS URL; ad-blocker path strip does not empty the modal chrome.
- **SC-002**: Dogfood unit/integration tests assert earliest-admin ownership and multi-admin grants.
- **SC-003**: E2E security suite green locally/CI; catalog ~259 Covered / ~5 Out of scope.

## Cross-links

- Docs: [`docs/product/LEGAL-AND-COOKIES.md`](../../docs/product/LEGAL-AND-COOKIES.md), [`docs/product/E2E-USE-CASES.md`](../../docs/product/E2E-USE-CASES.md) §16, [`docs/INSTALL.md`](../../docs/INSTALL.md), [`docs/product/ROLES.md`](../../docs/product/ROLES.md)
- Prior: `002`, `055`, `058`, `081`, `097`, `101`
