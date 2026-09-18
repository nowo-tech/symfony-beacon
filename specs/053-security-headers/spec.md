# Feature Specification: Security Headers in Caddy

**Feature Branch**: `053-security-headers`
**Created**: 2026-07-31
**Status**: Implemented (v0.13.0)

**Input**: Add security headers in Caddy (CSP, HSTS, X-Frame-Options, Referrer-Policy) for self-hosted prod compose. Document trade-offs with Vite/Mercure/PWA.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Headers (P1)

As an operator, prod responses include baseline security headers.

**Acceptance Scenarios**:

1. **Given compose.prod / Caddyfile prod, When I fetch HTML, Then CSP/HSTS/frame/referrer headers match docs.**
2. **Given strict CSP breaks an allowed feature, When documented, Then operators can adjust via documented snippets.**

## Requirements *(mandatory)*

- **FR-001**: Caddy prod snippets for CSP, HSTS, X-Frame-Options, Referrer-Policy.
- **FR-002**: PRODUCTION.md documents headers and Mercure/PWA exceptions.
- **FR-003**: Dev stack may omit HSTS.

## Success Criteria

- **SC-001**: PRODUCTION.md checklist includes headers.
- **SC-002**: Smoke curl assertions in docs or CI optional.

## Amendment (Application CSP subscriber, as-built)

Prod Caddy snippets remain the baseline for HSTS / frame / referrer. HTML **Content-Security-Policy** is owned by `ContentSecurityPolicySubscriber` (nonce for `script-src` / `style-src-elem`; `style-src-attr 'unsafe-inline'` for CSSOM; optional Mercure origin on `connect-src`). Kit admin host `<style>` blocks MUST use `csp_nonce()`. See `081` amendment “Kit Administration chrome sync” and `docs/PRODUCTION.md`.

## Amendment (Hot Reload CSP, 2026-08-17)

Debug CSP MUST NOT list `cdn.jsdelivr.net` by default. `nowo-tech/hot-reload-bundle` ≥**1.3.2** (`require-dev`, `dev`/`test` only) appends that host via `csp_augment_script_src` **when it injects**, and stamps the preserve boot script with request attribute `_beacon_csp_nonce`. Production MUST NOT register the bundle. See `docs/ops/FRANKENPHP-HOT-RELOAD.md`.

## Amendment (Hot Reload 1.4.0, 2026-08-18)

Host pin is `nowo-tech/hot-reload-bundle` **1.4.0**. CSP contract is unchanged. Dev MAY run `nowo:hot-reload:check` and use profiler environment checks. See `docs/ops/FRANKENPHP-HOT-RELOAD.md`.

## Amendment (legal editor styles, 2026-09-18 / `115`)

On `/admin/legal` and child paths, `style-src-elem` MUST be `'self' 'unsafe-inline'` and MUST NOT include a nonce. A nonce in that directive makes browsers ignore `'unsafe-inline'`, and the legal editor injects `<style>` from its script with no nonce. Other HTML routes keep nonce-based `style-src-elem` (debug MAY still add `'unsafe-inline'` for the profiler). `script-src` stays self + nonce; legal admin MUST NOT add `unsafe-eval`. Unit test: `ContentSecurityPolicySubscriberTest`.

## Out of scope

- WAF.
