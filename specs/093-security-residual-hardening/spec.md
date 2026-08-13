# Feature Specification: Security residual hardening (2026-08-13)

**Feature Branch**: `093-security-residual-hardening`  
**Created**: 2026-08-13  
**Status**: Implemented (v1.9.0)  
**Roadmap**: Phase 6.42  

**Input**: Full-tree security + architecture residual audit (2026-08-13): remediate open High/Medium items **H1–M5** left after `087-security-audit-hardening` — hard-delete Envelope query-auth, Ops fail-closed posture warnings, pre-auth hook rate limits, Teams Assign / Setup token query hygiene docs (+ prefer header), metrics require-token upgrade banner, and thin Ingest→Notifications decoupling. Low items (CSP attr, Live CSRF trade-off, share max-uses post-claim) stay non-goals.

## Summary

Operators and maintainers need the Beacon self-host surface to **stop offering weakened ingest auth paths**, **surface when Ops flags leave fail-closed posture**, **throttle anonymous hook abuse**, and **guide upgrades** that still have optional metrics tokens — without reinventing SSRF, share-link, or AuthKit foundations already shipped in `045`–`062` / `087`.

## Scope (planned)

| ID | Audit | Area | Deliverable |
|----|-------|------|-------------|
| R1 | H1 | Ingest | Hard-delete Envelope query `beacon_key`/`beacon_secret` credential **acceptance**; detection may remain only to reject with a clear error; Ops flag `ingest_reject_query_auth` becomes always-on (UI removed or locked) |
| R2 | H2 | Ops UI | Administration surfaces a **security posture** warning when any of: private webhook URLs allowed, anonymous hook Resolve enabled, metrics token not required |
| R3 | M1 | Hooks | Pre-auth IP rate limit on Slack / Teams / inbound-email public endpoints (same order of magnitude as ingest IP limit) |
| R4 | M2 | Teams Assign | Document OpenUri query HMAC/Referer risk in SECURITY + PRODUCTION; no breaking URL shape change in this pass |
| R5 | M3 | Setup | Document prefer `X-Setup-Token` over `?token=`; note log/Referer exposure; keep kit-compatible query for wizard unless kit supports header-only without breakage |
| R6 | M5 | Metrics | Ops Overview (or Metrics settings) banner when `metrics_require_token` is false on an existing install; UPGRADING note — **no silent force-migrate** |
| R7 | M4 | Architecture | Thin decoupling: after Envelope persistence, notifications trigger via Messenger message (or named domain event on `async`) so `ProcessEnvelopeHandler` no longer injects outbound notification services directly |

## Non-goals

- Stricter CSP `style-src` / remove `style-src-attr 'unsafe-inline'` (`053` residual)
- Re-enable classic CSRF on Live Component alert preference forms (`090` trade-off)
- Invalidate share-link sessions when max-uses is exhausted after claim (`061` / `087` partial — revoke/expiry already revalidated)
- Authenticated `/health/ready` queue counters (`050` — depth stays on `/metrics`)
- Full DDD / hexagonal rewrite; splitting `IssueSearchRepository`
- Changing Teams OpenUri to POST body (Microsoft card constraint)
- SSO / WebAuthn / other ROADMAP Later items

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Query-string Envelope auth is gone (Priority: P1)

As an operator, clients can no longer authenticate Envelope ingest by putting `beacon_key` / `beacon_secret` in the URL query string — even if someone toggles an Ops checkbox. Header `X-Beacon-Auth` and envelope DSN credentials remain valid.

**Why this priority**: Query secrets leak into access logs and Referer; `087` deferred hard-delete (`049`); flag-only reject is still operator-bypassable (audit H1).

**Independent Test**: POST envelope with only query credentials → rejected; header auth still ACKs; Ops UI no longer offers disabling query-auth reject (or control is absent).

**Acceptance Scenarios**:

1. **Given** a valid project API key, **When** Envelope is posted with credentials **only** in the query string, **Then** the request is rejected (no ACK / no enqueue of authenticated work).
2. **Given** the same key in `X-Beacon-Auth` (or envelope DSN), **When** Envelope is posted without query credentials, **Then** ingest ACKs as today.
3. **Given** Administration Ops defaults, **When** an admin opens security-related toggles, **Then** there is no control that re-enables query-string Envelope auth.
4. **Given** OpenAPI / DSN docs, **When** an operator reads ingest auth, **Then** query auth is documented as removed (not merely deprecated).

