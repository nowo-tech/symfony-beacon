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
| S1 | Project Settings | Create/rotate one-shot DSN (`_beacon_last_api_key_dsn`); ordinary GET shows public key only (show-once restored 2026-08-11); **`102` / 2026-08-16:** flash presented via temporary-reveal (~30s, clear-on-hide) on matching active key row; revoked keys never expose secret/DSN. **Follow-up `096` / v1.12.0:** secret stored as SHA-256 `secret_hash` (not recoverable Halite ciphertext) |
| S2 | Demo seed | `app:seed-demo` blocked outside `dev`/`test` unless `--allow-non-local` (never stable DEMO_* keys outside local) |
| S3 | Bootstrap guard | `SiteBackupSecurityDefaultsGuard` also rejects empty / documented / short `APP_SECRET` |
| S4 | Metrics | `InstanceSettings::DEFAULT_METRICS_REQUIRE_TOKEN = true`; migration sets column default true (existing rows unchanged) |
| S5 | Config portability | Import may only **tighten** `ingest_reject_query_auth`, `metrics_require_token`, `notifications_allow_private_urls`, `hooks_allow_anonymous_resolve` |
| S6 | API keys | `ProjectApiKeyFactory` uses `bin2hex(random_bytes(16+))` for public keys; human-friendly tokens remain labels only |
| S7 | Sessions | `when@prod` session `cookie_secure` / `cookie_samesite: lax` / `cookie_httponly` |
| S8 | Slack hooks | Interactions endpoint rejects unsigned `url_verification` challenge echo |
| QA | Tests | Guard unit tests; portability security-flag unit tests; `ProjectApiKeyVisibilityTest` (show-once + revoked hidden); factory constructor update |
| Docs | Ops | `SECURITY.md`, `PRODUCTION.md`, CHANGELOG / UPGRADING / ROADMAP |

## Non-goals (deferred)

Items below were deferred from the 2026-08-10 pass. Status as of **2026-08-13**:

- Revalidate share-link `isUsable()` on every authenticated request after claim (`061` follow-up) — **still deferred** (revoke/expiry already revalidated; max-uses post-claim intentional)
- MAC / signature on `ProcessEnvelopeMessage` after HTTP ACK (`051` adjacent) — **largely closed** in code (`AsMessageHandler(sign: true)`); keep listed until `087` text fully amended
- Domain-event decoupling Ingest → Notifications (`085` residual) — **planned** in `093` US6 (thin Messenger trigger)
- Hard-delete Envelope query-auth code path (`049` residual) — **planned** in `093` US1
- Stricter CSP `style-src` without `unsafe-inline` (`053` residual) — **still deferred**
- Authenticated `/health/ready` counters (`050` residual) — **still deferred**

See also: `093-security-residual-hardening` (Ops posture, hook rate limits, metrics upgrade banner, docs hygiene).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Owners obtain DSN after create/rotate (Priority: P1)

As a project owner, after I create or rotate an API key I briefly see the full DSN (temporary-reveal under the matching active key or flash); ordinary Settings visits do not re-show it. **Revoked** keys never show a copyable DSN.

**Why this priority**: Persistent secret-in-DOM for unused/revoked credentials and anonymous viewers remains a high-impact finding; managers need a one-shot copyable DSN after minting.

**Independent Test**: `ProjectApiKeyVisibilityTest` — after rotate, flash/reveal shows `data-testid="api-key-dsn"` once; ordinary GET has `api-key-dsn-redacted`; after revoke, `api-key-inactive` and no DSN/secret in HTML; viewer Settings → 403.

**Acceptance Scenarios**:

1. **Given** `project.api_keys.manage` and an **active** key without a fresh flash, **When** I open Settings, **Then** I see public id + redacted hint only — not a full DSN (`002` FR-003 / `102`).
2. **Given** I create or rotate a key, **When** I land on Settings after redirect, **Then** the full DSN appears via temporary-reveal (`_beacon_last_api_key_dsn` cleared from session on that render; MAY attach to the matching active key by public key).
3. **Given** a **revoked** / inactive key, **When** I open Settings, **Then** I see public id + inactive badge only — no secret, no copyable DSN, no clipboard-copy for that key.
4. **Given** a viewer, **When** Settings is requested, **Then** HTTP 403 and no secret appears (unchanged from `052` / visibility tests).

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

