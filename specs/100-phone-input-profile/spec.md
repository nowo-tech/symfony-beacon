# Feature Specification: Phone input kit on Account → Profile

**Feature Branch**: `100-phone-input-profile`  
**Created**: 2026-08-15  
**Status**: Done (v1.16.0 / Phase 6.51)  
**Roadmap**: Phase 6.51  

**Input**: Account → Profile phone must use `nowo-tech/phone-input-bundle` (country prefix + E.164) instead of a free-text field; host CSP must not break the prefix picker; AuthKit QR login stays disabled in prod while remaining available under `when@dev` / `when@test` for UC-AUTH-21/22. Related ops polish: `make restart` recreates PHP workers so `BEACON_DSN` from `.env.local` is reloaded; operator working env file is `.env.local` (REQ-ENV-003).

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| P1 | Kit | Pin `nowo-tech/phone-input-bundle` (**1.2.1** at ship; **1.3.0** after `101`) |
| P2 | Profile form | `AccountProfileType` uses `PhoneType` (CONCATENATED E.164 → `User.phone`); FormKit `mergeFieldOptions` + `user_preferences` catalogue |
| P3 | CSP / UX | At ship: host widget override + Stimulus `phone-prefix-picker` + `_phone_input.scss`. **Superseded by `101`**: kit IIFE + `phone_input.css` (no host fork) |
| P4 | QR env split | Default `qr_login.mode: disabled`; `when@dev` / `when@test` → `enabled` (096 / AUTH-005) |
| P5 | Tests | PHPUnit submits `user_profile[phone][country_iso]` + `[national_number]`; verify/clear `phoneVerifiedAt` still holds (`095`) |
| O1 | Dogfood ops | `make restart` → `up -d --force-recreate` for php/messengers so Compose reloads `.env.local` (`BEACON_DSN`) |
| O2 | Env file | Operator working file is `.env.local` (from `.env.dist`); `ensure-env` / CI `COMPOSE_ENV_FILES` (REQ-ENV-003) |

## Non-goals

- SMS OTP / re-enabling QR in production (`phone_otp` remains ROADMAP **Later**)
- Changing `User.phone` / `phoneVerifiedAt` schema
- Admin user edit phone widget (Account profile only in this cut)
- Hand-rolled dial-code UI without the kit

## User Scenarios & Testing

### User Story 1 - Save phone with country prefix (P1)

**Why this priority**: Free-text phones were ambiguous for QR / future OTP; the kit stores concatenated E.164 and validates by country.

**Independent Test**: Account → Profile shows country select + national number; saving ES `600111222` persists `+34600111222` (or kit-equivalent concatenated form) on `User.phone`.

**Acceptance Scenarios**:

1. **Given** Account → Profile, **When** the basic form renders, **Then** `select[name="user_profile[phone][country_iso]"]` and `input[name="user_profile[phone][national_number]"]` exist.
2. **Given** country ES and a valid national number, **When** the member saves, **Then** `User.phone` is stored in concatenated international form and display name save still needs no password.
3. **Given** an invalid national number for the selected country, **When** the member saves, **Then** validation fails and the previous phone is unchanged.

### User Story 2 - Prefix picker works under CSP (P1)

**Why this priority**: Vendor widget injects inline `<script>`; Beacon CSP nonces block it, leaving a broken double control under Bootstrap CSS.

**Independent Test**: With CSP enabled, opening Profile enhances the native `<select>` via Stimulus; dropdown is usable and themed for dark/light.

**Acceptance Scenarios**:

1. **Given** a CSP nonce policy, **When** Profile loads, **Then** no blocked inline phone-input script is required; the kit CSP-safe picker is active (`data-controller="phone-prefix-picker"` and/or kit IIFE).
2. **Given** the picker enhanced, **When** the member opens the prefix menu, **Then** the menu is portaled/positioned so `dialog` overflow does not clip it.
3. **Given** JS disabled, **When** the form renders, **Then** the native country `<select>` remains usable (progressive enhancement).

### User Story 3 - QR stays off in prod, on in local/CI (P2)

**Why this priority**: `096` disabled QR until OTP; E2E UC-AUTH-21/22 and local dual-device flows still need it.

**Independent Test**: Prod/default config has `qr_login.mode: disabled`; `APP_ENV=dev` or `test` enables it.

**Acceptance Scenarios**:

