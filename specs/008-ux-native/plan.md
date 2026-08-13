# Implementation Plan: UX Native (Hotwire Native mobile shell)

**Feature**: `008-ux-native`  
**Date**: 2026-07-20  
**Updated**: 2026-08-13  
**Status**: **Deferred / Future** — do not implement until prioritized on the roadmap  

## Summary

Enable Beacon to power Hotwire Native iOS/Android shells by integrating Symfony UX Native + Turbo, publishing path configuration JSON, adapting Twig layouts for native WebViews, and documenting client setup.

**Current product path**: PWA only (`nowo-tech/pwa-bundle`). This plan is retained as the intended approach when the roadmap item is scheduled.

## Technical approach (when prioritized)

| Area | Decision |
|------|----------|
| Detection | Symfony UX Native User-Agent listener + `ux_is_native()` |
| Navigation | Symfony UX Turbo (Turbo Drive) in Vite/Stimulus pipeline |
| Config | `App\Native\AppNativeConfiguration` → `/config/ios_v1.json`, `/config/android_v1.json` |
| Security | `PUBLIC_ACCESS` for native config paths |
| Layout | Hide PWA install + redundant chrome when native |
| Bridge | Optional Stimulus bridge for theme sync |
| Docs | `docs/dev/NATIVE-MOBILE.md` + README link |

## Constitution alignment

- Spec-first: keep this folder until implementation; amend constitution before reintroducing packages.
- Docker-first; English docs/PHPDoc/UI; tests required when implemented.

## Related packages (future)

- `symfony/ux-native`, `symfony/ux-turbo`
- Existing PWA: `nowo-tech/pwa-bundle` (browser path)

## Risks

- UX Native is experimental upstream — pin versions and cover with tests.
- Turbo Drive historically interacted poorly with some operator UI widgets; evaluate carefully before enabling.
- Local HTTPS self-signed certs complicate physical-device testing.