- **FR-001**: Settings MUST gate API key secrets/DSN to `project.api_keys.manage` only. Create/rotate MUST flash a one-shot DSN (`_beacon_last_api_key_dsn`) cleared from session on that render and presented with temporary-reveal (`102`). Ordinary Settings GET MUST NOT re-embed the secret/DSN for active keys (public key + rotate hint only). **Revoked / inactive** keys MUST NEVER render secret or copyable DSN. Viewers and non-managers MUST NOT see secrets.
- **FR-002**: `app:seed-demo` MUST refuse non-local environments unless `--allow-non-local`; stable DEMO_* material MUST be local-only.
- **FR-003**: Outside `dev`/`test`, APP_SECRET MUST NOT be empty, MUST NOT equal the documented `.env.dist` default, and MUST be at least 16 characters (`SiteBackupSecurityDefaultsGuard`, extending `062`). When `MERCURE_JWT_SECRET` is set, it MUST NOT equal the documented Mercure placeholder and MUST be at least 32 characters (empty allowed when Mercure unused; see `062` Mercure amendment).
- **FR-004**: New instance settings MUST default `metrics_require_token` to true; column DB default MUST match for new rows.
- **FR-005**: Instance config import MUST ignore inbound values that would weaken the four security-sensitive booleans listed in Scope S5; tightening MUST remain allowed.
- **FR-006**: Newly generated API public keys MUST use cryptographically strong random identifiers (not human-friendly adjective-noun tokens).
- **FR-007**: Production Symfony session cookies MUST set secure + httponly + SameSite=Lax (or stricter).
- **FR-008**: Slack interactions endpoint MUST NOT echo `url_verification` challenges without signature verification (reject or dedicated Events route).
- **FR-009**: Docs (SECURITY, PRODUCTION, UPGRADING, CHANGELOG, ROADMAP) MUST describe operator impact.

## Success Criteria

- **SC-001**: Functional visibility test proves ordinary Settings GET never embeds the secret, one-shot flash after rotate does, revoked keys stay redacted, and viewer 403 without secret (`ProjectApiKeyVisibilityTest`).
- **SC-002**: Guard unit suite covers APP_SECRET documented + short reject on prod/staging.
- **SC-003**: Portability unit suite covers weaken-ignore and tighten-apply.
- **SC-004**: Spec catalog lists `087` and cross-links predecessors (`018`, `038`, `044`, `052`, `055`, `058`, `062`, `068`).

## Amendment (API key DSN listing, 2026-08-11)

Briefly allowed listing a copyable DSN under **active** keys for managers (recoverability). **Superseded** the same day: restore strict show-once (ordinary GET = public key only; create/rotate flash only). Inactive-key redaction remains mandatory. Cross-links: `002` FR-003, `018` FR-003 / FR-004.

## Amendment (temporary reveal UX, 2026-08-16)

Show-once remains mandatory. The flash MAY render on the matching active key row; Stimulus `temporary-reveal` (~30s, clear-on-hide) + `ProjectApiKey::maskDsn()` improve shoulder-surfing / DOM residual risk without restoring recoverable secrets from storage (`096` / `102`). Vitest covers the controller; `ProjectApiKeyVisibilityTest` / builder unit tests cover wiring.

## Amendment (`MERCURE_JWT_SECRET` bootstrap, 2026-08-11)

Extends FR-003 / `062`: `SiteBackupSecurityDefaultsGuard` rejects documented / short `MERCURE_JWT_SECRET` outside `dev`/`test` when the env var is set. Covered by `SiteBackupSecurityDefaultsGuardTest`. Docs: PRODUCTION.md, MERCURE.md.

## Amendment (session longevity + PWA cookie, 2026-08-11)

Extends FR-007:

- Session cookie name is `beacon.session_cookie_name` (`config/parameters.yaml`), default `SYMFONY_BEACON_SESSID` (not `PHPSESSID`). Cookie Consent inventory MUST reference the same parameter.
- Without Remember me: `framework.session.cookie_lifetime` + `gc_maxlifetime` = **86400** (1 day).
- Remember me cookie lifetime = **2592000** (30 days); keep AuthKit login `remember_me.lifetime` in sync with `security.firewalls.main.remember_me.lifetime`.
- PWA bootstrap routes `/manifest.webmanifest` and `/sw.js` MUST NOT emit `Set-Cookie` (guest session must not overwrite an authenticated session cookie).

## Related

- Predecessors: `018-project-governance`, `038-prometheus-metrics`, `044-instance-config-export`, `052-api-public-key-hardening`, `055-install-seed-layers`, `058-self-beacon-client`, `062-sitebackup-nondev-guard`, `068-slack-interactive-actions`
- Audit canvas: `security-architecture-audit-2026-08-10`
- Architecture lineage: `083` / `085` / `086` (boundaries unchanged by this pass)