---

### User Story 2 - Weakened Ops posture is visible (Priority: P1)

As an instance admin, when private webhook URLs, anonymous Resolve, or optional metrics scrape are enabled, Administration shows a clear security posture warning so I cannot miss a fail-open configuration.

**Why this priority**: Audit H2 — risk is operational misconfiguration more than missing code guards; import already tighten-only (`087` S5).

**Independent Test**: Toggle each flag to the weakened state; Ops Overview (or dedicated posture callout) shows warning; restore secure defaults; warning clears.

**Acceptance Scenarios**:

1. **Given** `allow_private_urls` true **or** `allow_anonymous_resolve` true **or** `metrics_require_token` false, **When** an admin opens Ops Overview (or Instance Ops defaults), **Then** a visible warning lists which postures are weakened.
2. **Given** all three secure, **When** the same page loads, **Then** no posture warning appears.
3. **Given** instance config import, **When** JSON tries to weaken those flags, **Then** tighten-only behaviour from `087` remains unchanged.

---

### User Story 3 - Public hooks resist burst abuse (Priority: P2)

As an operator, anonymous clients cannot flood Slack / Teams / inbound-email hook endpoints without hitting an IP rate limit before signature verification completes expensive work (or at least before unbounded processing).

**Why this priority**: Audit M1 — public `PUBLIC_ACCESS` surfaces without ingest-style IP throttling.

**Independent Test**: Exceed N requests / window from one IP to a hook route → 429 (or equivalent); legitimate signed traffic under the limit still works.

**Acceptance Scenarios**:

1. **Given** the configured hook IP limit, **When** an IP exceeds it against `/hooks/slack/*`, `/hooks/teams/*`, or `/hooks/email/*`, **Then** further requests from that IP are rejected until the window resets.
2. **Given** traffic under the limit with a valid signature / inbound secret, **When** a mutation is posted, **Then** behaviour is unchanged from today.
3. **Given** Teams Assign-me (`ROLE_USER`), **When** rate limits are considered, **Then** either it shares the hook limiter or is documented as session-gated and out of this story’s IP throttle scope.

---

### User Story 4 - Metrics upgrades are guided (Priority: P2)

As an admin on an upgraded instance where metrics token requirement was left off, I see an explicit prompt to require a Bearer token — without a silent migration that breaks existing scrapers.

**Why this priority**: Audit M5 — `087` S4 left existing rows unchanged.

**Acceptance Scenarios**:

1. **Given** `metrics_require_token` false, **When** Ops Overview loads, **Then** the posture warning (US2) or a dedicated metrics callout tells the admin to enable require-token and set a token.
2. **Given** require-token false and no token configured, **When** `/metrics` is scraped anonymously, **Then** behaviour stays as today (admin session or open — do not silently lock out without the banner).
3. **Given** UPGRADING.md, **When** an operator upgrades, **Then** a short note points to enabling metrics require-token.

---

### User Story 5 - Query token hygiene documented (Priority: P3)

As an operator deploying Setup / Teams Assign cards, I understand that secrets in query strings can appear in logs and Referer, and I prefer headers where the kit allows.

**Why this priority**: Audit M2/M3 — design constraints (OpenUri, SiteBackup wizard) limit code changes; documentation closes the gap this pass.

**Acceptance Scenarios**:

1. **Given** SECURITY.md / PRODUCTION.md, **When** Setup token and Teams Assign are described, **Then** query exposure and “prefer header” guidance are explicit.
2. **Given** SiteBackup still needs `?token=` for the wizard, **When** setup completes, **Then** docs recommend rotating `SITE_SETUP_TOKEN`.

---

### User Story 6 - Ingest does not own notification dispatch (Priority: P3)

As a maintainer, Envelope processing acknowledges and persists issues without depending on notification channel services in the same handler class; outbound alerts are triggered asynchronously after a successful write.

**Why this priority**: Audit M4 / `085` residual — reduces blast radius and clarifies module direction; not a user-facing security bypass.

**Independent Test**: Unit/integration: after Envelope process, a notification trigger message is dispatched; handler no longer type-hints `NotificationDispatcher` (or equivalent outbound facade).

**Acceptance Scenarios**:

