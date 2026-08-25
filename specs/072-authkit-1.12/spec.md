# Feature Specification: AuthKit 1.12.0 bump + QR foundation

**Feature Branch**: `072-authkit-1.12`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.24  

**Input**: Bump Beacon to `nowo-tech/auth-kit-bundle` **1.12.0** and wire the minimum host surface so QR phone login and enterprise OIDC SSO flags work.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Upgrade AuthKit schema (Priority: P1)

As an operator, I can run migrations after upgrading and AuthKit QR + enterprise SSO schema exist.

### User Story 2 - QR phone login foundation (Priority: P1)

As a member, I can save a phone on Account → Profile and use **Sign in with phone (QR)** on the login page.

### User Story 3 - Enterprise SSO flag (Priority: P2)

As an admin, I can mark a social credential as **Enterprise SSO** so it appears under the organization heading.

## Acceptance

- Composer pin `1.12.0` (image generator later in `075` / AuthKit **1.12.1**)
- Migration applied
- `qr_login.mode: enabled` + public `/login/qr*` access_control
- Phone fields on `User` + profile form
- Admin `enterpriseSso` checkbox persisted

## Out of Scope

- SMS OTP phone verification (ROADMAP **Later**; extends `072` / `075` / `100`). AuthKit **1.20** / `105` adds **OTP input UX** on `/reset-password/complete` only — not phone SMS.
- WebAuthn runtime (ROADMAP **Later**)
- SAML (ROADMAP **Later**; OIDC enterprise flag shipped here)
- QR PNG/SVG image generation (shipped in `075-qr-png`)
- QR Approve as slide-to-confirm (`105` keeps a button for UC-AUTH-22)

## Amendments

### 2026-08-15 — Profile phone kit + QR env split (`100`)

- Account → Profile phone field uses `nowo-tech/phone-input-bundle` (`PhoneType`, E.164) instead of free text — see `specs/100-phone-input-profile/`.
- Production/default `qr_login.mode` remains **disabled** per `096`; local/E2E re-enable via `when@dev` / `when@test` (not a global `enabled` default).

### 2026-08-25 — AuthKit 1.20 optional kits (`105`)

- Password-reset **code** page uses `nowo-tech/otp-input-bundle` (multi-box UX; server OTP unchanged).
- Device Intelligence collect + Trusted browsers + new-device mail; Device ID is not a credential and is not auto-trusted after login.
- Slide-to-confirm on registration terms; QR Approve stays a button.
- See `specs/105-authkit-security-kits/`.
