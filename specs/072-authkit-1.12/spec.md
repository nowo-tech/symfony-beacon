# Spec: AuthKit 1.12.0 bump + QR foundation

## Summary

Bump Beacon to `nowo-tech/auth-kit-bundle` **1.12.0** and wire the minimum host surface so QR phone login and enterprise OIDC SSO flags work.

## User stories

1. As an operator, I can run migrations after upgrading and AuthKit QR + enterprise SSO schema exist.
2. As a member, I can save a phone on Account → Profile and use **Sign in with phone (QR)** on the login page.
3. As an admin, I can mark a social credential as **Enterprise SSO** so it appears under the organization heading.

## Acceptance

- Composer pin `1.12.0`
- Migration applied
- `qr_login.mode: enabled` + public `/login/qr*` access_control
- Phone fields on `User` + profile form
- Admin `enterpriseSso` checkbox persisted

## Out of scope

- SMS OTP phone verification
- WebAuthn runtime
- SAML