1. **Given** default / prod config, **When** AuthKit config is dumped, **Then** `qr_login.mode` is `disabled`.
2. **Given** `when@dev` or `when@test`, **When** the kernel boots, **Then** `qr_login.mode` is `enabled` for UC-AUTH-21/22.
3. **Given** QR enabled in test, **When** phone changes, **Then** `phoneVerifiedAt` clear/preserve rules from `095` still apply.

### User Story 4 - Restart reloads dogfood DSN (P3)

**Why this priority**: Plain Compose `restart` keeps stale container env; after `make dogfood` / seed writes `BEACON_DSN`, workers must see the new value.

**Independent Test**: Change `BEACON_DSN` in `.env.local`, run `make restart`, `printenv BEACON_DSN` inside php matches the file.

**Acceptance Scenarios**:

1. **Given** an updated `.env.local` `BEACON_DSN`, **When** `make restart` runs, **Then** php / messenger / messenger-notify are recreated (`--force-recreate`) and env matches `.env.local`.

## Edge Cases

- Clearing the national number clears or nulls phone and clears verification (`095`).
- Unchanged verified phone keeps `phoneVerifiedAt`.
- Preferred countries list is ES-centric defaults; operators can override via `nowo_phone_input.yaml`.
- Form HTML names use panel prefix `user_profile[...]` while FormKit block prefix remains `user_preferences` (catalogue keys unchanged from `098`).

## Functional Requirements

- **FR-001**: Require `nowo-tech/phone-input-bundle` and register bundle + `nowo_phone_input` defaults.
- **FR-002**: `AccountProfileType` MUST use kit `PhoneType` with country prefix selector and CONCATENATED value format into `User.phone`.
- **FR-003**: Prefix picker MUST be CSP-safe (kit external JS ≥ **1.3**, or equivalent). MUST NOT rely on vendor inline script. Host MUST NOT keep a Twig/Stimulus/SCSS fork after `101`.
- **FR-004**: Default AuthKit `qr_login.mode` MUST be `disabled`; MUST be `enabled` under `when@dev` and `when@test`.
- **FR-005**: PHPUnit Account profile tests MUST exercise the compound phone fields and verification hygiene.
- **FR-006**: `make restart` MUST recreate app containers so `.env.local` (including `BEACON_DSN`) is reloaded.
- **FR-007**: Operator working env MUST be `.env.local` (template `.env.dist`); Make/Compose/CI MUST not treat `.env` as the primary working file.

## Success Criteria

- **SC-001**: Profile phone UX uses the kit compound widget; PHPUnit green for save / keep-verified / clear-verified paths.
- **SC-002**: Profile page works under CSP without console script-src violations for the phone field.
- **SC-003**: Prod QR remains disabled; local/E2E QR paths remain covered.
- **SC-004**: After dogfood DSN write + `make restart`, container `BEACON_DSN` matches `.env.local`.
- **SC-005**: Fresh clone uses `cp .env.dist .env.local`; existing `.env` migrates via `make ensure-env`.

## Dependencies

- FormKit host profile `beacon` (`081` / `077`)
- Account profile split (`098`)
- Phone verification hygiene (`095`) + QR disabled until OTP (`096`)
- AuthKit QR foundation (`072` / `075`)
- Self Beacon client / dogfood (`058`)

## Implementation notes

- Config: `config/packages/nowo_phone_input.yaml`, `nowo_auth_kit.yaml` (`when@dev` / `when@test`)
- Form: `src/Identity/Form/AccountProfileType.php`
- Assets (as of `101`): kit `phone_input.css` + flag-icons on Profile; kit `nowo-phone-prefix-picker.js` from widget (no host `_phone_input.scss` / `phone_prefix_picker`)
- Twig: kit `@NowoPhoneInputBundle/Form/phone_input_widget.html.twig` (no host override); `templates/account/profile.html.twig`
- Tests: `tests/Functional/Identity/AccountPreferencesTest.php`
- Make: `restart` target force-recreate

## Amendments

### 2026-08-15 — Initial cut (this document)

Implements P1–P5 + O1–O2 as Phase **6.51** / **v1.16.0**. No Doctrine migration. Operator env file is `.env.local`.

### 2026-08-15 — Kit CSP upstream (`101`)

PhoneInput **1.3.0** owns the CSP-safe prefix picker and progressive-enhancement CSS. Host Twig override, Stimulus controller, and `_phone_input.scss` removed. See `101-kit-csp-shared-helpers`.
