# Feature Specification: Security audit hardening (2026-08-10)

**Feature Branch**: `087-security-audit-hardening`  
**Created**: 2026-08-10  
**Status**: Implemented (shipped in **v1.4.0**, 2026-08-10)  
**Roadmap**: Phase 6.36  

**Input**: Senior architecture/security audit (2026-08-10): remediate critical/high findings — show-once API DSN, seed-demo env gate, APP_SECRET fail-closed, metrics require-token default, fail-closed instance-config import, high-entropy public keys, prod session cookies, Slack interactions challenge reflector. Deferred items documented as non-goals for this pass.

## Summary

Operators and maintainers need the Beacon self-host surface to **fail closed** on known-local secrets and to **stop re-rendering ingest secrets** in Settings HTML. This feature records the as-built remediations from the full-tree audit (not a branch-diff review) without rewriting ingest SSRF/share-link foundations already shipped in `045`–`054` / `061`–`062`.

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| S1 | Project Settings | API key DSN/secret shown **once** after create/rotate (`_beacon_last_api_key_dsn`); list shows public key + rotate hint |
| S2 | Demo seed | `app:seed-demo` blocked outside `dev`/`test` unless `--allow-non-local` (never stable DEMO_* keys outside local) |
| S3 | Bootstrap guard | `SiteBackupSecurityDefaultsGuard` also rejects empty / documented / short `APP_SECRET` |
| S4 | Metrics | `InstanceSettings::DEFAULT_METRICS_REQUIRE_TOKEN = true`; migration sets column default true (existing rows unchanged) |
| S5 | Config portability | Import may only **tighten** `ingest_reject_query_auth`, `metrics_require_token`, `notifications_allow_private_urls`, `hooks_allow_anonymous_resolve` |
| S6 | API keys | `ProjectApiKeyFactory` uses `bin2hex(random_bytes(16+))` for public keys; human-friendly tokens remain labels only |
| S7 | Sessions | `when@prod` session `cookie_secure` / `cookie_samesite: lax` / `cookie_httponly` |
| S8 | Slack hooks | Interactions endpoint rejects unsigned `url_verification` challenge echo |
| QA | Tests | Guard unit tests; portability security-flag unit tests; `ProjectApiKeyVisibilityTest` show-once; factory constructor update |
| Docs | Ops | `SECURITY.md`, `PRODUCTION.md`, CHANGELOG / UPGRADING / ROADMAP |

## Non-goals (deferred)

- Revalidate share-link `isUsable()` on every authenticated request after claim (`061` follow-up)
- MAC / signature on `ProcessEnvelopeMessage` after HTTP ACK (`051` adjacent)
- Domain-event decoupling Ingest → Notifications (`085` residual)
- Hard-delete Envelope query-auth code path (`049` residual)
- Stricter CSP `style-src` without `unsafe-inline` (`053` residual)
- Authenticated `/health/ready` counters (`050` residual)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Owners copy DSN once (Priority: P1)

As a project owner, after I create or rotate an API key I see the full DSN once; reloading Settings does not embed the secret again.

**Why this priority**: Persistent secret-in-DOM was the highest-impact XSS/session-share finding.

**Independent Test**: `ProjectApiKeyVisibilityTest` — Settings GET after seed has no secret; POST create shows `data-testid="api-key-dsn"` once; subsequent GET redacts.

**Acceptance Scenarios**:

1. **Given** `project.api_keys.manage`, **When** I open Settings without a fresh create/rotate, **Then** I see the public key and a rotate hint, not the secret/DSN.
2. **Given** I create or rotate a key, **When** I land on Settings after redirect, **Then** the full DSN appears once in a banner and is cleared from session on that render.
3. **Given** a viewer, **When** Settings is requested, **Then** HTTP 403 and no secret appears (unchanged from `052` / visibility tests).

### User Story 2 - Demo seed cannot poison prod (Priority: P1)

As an operator, I cannot accidentally install documented demo credentials on a non-local environment.

**Independent Test**: Guard/command behavior documented; outside `dev`/`test`, command exits failure without `--allow-non-local`; with the flag, new keys are random (not DEMO_* constants).

**Acceptance Scenarios**:

1. **Given** `APP_ENV=prod` (or staging), **When** I run `app:seed-demo` without `--allow-non-local`, **Then** the command fails closed.
2. **Given** local `dev`/`test`, **When** I run `app:seed-demo`, **Then** stable DEMO_* keys remain available for dogfooding (`058`).
3. **Given** `--allow-non-local` outside local, **When** a new key is created, **Then** public/secret are random (not DEMO_*).

