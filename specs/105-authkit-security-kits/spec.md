# Feature Specification: AuthKit security kits (slide-to-confirm, device intelligence, OTP input)

**Feature Branch**: `105-authkit-security-kits`  
**Created**: 2026-08-25  
**Status**: Shipped (v1.24.0 / Phase 6.57)  
**Roadmap**: Phase 6.57  

**Input**: Wire AuthKit **1.20.0** optional kits so registration and danger-zone confirms use slide-to-confirm, guest auth pages collect a non-credential device observation, members can trust/revoke browsers, password-reset complete uses multi-box OTP UX, and new-device sign-in can notify via the encrypted instance Mailer.

## Summary

Prefer official Nowo.tech kits — do not hand-roll sliders, device fingerprints, or OTP boxes.

| ID | Area | Deliverable |
|----|------|-------------|
| K1 | AuthKit | Pin [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) **1.20.0** with `slide_to_confirm`, `device_intelligence`, and `otp_input` enabled on the default profile |
| K2 | Slide | [`nowo-tech/slide-to-confirm-bundle`](https://packagist.org/packages/nowo-tech/slide-to-confirm-bundle) **1.1.0** — registration consent `gate`; project **Clear history** uses FormKit `addSlideToConfirmField()` profile `danger` |
| K3 | Device | [`nowo-tech/device-intelligence-bundle`](https://packagist.org/packages/nowo-tech/device-intelligence-bundle) **1.1.0** — collect on AuthKit pages (`/_device/collect`); privacy `strict`; cookie `di_obs` (required, not a credential) |
| K4 | Trust | Account → Security → **Trusted browsers** (`/account/security/devices`) — explicit trust/revoke; login never auto-trusts |
| K5 | Mail | `AuthKitNewDeviceLoginMailNotifier` implements AuthKit `NewDeviceLoginNotifierInterface` via encrypted instance Mailer |
| K6 | OTP UX | [`nowo-tech/otp-input-bundle`](https://packagist.org/packages/nowo-tech/otp-input-bundle) on `/reset-password/complete` (`otp_input.enabled` + `OtpType`); server OTP checks unchanged |
| K7 | FormKit | [`nowo-tech/form-kit-bundle`](https://packagist.org/packages/nowo-tech/form-kit-bundle) **2.5.2** — `addSlideToConfirmField()`; drop host `nowo_form_kit.type_map.search` (built-in since 2.4.0). Dashboard Menu `SearchQueryType` kit tag is `106` (**≥2.1.10**, no host override) |
| K8 | Ops | MaintenanceMode excludes `/_device`; PWA `deny_cache_patterns` includes `/_device`; SW `cache_version` **v6**; SiteBackup `short_circuit_when_done: true` |

## Non-goals

- Phone **SMS OTP** verification (ROADMAP **Later**; extends `072` / `075` / `100`) — Profile still shows verification status only
- WebAuthn / passkeys
- Treating Device ID as a login factor or auto-trusting after a successful password/magic/social/QR login
- Slide-to-confirm on QR **Approve** (stays a button so UC-AUTH-22 E2E can click submit)
- Replacing AuthKit login throttle (`097`) — device-keyed limits on register/reset/magic are extra, not a substitute
- Hand-rolled dial codes, OTP widgets, or fingerprint JS

## User Scenarios & Testing

### User Story 1 - Registration consent slider (P1)

As a first-user operator on `/register`, I must slide to confirm terms before the form can submit. The slider is UX friction only; CSRF and `registration_mode` stay on the AuthKit form.

**Why this priority**: Consent without an extra checkbox-only path that is easy to miss; kit owns the control.

**Independent Test**: Guest register page shows `nowo-slide-to-confirm` on the terms field; submit without sliding is blocked client-side; AuthKit still validates CSRF/terms server-side.

**Acceptance Scenarios**:

1. **Given** `slide_to_confirm.enabled` and `registration_consent: gate`, **When** `/register` renders, **Then** the terms field uses slide-to-confirm (not a bare checkbox-only control).
2. **Given** that page, **When** I submit without confirming the slider, **Then** the registration POST does not succeed.
3. **Given** QR approve, **When** the authenticated phone UI renders, **Then** Approve remains a button (`qr_login_approve: false`) so UC-AUTH-22 stays clickable.

### User Story 2 - Recognise browsers without making Device ID a password (P1)

As an operator, AuthKit guest pages collect a device observation so returning browsers can be recognised for abuse prevention. Device ID is **not** a credential.

**Why this priority**: Extra rate-limit key and new-device mail need a stable observation without weakening password/magic/QR auth.

**Independent Test**: `POST /_device/collect` is reachable (PUBLIC_ACCESS); `di_obs` is HttpOnly; collect boot uses the CSP nonce (vendor inline script would be blocked).

**Acceptance Scenarios**:

1. **Given** AuthKit guest layout, **When** login/register/reset/magic pages render, **Then** device-intelligence assets load and collect runs against `/_device/collect`.
2. **Given** privacy `strict`, **When** an observation is stored, **Then** IP is hashed (no raw IP); high-entropy collectors (canvas/webgl/audio/fonts) stay off.
3. **Given** Maintenance Mode or PWA, **When** `/_device` is requested, **Then** the path is excluded from maintenance gating and from SW precache (`deny_cache_patterns`).
4. **Given** a new browser sign-in and Mailer ready, **When** AuthKit emits a new-device notice, **Then** `AuthKitNewDeviceLoginMailNotifier` sends via encrypted instance Mailer (same delivery path as magic/reset).

### User Story 3 - Explicit trusted browsers (P1)

As a signed-in member, on Account → Security → Trusted browsers I can trust the current browser or revoke a previous grant. Login never creates a trust row by itself.

**Why this priority**: Trust is an explicit account-security action, not a hidden side effect of authentication.

**Independent Test**: `GET /account/security/devices` lists grants; POST trust/revoke use CSRF-only forms; PHPUnit covers tab chrome + `AccountTrustedDevices`.

**Acceptance Scenarios**:

1. **Given** I am on Security, **When** the page renders, **Then** a **Trusted browsers** tab links to `/account/security/devices` (alongside password / history / activity).
2. **Given** a current device context, **When** I POST trust with valid CSRF, **Then** a trust grant is stored for my user identifier and the current device.
3. **Given** a listed grant, **When** I POST revoke, **Then** that grant is revoked and no longer listed as active.
4. **Given** a successful login without visiting Trusted browsers, **When** I inspect trust rows, **Then** none were created automatically (`qr_login.approve_require_trusted` remains false).

### User Story 4 - Multi-box OTP on password-reset complete (P2)

As a guest completing password reset with a code, `/reset-password/complete` shows six numeric boxes (kit `OtpType`) that still submit one string. Server OTP verification is unchanged.

**Why this priority**: UX only; do not invent a second OTP protocol.

**Independent Test**: UC-AUTH-26 still loads the complete page; form theme includes `@NowoOtpInputBundle/Form/otp_input_theme.html.twig`; host `_otp_input.scss` maps digit boxes to Beacon `.input` tokens.

**Acceptance Scenarios**:

1. **Given** `otp_input.enabled` and `password_reset_code: true`, **When** `/reset-password/complete` renders, **Then** the code field is kit OTP input (`length: 6`, numeric).
2. **Given** that form, **When** I submit a valid code, **Then** AuthKit server checks behave as before this feature (no parallel host verifier).

### User Story 5 - Clear history requires a danger slide (P2)

As a project admin clearing telemetry history, Settings danger zone requires sliding to confirm. Authorization remains `project.settings.manage` + CSRF (`011` / `090`).

**Why this priority**: Extra friction on an irreversible wipe without replacing typed-name delete.

**Independent Test**: UC-PROJ-17 sees `nowo-slide-to-confirm` inside `form[action*="clear-history"]`; `ProjectDangerZoneTest` still posts CSRF.

**Acceptance Scenarios**:

1. **Given** Settings danger zone, **When** Clear history renders, **Then** `ProjectClearHistoryType` includes `addSlideToConfirmField('confirm', profile: danger)` (mapped false is the type default).
2. **Given** that form, **When** I POST without a confirmed slide, **Then** history is not cleared.
3. **Given** Delete project / Transfer ownership, **When** those modals render, **Then** they keep typed-name confirmation (`011`); they do not require this slider.

## Edge Cases

- Collect while setup is gated: `/_device` stays in SiteBackup exclusions so the probe can run; AuthKit `/login` stays gated until setup is done (`056` FR-014).
- No current device context on Trusted browsers: Trust POST flashes `flash.preferences.device_missing` and does not create a grant.
- Unknown / malformed device id on revoke: 404 or `device_unknown` flash; no cross-user revoke (trust service scoped to user identifier).
- Mailer not ready: new-device notify follows existing AuthKit mail-ready gating (same as magic/reset).
- Cookie CMP: `di_obs` is inventoried as **required** account-security (not analytics); operators keep English legal/cookie pages current.

## Functional Requirements

- **FR-001**: Composer MUST pin AuthKit **1.20.0**, Device Intelligence **1.1.0**, OTP Input (current patch), Slide-to-confirm **1.1.0**, FormKit **≥ 2.5.2**.
- **FR-002**: AuthKit profile MUST enable `slide_to_confirm` (registration `gate`; QR approve **false**), `device_intelligence` (collect on auth pages, new-device notify, extra device-keyed rate limit on register/reset/magic), and `otp_input` (password-reset code page).
- **FR-003**: Device ID MUST NOT be a credential. AuthKit MUST NOT auto-trust a device after login.
- **FR-004**: `di_obs` MUST be HttpOnly, first-party, category **required**, documented in cookie inventory + `/legal/cookies` + seed fixture `cookie_consent.default.json`.
- **FR-005**: Device collect endpoint MUST be `PUBLIC_ACCESS`, excluded from maintenance and PWA precache, and collect boot MUST use the CSP nonce.
- **FR-006**: Account Trusted browsers MUST use CSRF-only POSTs (`CsrfOnlyFormFactory`) for trust/revoke; list rows MUST be scoped to the signed-in user.
- **FR-007**: New-device email MUST go through `NewDeviceLoginNotifierInterface` → instance Mailer (encrypted DSN / `034`).
- **FR-008**: Clear history MUST use FormKit `addSlideToConfirmField()`; host MUST NOT set `mapped: false` unless overriding the type default. Delete/transfer stay typed-name (`011`).
- **FR-009**: Host MUST NOT re-register `nowo_form_kit.type_map.search` (FormKit ≥ 2.4 built-in).
- **FR-010**: Migration MUST create `device_intelligence_*` tables (`Version20260824120000`).
- **FR-011**: Phone SMS verification MUST remain out of scope (ROADMAP Later).
- **FR-012**: PHPUnit MUST cover AuthKit bootstrap flags, Trusted browsers chrome, `AccountTrustedDevices`, new-device mail notifier, danger-zone slide field, and setup `/_device` exclusion.

## Key Entities

- **Device**: Stable observation id (ULID-shaped); label, OS/browser family, confidence — not a secret.
- **Observation**: Point-in-time signals (hashed IP, UA family); used for matching/risk, not login.
- **Device–user link**: Which account identifiers have been seen on a device.
- **Device trust**: Explicit grant (`trusted_at` / `revoked_at`) per user identifier + device; never implied by login.

## Success Criteria

- **SC-001**: Operators can register only after sliding terms; QR Approve remains a one-click button.
- **SC-002**: Members can open Trusted browsers, trust the current browser, and revoke it; a fresh login does not create a trust grant.
- **SC-003**: Guests completing reset-by-code see multi-box OTP without a second verification protocol.
- **SC-004**: Clearing project history requires the danger slider; typed-name delete still works.
- **SC-005**: Cookie inventory lists `di_obs` as required; legal/cookie pages stay English (`lang="en"`) with room for operator text.

## Assumptions

- AuthKit already owns login/register/reset/magic (`037`); this feature only enables optional kits and a thin Account trust UI.
- Encrypted Mailer DSN (`034`) remains the outbound path for new-device mail.
- Login throttle decorator (`097`) remains the primary brute-force control; device-keyed limits are additional.
- SiteBackup **1.13.7+** `short_circuit_when_done: true` is required so Beacon catalog detectors do not reopen `/setup` after durable done (`056`).

## Cross-links

- Prior: `011`, `034`, `037`, `056`, `072`, `075`, `081`, `090`, `096`, `097`, `100`, `101`, `103`
- Follow-up: `106-ops-ingest-hardening` (FormKit **2.5.2**, Dashboard Menu **≥2.1.10** `SearchQueryType` kit tag, Ops/ingest/QA — not AuthKit kits)
- Docs: [`docs/product/LEGAL-AND-COOKIES.md`](../../docs/product/LEGAL-AND-COOKIES.md), [`docs/product/E2E-USE-CASES.md`](../../docs/product/E2E-USE-CASES.md) (UC-AUTH-22/26, UC-PROJ-17)
- Kits: AuthKit, Device Intelligence, OTP Input, Slide-to-confirm, FormKit, Cookie Consent, Maintenance Mode, PWA, SiteBackup
