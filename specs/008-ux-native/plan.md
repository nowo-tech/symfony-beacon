# Implementation Plan: UX Native (Hotwire Native mobile shell)

**Feature**: `008-ux-native`  
**Date**: 2026-07-20  
**Status**: Completed  

## Summary

Enable Beacon to power Hotwire Native iOS/Android shells by integrating Symfony UX Native + Turbo, publishing path configuration JSON, adapting Twig layouts for native WebViews, and documenting client setup.

## Technical approach

| Area | Decision |
|------|----------|
| Detection | Symfony UX Native User-Agent listener + `ux_is_native()` |
| Navigation | Symfony UX Turbo (Turbo Drive) in Vite/Stimulus pipeline |
| Config | `App\Native\AppNativeConfiguration` → `/config/ios_v1.json`, `/config/android_v1.json` |
| Security | `PUBLIC_ACCESS` for native config paths |
| Layout | Hide PWA install + redundant chrome when native; safe-area CSS |
| Bridge | Optional Stimulus `beacon-theme-bridge` for theme sync |
| Docs | `docs/native-mobile.md` + README link |

## Constitution alignment

- Spec-first: this feature has `spec` / `plan` / `tasks`.
- Docker-first: no host PHP required; commands via Compose.
- English docs/PHPDoc/UI.
- Tests required: `tests/Native/NativeConfigEndpointsTest.php`.

## Related packages

- `symfony/ux-native`, `symfony/ux-turbo`
- Existing PWA: `nowo-tech/pwa-bundle` (browser path; suppressed in native UA)
- Client SDK for ingest (separate repo): `nowo-tech/beacon-bundle` / `BEACON_DSN` — not part of this feature’s mobile shell, but complementary for reporting apps

## Risks

- UX Native is experimental upstream — pin versions and keep behaviour covered by tests.
- Local HTTPS self-signed certs complicate physical-device testing.
