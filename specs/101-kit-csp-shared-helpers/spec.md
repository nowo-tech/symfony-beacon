# Feature Specification: Kit CSP upstream + shared helpers

**Feature Branch**: `101-kit-csp-shared-helpers`  
**Created**: 2026-08-15  
**Status**: Done (v1.17.0 / Phase 6.52)  
**Roadmap**: Phase 6.52  

**Input**: Extract Beacon host forks for (1) phone prefix picker CSP, (2) cookie consent CSP-safe skin, (4) CSRF-only / GET filter Form helpers, (5) generic UI Stimulus controllers into the owning `nowo-tech/*` kits; cut kit releases; update Beacon to consume them and delete host duplicates. Priority **3** (SMS) is out of scope.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| K1 | PhoneInput **1.3.0** | CSP-safe external `nowo-phone-prefix-picker.js` + widget markup; kit `phone_input.css` progressive enhancement; no host Twig/Stimulus/SCSS fork |
| K2 | CookieConsent **1.9.0** | Standalone `nowo-cookie-consent.css`; skip `<style>` inject when `data-nowo-cookie-consent-css`; Beacon skin upstream; layouts link kit CSS |
| K4 | FormKit **2.4.0** | `CsrfOnlyType` / `HiddenFieldsCsrfType` / `SearchQueryType` / `CsrfOnlyFormFactory` / `GetFilterFormFactory` / kit `AbstractGetFilterType`; Beacon keeps dashboard helpers on `App\Shared\Form\AbstractGetFilterType` |
| K5 | UiKit **1.8.0** | IIFEs `nowo-ui-clipboard.js` / `nowo-ui-tabs.js`; Stimulus peers under `stimulus-peers/`; Beacon re-exports peers from vendor |
| B1 | Beacon consume | Pin kits; remove host forks; docs (`CHANGELOG` / `UPGRADING` / `LEGAL-AND-COOKIES` / `CONTRIBUTING`) |

## Non-goals

- SMS / OTP / AuthKit phone login (`phone_otp` remains ROADMAP Later)
- Upstreaming Beacon-specific Form helpers (`DashboardProjectFilterFields`, `EncryptedSecretFormApplier`, `AdminSearchType`, host `FormKitAbstractType` / `beacon` profile)
- Replacing Stimulus with IIFEs on product shell (Beacon keeps Stimulus; kit IIFEs are optional no-build path)
- Changing cookie seed bottom-left / equal-weight contract (`055` / `002`)

## User Scenarios & Testing

### User Story 1 - Profile phone under CSP without host fork (P1)

**Why this priority**: Host fork duplicated kit behaviour; kit 1.3 owns CSP-safe picker.

**Independent Test**: Account → Profile with CSP; prefix picker works; no `templates/bundles/NowoPhoneInputBundle` override; no `phone_prefix_picker_controller.ts`.

**Acceptance Scenarios**:

1. **Given** PhoneInput **≥ 1.3**, **When** Profile renders, **Then** the kit widget loads `asset('js/nowo-phone-prefix-picker.js', 'nowo_phone_input')` (no inline script) and flag + `phone_input.css` links are present.
2. **Given** JS enabled, **When** the member opens the prefix menu, **Then** search/keyboard/portal behaviour works under CSP.
3. **Given** JS disabled, **When** the form renders, **Then** the native country `<select>` remains usable.

### User Story 2 - Cookie modal skin from kit CSS (P1)

**Why this priority**: Host SCSS duplicated kit chrome because CSP nonces dropped injected styles.

**Independent Test**: Guest login shell shows themed bottom-left modal; kit skin is present without JS-injected `<style>`; position remains bottom-left.

**Acceptance Scenarios** (amended by `103` / v1.20.0 — Vite bundle):

1. **Given** CookieConsent **≥ 1.9**, **When** public shells load, **Then** kit skin CSS is available (originally via kit pack `<link data-nowo-cookie-consent-css>`; as of `103`, via Vite-bundled `app` CSS + `data-nowo-cookie-consent-external-css="true"`).
2. **Given** the external-CSS marker, **When** the modal JS boots, **Then** it does not inject a `<style>` block for the skin.
3. **Given** the seeded DB profile, **When** the modal opens, **Then** position remains bottom-left with equal-weight actions.

### User Story 3 - CSRF / GET helpers from FormKit (P1)

**Why this priority**: Shared factories belonged in FormKit, not `App\Shared\Form`.

**Independent Test**: Controllers type-hint `Nowo\FormKitBundle\Form\CsrfOnlyFormFactory` / `GetFilterFormFactory`; `lint:container` green; unit tests for factory API pass.

**Acceptance Scenarios**:

