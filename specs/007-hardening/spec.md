# Feature Specification: Hardening

**Feature Branch**: `007-hardening`  
**Created**: 2026-07-19  
**Status**: Completed (baseline production/ops hygiene as-built; ongoing)  

## Summary

Hardening in this product means **operator-ready defaults**: production Docker target, secrets only via env, CSRF on mutating forms, documented production notes, and public legal/cookie surfaces when the Twig UI is exposed. There is no separate `src/Hardening` module—behaviour lives in Shared/Identity/Project config and docs.

## Scope (as-built)

- Production image target `frankenphp_prod` and guidance in `docs/production.md`.
- Secrets: version `.env.dist` only; never commit `.env`.
- CSRF on forms (Symfony Security / form components).
- Cookie consent + legal/privacy/terms pages for self-hosted UI (`docs/legal-and-cookies.md`, `nowo-tech/cookie-consent-bundle`).
- Dependency pinning via Composer/lock and CI green tests.

## Out of scope / future

- Full WAF, rate limits beyond existing login throttling kits, multi-region HA, and paid SaaS compliance packs are **not** claimed by this feature.
- Project notifications (`009`) remain a separate future spec.

## Requirements *(mandatory)*

- **FR-001**: Document how to run the production image and configure secrets.
- **FR-002**: Mutating UI actions MUST use CSRF tokens.
- **FR-003**: When non-essential cookies or third-party scripts appear, cookie consent UX MUST be available.
- **FR-004**: Legal notice / privacy / terms pages remain reachable for operators to customize.

## Success Criteria

- **SC-001**: A new operator can follow `docs/production.md` without host-installed PHP.
- **SC-002**: CI remains green; no secrets committed.
