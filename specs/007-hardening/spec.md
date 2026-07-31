# Feature Specification: Hardening

**Feature Branch**: `007-hardening`  
**Created**: 2026-07-19  
**Status**: Completed (baseline + 2026-07-21 security remediations; bootstrap / locale / ingest scrub — 2026-07-22)  

## Summary

Hardening in this product means **operator-ready defaults**: production Docker target, secrets only via env, CSRF on mutating forms (including API key creation), documented production notes, public legal/cookie surfaces when the Twig UI is exposed, and defenses against common self-hosting risks (SSRF on notification webhooks, shared login throttle storage, session invalidation on account disable, required ingest secrets, anonymous setup abuse controls).

There is no separate `src/Hardening` module—behaviour lives in Shared/Identity/Project/Notifications config and docs.

## Scope (as-built)

- Production image target `frankenphp_prod` and guidance in `docs/PRODUCTION.md`.
- Secrets: version `.env.dist` only; never commit `.env`. Halite keys / encrypted Doctrine fields for API secrets and webhook URLs (`nowo-tech/doctrine-encrypt-bundle`). Durable key in prod Compose (`048`) and durable test key via PHPUnit bootstrap (`var/secrets/`).
- CSRF on forms and on non-form mutating POSTs (e.g. **create API key**, **guest locale switch**).
- Cookie consent + legal/privacy/terms pages at `/{_locale}/legal/…` (bare `/legal/…` redirects to `DEFAULT_LOCALE`; `docs/product/LEGAL-AND-COOKIES.md`, `nowo-tech/cookie-consent-bundle`).
- Login throttling with **database** storage shared across FrankenPHP workers / multi-pod (`login_attempts`, see `012-safe-self-hosting`).
- UserKit `invalidate_sessions_on_disable: true`.
- Outbound notification URL SSRF guard (private / link-local / metadata blocked in production).
- Ingest requires API key **secret** when present; strips envelope `dsn` from queued payloads; envelope size cap (`003-ingest`).
- Anonymous setup bootstrap cannot create fixed-password demo admins; setup POSTs rate-limited (`056`).
- Safe internal redirects reject open-redirect edges (`047`).
- Dependency pinning via Composer/lock and CI green tests.

## Out of scope / future

- Full WAF, multi-region HA, and paid SaaS compliance packs are **not** claimed by this feature.
- Allowlisting Slack/Discord hostnames only (current guard is IP/host class based).

## Requirements *(mandatory)*

- **FR-001**: Document how to run the production image and configure secrets.
- **FR-002**: Mutating UI actions MUST use CSRF tokens (including project API key create).
- **FR-003**: When non-essential cookies or third-party scripts appear, cookie consent UX MUST be available.
- **FR-004**: Legal notice / privacy / terms / cookies pages remain reachable (locale-prefixed and bare→`DEFAULT_LOCALE`) for operators to customize.
- **FR-005**: Notification HTTP destinations MUST NOT target private, reserved, or cloud-metadata addresses unless explicitly allowed (`BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS` / `when@dev|test`).
- **FR-006**: Disabling a user account MUST invalidate sessions.
- **FR-007**: Login attempt counters MUST be shared across processes in default production config (database storage).
- **FR-008**: Guest locale helper `POST /locale/{locale}` MUST require a valid CSRF token; GET MUST NOT switch locale.
- **FR-009**: Anonymous setup bootstrap MUST NOT expose sample/demo seed actions that create known default credentials; setup mutating POSTs MUST be rate-limited (`056`).
- **FR-010**: Envelope ingest MUST NOT queue raw envelope-header `dsn` after auth (`003-ingest`).

## Success Criteria

- **SC-001**: A new operator can follow `docs/PRODUCTION.md` without host-installed PHP.
- **SC-002**: CI remains green; no secrets committed.
- **SC-003**: Security remediations (CSRF keys, ingest secret, SSRF, group link policy, session disable, DB throttle, guest locale CSRF, setup bootstrap hardening, envelope credential scrub) are covered by automated tests where applicable.
