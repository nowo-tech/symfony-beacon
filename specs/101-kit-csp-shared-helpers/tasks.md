# Tasks: Kit CSP upstream + shared helpers

**Feature**: `101-kit-csp-shared-helpers`  
**Status**: Done (Unreleased / Phase 6.52)

## Phase 1: Kit releases

- [x] T001 PhoneInput **1.3.0** — CSP-safe picker JS + widget + CSS; tag + Packagist
- [x] T002 CookieConsent **1.9.0** — standalone CSS, skip inject, Beacon skin; tag + Packagist (`master`)
- [x] T003 FormKit **2.4.0** — CSRF-only / GET filter helpers + docs/CSRF.md; tag + Packagist
- [x] T004 UiKit **1.8.0** — clipboard/tabs IIFEs + Stimulus peers + STIMULUS.md; tag + Packagist

## Phase 2: Beacon consume

- [x] T005 Pin four kits in `composer.json` / lock; `assets:install`
- [x] T006 Remove phone Twig override, `phone_prefix_picker`, `_phone_input.scss`; link kit CSS on Profile
- [x] T007 Remove `_cookie_consent.scss`; link `nowo-cookie-consent.css` on `base` + `guest_shell`
- [x] T008 Switch to FormKit CSRF/GET FQCNs; migrate `createNamed` / `create`; slim host `AbstractGetFilterType`
- [x] T009 Re-export UiKit Stimulus peers; drop phone-prefix from `stimulus_bootstrap.ts`
- [x] T010 Docs: CHANGELOG Unreleased, UPGRADING, LEGAL-AND-COOKIES, CONTRIBUTING

## Phase 3: Specs / roadmap

- [x] T011 Spec `101` + amend `100` / `090` / `081` / `002`
- [x] T012 ROADMAP Phase **6.52**; `feature.json` → `101-kit-csp-shared-helpers`
- [x] T013 Kits rule: phone-input / cookie / FormKit CSRF / UiKit peers notes
