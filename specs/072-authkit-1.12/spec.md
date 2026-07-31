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

- SMS OTP phone verification (ROADMAP **Later**; extends `072` / `075`)
- WebAuthn runtime (ROADMAP **Later**)
- SAML (ROADMAP **Later**; OIDC enterprise flag shipped here)
- QR PNG/SVG image generation (shipped in `075-qr-png`)