1. **Given** a successful Envelope write that should notify, **When** the ingest worker finishes, **Then** a Messenger message on the notify/`async` path is responsible for dispatching outbound alerts.
2. **Given** notification dispatch failure, **When** retries occur, **Then** ingest ACK / issue persistence is not rolled back by outbound failure (same isolation intent as separate consumers).
3. **Given** `make check-module-boundaries`, **When** run after the change, **Then** it still passes (no new Shared write-path violations).

---

### Edge Cases

- Query string contains empty `beacon_key=` — still rejected / not accepted as auth.
- OTLP ingest already ignores query auth — must remain unchanged.
- Hook rate limit must not block Slack `url_verification` permanently after a burst from Slack’s IP ranges more aggressively than ingest (document operator tuning; default aligned with ingest order of magnitude).
- Posture warning must not expose secret values (only flag names / human labels).
- Thin notification decoupling must preserve volume-threshold and status-change notification behaviour already covered by tests.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Envelope ingest MUST NOT accept `beacon_key` / `beacon_secret` from the HTTP query string as credentials. Detection MAY remain solely to reject. Header and envelope-DSN credential extraction MUST remain.
- **FR-002**: Operators MUST NOT be able to re-enable query-string Envelope auth via Instance Ops defaults UI or config import.
- **FR-003**: Administration MUST show a security posture warning when private webhook URLs are allowed, anonymous hook Resolve is enabled, or metrics token is not required.
- **FR-004**: Public Slack, Teams actions, and inbound-email hook endpoints MUST apply a per-IP rate limit before unbounded processing.
- **FR-005**: SECURITY.md and PRODUCTION.md MUST document Teams Assign query HMAC risk and Setup token query vs header preference (rotate after setup).
- **FR-006**: When metrics require-token is off, Ops MUST warn the admin (via FR-003 and/or a dedicated callout); UPGRADING MUST mention enabling it. The system MUST NOT silently flip the flag on upgrade in this feature.
- **FR-007**: Envelope persistence success MUST trigger outbound notifications via an async message (or equivalent) so the Envelope process handler does not directly depend on notification outbound services.
- **FR-008**: Docs (SECURITY, PRODUCTION, UPGRADING, CHANGELOG, ROADMAP) MUST describe operator impact. OpenAPI ingest auth MUST not advertise query credentials.

### Key Entities

- **Instance security posture**: Derived view of Ops booleans that weaken fail-closed defaults (private URLs, anonymous Resolve, metrics require-token).
- **Hook IP rate budget**: Per-client-IP counter/window for public hook routes.
- **Notification trigger message**: Async unit of work linking a persisted ingest outcome to outbound alert evaluation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Automated tests prove Envelope query-only credentials never authenticate; header/DSN paths still succeed.
- **SC-002**: Automated or functional check proves Ops UI cannot weaken query-auth reject / query auth is absent from toggles.
- **SC-003**: Functional test proves posture warning appears iff any of the three weakened flags is set.
- **SC-004**: Functional or unit test proves hook endpoints return rate-limit rejection after budget exhaustion.
- **SC-005**: PHPUnit coverage proves notification trigger is dispatched asynchronously and ingest handler no longer injects the outbound dispatcher.
- **SC-006**: Spec catalog lists `093` and cross-links `049`, `087`, `085`, `038`, `068`–`073`.

## Assumptions

- Envelope clients in production already use `X-Beacon-Auth` or DSN-in-envelope; query auth removal is an acceptable break for any remaining legacy clients (documented in UPGRADING).
- Hook rate-limit defaults can match ingest IP limiter magnitude; operators may tune later if Slack IP sharing becomes noisy (out of scope to add UI tunables in this pass unless trivial).
- Teams OpenUri continues to require query parameters; documentation is sufficient for M2 this pass.
- SiteBackup kit continues to accept setup token via query for the wizard; header preference is documented unless a zero-break kit config exists.
- Thin Messenger decoupling is enough for M4; a full domain-event library is out of scope.

## Related

- Predecessors: `049-deprecate-query-ingest-auth`, `087-security-audit-hardening`, `085-architecture-convergence`, `038-prometheus-metrics`, `068`–`073` (hooks), `045-webhook-ssrf-redirects`
- Audit canvas: `security-architecture-audit-2026-08-13` (extends findings from `security-architecture-audit-2026-08-10` / `087`)
- Architecture: `docs/ARCHITECTURE.md`, constitution module boundaries
