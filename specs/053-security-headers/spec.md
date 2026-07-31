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

## Out of scope

- Application-level nonce CSP generator (optional later).
- WAF.