1. **Given** FormKit **≥ 2.4**, **When** a nested CSRF-only action is built, **Then** code uses `createNamed()` (not the old `create(..., named: true)` host API).
2. **Given** a flat kit-style token, **When** Settings builds revoke forms, **Then** code uses `create()` (empty name).
3. **Given** dashboard GET filters, **When** Types extend `App\Shared\Form\AbstractGetFilterType`, **Then** they inherit kit filter defaults and keep `addDashboardPageAndProject` / `per_page` helpers on the host subclass.

### User Story 4 - UiKit Stimulus peers (P2)

**Why this priority**: Confirm / toast / page-loader / tabs / clipboard lived only on the host.

**Independent Test**: `assets/controllers/{clipboard_copy,confirm_*,page_loader,tabs,toast_stack}_controller.ts` re-export vendor peers; Vitest for those controllers passes.

**Acceptance Scenarios**:

1. **Given** UiKit **≥ 1.8**, **When** Vite builds the app entry, **Then** Stimulus registers the same controller identifiers (`clipboard-copy`, `confirm-dialog`, …) from vendor peers.
2. **Given** optional no-build hosts, **When** they load kit IIFEs, **Then** `nowo-ui-clipboard.js` / `nowo-ui-tabs.js` are available (Beacon product shell may keep Stimulus).

## Functional Requirements

- **FR-001**: Pin `nowo-tech/phone-input-bundle` **1.3.0**, `cookie-consent-bundle` **1.9.0**, `form-kit-bundle` **2.4.0**, `ui-kit-bundle` **1.8.0** (exact pins in `composer.json`).
- **FR-002**: MUST NOT keep host forks for phone widget Twig, `phone_prefix_picker` Stimulus, or `_phone_input.scss`. A thin `_cookie_consent.scss` host bridge is allowed again as of `103` (footer/position only — not a full skin fork).
- **FR-003**: Public layouts (`base`, `guest_shell`) MUST load kit cookie skin without JS `<style>` injection. As of `103`: Vite-bundled import + `data-nowo-cookie-consent-external-css="true"` (do not `<link>` `/bundles/nowocookieconsent/*` on public pages).
- **FR-004**: Host MUST NOT redefine `CsrfOnlyType`, `HiddenFieldsCsrfType`, `SearchQueryType`, `CsrfOnlyFormFactory`, or `GetFilterFormFactory` under `App\Shared\Form`.
- **FR-005**: Host `AbstractGetFilterType` MUST extend kit `Nowo\FormKitBundle\Form\AbstractGetFilterType` and MAY keep Beacon-only dashboard helpers.
- **FR-006**: `CsrfOnlyFormFactory::create()` is flat; nested `csrf_only[_token]` MUST use `createNamed()`. Twig `csrf_action_form()` preserves the `$named` bool by calling the matching kit method.
- **FR-007**: Stimulus peers for clipboard / confirm / page-loader / tabs / toast-stack MUST load from UiKit `stimulus-peers` (re-export or importmap); host MAY keep product-only controllers (`password-confirm-mirror`, charts, …).

## Success Criteria

- **SC-001**: Kit releases published on Packagist; Beacon `composer update` resolves the four pins.
- **SC-002**: Profile phone + guest cookie modal work under CSP without host SCSS forks.
- **SC-003**: `lint:container` + targeted PHPUnit/Vitest for CSRF factories and peer controllers pass.
- **SC-004**: Specs `100` / `090` / `081` / `002` amended; ROADMAP Phase **6.52** recorded.

## Dependencies

- Phone profile (`100` / 6.51)
- CSRF Forms (`090`) + FormKit filter profile (`081`)
- Cookie consent public-only + bottom-left seed (`081` / `002` / `055`)
- Confirm / toast shells (`086`)

## Implementation notes

- Kits: sibling repos under `repositories/bundles/{PhoneInput,CookieConsent,FormKit,UiKit}Bundle`
- Beacon assets: vendor re-exports in `assets/controllers/*_controller.ts`; `assets/stimulus_bootstrap.ts` drops `phone-prefix-picker`
- Profile: `templates/account/profile.html.twig` links flag-icons + `phone_input.css`
- Docs: `docs/CHANGELOG.md` Unreleased; `docs/UPGRADING.md`; `docs/product/LEGAL-AND-COOKIES.md`

## Amendments

### 2026-08-15 — Initial cut (this document)

Implements K1–K5 + B1 as Phase **6.52**. No Doctrine migration.

### 2026-08-17 — Cookie CSS Vite bundle (`103`)

Ad blockers often strip `/bundles/nowocookieconsent/nowo-cookie-consent.css`. Host now imports the kit CSS into Vite `app`, sets `data-nowo-cookie-consent-external-css="true"`, and keeps a thin `_cookie_consent.scss` bridge. Amends FR-002 / FR-003 and User Story 2. Full write-up: `103-cookie-consent-vite-e2e-security`.
