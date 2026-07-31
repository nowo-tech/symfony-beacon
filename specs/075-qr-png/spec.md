# Feature Specification: QR login image (AuthKit 1.12.1)

**Feature Branch**: `075-qr-png`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.27  
**Issue**: [#40](https://github.com/nowo-tech/symfony-beacon/issues/40)

**Input**: Ship QR login visual codes by bumping AuthKit to 1.12.1 and requiring `endroid/qr-code` so `EndroidQrCodeGenerator` replaces Null.

## Acceptance

1. `composer.json` pins `nowo-tech/auth-kit-bundle` **1.12.1** and `endroid/qr-code` **^6**.
2. Anonymous `GET /login/qr` → show page HTML includes `data:image/png` or `data:image/svg+xml` img src.
3. ROADMAP: QR image Done; **SMS OTP** remains Later.

## Out of Scope

- SMS OTP / phone_otp notifiers
- Enabling `ext-gd` in Docker (SVG fallback is acceptable without GD)