### User Story 3 - Known APP_SECRET rejected outside local (Priority: P1)

As an operator exposing Beacon beyond laptop defaults, the app refuses to boot with `.env.dist` `APP_SECRET`.

**Independent Test**: `SiteBackupSecurityDefaultsGuardTest` covers documented secret reject and short secret reject for `prod`/`staging`.

**Acceptance Scenarios**:

1. **Given** non-local env + `ChangeMePleaseUseARealSecret`, **When** HTTP or non-skipped console runs, **Then** RuntimeException naming APP_SECRET.
2. **Given** non-local env + secret length &lt; 16, **When** boot is attempted, **Then** RuntimeException.
3. **Given** `cache:clear` / `cache:warmup` / `assets:install` in image build, **When** those commands run, **Then** the guard still skips (unchanged `062` bake path).

### User Story 4 - Config import cannot weaken security flags (Priority: P1)

As an instance admin importing appearance/ops JSON, I cannot silently re-enable private webhook URLs, anonymous hook resolve, or disable query-auth reject / metrics token requirement.

**Independent Test**: `InstanceConfigPortabilitySecurityFlagsTest`.

**Acceptance Scenarios**:

1. **Given** secure defaults (reject query auth on, metrics require token on, private URLs off, anonymous resolve off), **When** import JSON tries to weaken them, **Then** those flags stay secure; other allowlisted ints (e.g. retention) still apply.
2. **Given** weakened flags already stored, **When** import supplies tighter values, **Then** flags tighten.

### User Story 5 - Metrics default fail-closed for new installs (Priority: P2)

As a new install operator, Prometheus scrape expects a configured Bearer token by default.

**Acceptance Scenarios**:

1. **Given** a new `instance_settings` row, **When** created, **Then** `metrics_require_token` defaults to true.
2. **Given** an upgraded existing row with require-token false, **When** migrate runs, **Then** the stored boolean is unchanged (operators opt in via Ops UI / docs).

## Requirements *(mandatory)*

- **FR-001**: Settings MUST NOT render API key secrets/DSN on ordinary GET; secrets MUST appear at most once after create/rotate (session one-shot), for `project.api_keys.manage` only.
- **FR-002**: `app:seed-demo` MUST refuse non-local environments unless `--allow-non-local`; stable DEMO_* material MUST be local-only.
- **FR-003**: Outside `dev`/`test`, APP_SECRET MUST NOT be empty, MUST NOT equal the documented `.env.dist` default, and MUST be at least 16 characters (`SiteBackupSecurityDefaultsGuard`, extending `062`).
- **FR-004**: New instance settings MUST default `metrics_require_token` to true; column DB default MUST match for new rows.
- **FR-005**: Instance config import MUST ignore inbound values that would weaken the four security-sensitive booleans listed in Scope S5; tightening MUST remain allowed.
- **FR-006**: Newly generated API public keys MUST use cryptographically strong random identifiers (not human-friendly adjective-noun tokens).
- **FR-007**: Production Symfony session cookies MUST set secure + httponly + SameSite=Lax (or stricter).
- **FR-008**: Slack interactions endpoint MUST NOT echo `url_verification` challenges without signature verification (reject or dedicated Events route).
- **FR-009**: Docs (SECURITY, PRODUCTION, UPGRADING, CHANGELOG, ROADMAP) MUST describe operator impact.

## Success Criteria

- **SC-001**: Functional visibility test proves secret absent on cold Settings GET and present only after create.
- **SC-002**: Guard unit suite covers APP_SECRET documented + short reject on prod/staging.
- **SC-003**: Portability unit suite covers weaken-ignore and tighten-apply.
- **SC-004**: Spec catalog lists `087` and cross-links predecessors (`018`, `038`, `044`, `052`, `055`, `058`, `062`, `068`).

## Related

- Predecessors: `018-project-governance`, `038-prometheus-metrics`, `044-instance-config-export`, `052-api-public-key-hardening`, `055-install-seed-layers`, `058-self-beacon-client`, `062-sitebackup-nondev-guard`, `068-slack-interactive-actions`
- Audit canvas: `security-architecture-audit-2026-08-10`
- Architecture lineage: `083` / `085` / `086` (boundaries unchanged by this pass)
