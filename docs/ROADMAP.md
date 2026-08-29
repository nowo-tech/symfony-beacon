# Product roadmap

Living plan for **symfony-beacon** and the companion client **[nowo-tech/beacon-bundle](https://github.com/nowo-tech/BeaconBundle)**. Priorities follow product completeness analysis (post-v0.6.0): close the operator loop (alerts), keep self-hosting safe, then deepen automatic instrumentation and product depth.

Related: [ARCHITECTURE.md](ARCHITECTURE.md), [CHANGELOG.md](CHANGELOG.md), feature specs under `specs/`.

## Guiding principles

1. Spec-first — each slice maps to a `specs/NNN-*` feature (or a BeaconBundle spec).
2. Efficient ingest — outbound work stays on Messenger; Envelope ACK never waits on Slack/webhooks.
3. Prefer Nowo.tech kits for auth/ops UX; keep Beacon focused on telemetry.
4. English docs / PHPDoc / default UI copy.

## Status legend

| Status | Meaning |
|--------|---------|
| **Done** | Shipped in a tagged release |
| **In progress** | Active implementation |
| **Next** | Immediate queue |
| **Planned** | Ordered backlog |
| **Later** | Explicitly deferred |

---

## Phase 0 — Foundation (Done → v0.6.0)

| Item | Repo | Notes |
|------|------|--------|
| Envelope ingest + Messenger | Beacon | `003-ingest` |
| Issues triage UI (fingerprint, assignee, status, history, DataTables) | Beacon | `004-issues` |
| Performance + N+1 UI | Beacon | `006-performance` |
| Daily analytics | Beacon | `005-analytics` |
| AuthKit, projects, Settings, danger zone | Beacon | `002`, `011` |
| Rich event context + stack source context | Beacon + Bundle | `010`, Bundle ≥ 1.3.0 |
| PWA (browser installability) | Beacon | `nowo-tech/pwa-bundle` (see `docs/dev/NATIVE-MOBILE.md`) |
| Architecture rationale + Mermaid flows | Beacon | `docs/ARCHITECTURE.md` |

---

## Phase 1 — Close the loop (Done)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 1.1 | **Project notifications** (Slack, Discord, Teams, Telegram, email, generic HTTP) | Beacon | `009-project-notifications` | **Done** |
| 1.2 | **Regression rules**: reopen `resolved` **and** `ignored` → unresolved on matching event; notify on new + regression only | Beacon | `009` / ingest | **Done** |
| 1.3 | Settings UI: destinations CRUD, category filters, masked URLs, send-test, **setup guides** | Beacon | `009` | **Done** |
| 1.4 | Async delivery + bounded retries via Messenger + **SSRF guard** | Beacon | `009` | **Done** |
| 1.5 | **API docs in Panel** (Nelmio OpenAPI / Swagger in app shell) | Beacon | `013-api-docs-panel` | **Done** |

---

## Phase 2 — Safe self-hosting (Done)

| # | Item | Repo | Status |
|---|------|------|--------|
| 2.1 | Configurable **retention** + purge job (max age / max events per project) | Beacon | **Done** |
| 2.2 | **Ingest rate limit** per project / API key | Beacon | **Done** |
| 2.3 | **Health / ready** endpoints + Messenger queue depth signal | Beacon | **Done** |
| 2.4 | Production scaling / backup notes in `docs/PRODUCTION.md` | Beacon | **Done** |
| 2.5 | `nowo-tech/login-throttle-bundle` on AuthKit login (**database** storage shared across workers) | Beacon | **Done** |

---

## Phase 3 — Client instrumentation depth (Done; 3.1–3.6)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 3.1 | Capture **Messenger** worker failures (`WorkerMessageFailedEvent`) | Bundle | — | **Done** |
| 3.2 | **Auto HTTP transaction** (route/controller + duration) | Bundle | — | **Done** (opt-in) |
| 3.3 | Opt-in **Doctrine** + **HttpClient** breadcrumbs/spans | Bundle (+ Beacon UI) | `024-client-spans` | **Done** |
| 3.4 | Public **`tags`** API + **`before_send`** scrubbing hook | Bundle (+ Beacon UI) | `023-client-tags-scrubbing` | **Done** |
| 3.5 | Non-blocking client transport (async/queue) + versioned User-Agent | Bundle | — | **Done** (Bundle **v1.6.0**) |
| 3.6 | Contract tests: golden Envelope ↔ Beacon `ProcessEnvelopeHandler` | Bundle (+ Beacon) | — | **Done** |

---

## Phase 4 — Product depth (Done — v0.10.0)

Ordered Speckit program (Beacon `014`→`022`; Bundle `023`/`024`):

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 4.1 | **Releases**: filter, “new in release”, compare environments | Beacon | `014-releases` | **Done** |
| 4.2 | **Issue workflow**: comments, priority, mark-duplicate (+ merge), saved views | Beacon | `015-issue-workflow` | **Done** |
| 4.3 | **Issue search & scale**: full-text, tag/URL/user/release filters, SQL-only sorts | Beacon | `016-issue-search` | **Done** |
| 4.4 | **Export + issue lifecycle webhooks** (CSV/JSON; not only alert notify) | Beacon | `017-export-webhooks` | **Done** |
| 4.5 | **Project governance**: per-project retention/rate/quota in Settings; key revoke/rotate | Beacon | `018-project-governance` | **Done** |
| 4.6 | **Admin project ops**: stats, suspend ingest, admin audit, view-as-member | Beacon | `019-admin-projects-ops` | **Done** |
| 4.7 | **Notification digest / quiet hours** (no native PagerDuty) | Beacon | `020-notification-digest` | **Done** |
| 4.8 | **Project health UI**: Messenger queue, webhook failures, last deliveries | Beacon | `021-project-health-ui` | **Done** |
| 4.9 | Analytics + Performance **functional** tests + CI coverage | Beacon | `022-analytics-perf-ci` | **Done** |

---

## Phase 5 — Access & insights (Done — high/medium through v0.12.x)

Ordered Speckit program. Prefer AuthKit / Symfony login-link for magic login; do not hand-roll auth. **Social OAuth** (Google/GitHub/Microsoft via AuthKit) is `060`. Enterprise **SSO/SAML/OIDC** stays Later (separate from `026` / `060`).

### High impact

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.1 | **Analytics charts**: period presets / range + filters (env, release) | Beacon | `025-analytics-charts` | **Done** |
| 5.2 | **Magic login links** + project **viewer** role; optional signed share links | Beacon | `026-magic-links-viewer` | **Done** |
| 5.3 | **Threshold alerts**: e.g. &gt; N errors in M minutes (plus existing new/regression) | Beacon | `027-threshold-alerts` | **Done** |

### Medium impact

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.4 | **Release health** panel: new-in-release counts + compare (builds on `014`) | Beacon | `028-release-health` | **Done** |
| 5.5 | **Issue FULLTEXT** search (upgrade `016` `LIKE` path) | Beacon | `029-issue-fulltext` | **Done** |
| 5.6 | **Delivery history**: last N attempts per notification destination (extends `021`) | Beacon | `030-delivery-history` | **Done** |
| 5.7 | **Admin project audit timeline** on Admin → Project show (extends `019`) | Beacon | `031-admin-project-audit` | **Done** |
| 5.7a | **Encrypted instance Mailer DSN** (Admin → Mailer; env fallback only) | Beacon | `034-encrypted-mailer-dsn` | **Done** |

### Unspecced polish (shipped alongside Phase 5)

| Item | Notes | Status |
|------|--------|--------|
| Account **appearance** extras (font scale, contrast, sidebar default) | `/account/display` + theme boot | **Done** |
| Admin **users / groups** AuditKit meta (`createdAt` / `updatedAt` / blame) | Extends `audit-kit-bundle` usage | **Done** |
| Password generator + password-change history on `/account/security` | PasswordStrength + `password_history` | **Done** |
| Richer `/account/profile` (roles, UUID, memberships, groups) | Account overview | **Done** |

### Later (still in backlog — pull into Phase 6 when prioritized)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.8 | **Monthly event quota** (alongside daily; extends `018`) | Beacon | `032-monthly-quota` | **Done** (v0.13.0; see Phase 6.4) |
| 5.9 | **CI coverage report** (informational / soft threshold; not 100% gate) | Beacon | `033-coverage-ci` | **Done** (v0.16.0; see Phase 6.7) |
| — | **SSO/SAML/OIDC** via AuthKit / dedicated enterprise spec | Beacon | — | **Later** |

Do **not** reinvent: native PagerDuty, session replay, multi-org SaaS control plane — use HTTP webhooks + digests instead.

---

## Phase 6 — Operator platform & triage depth (Next)

Focus: **v1.1.0** ships Phase **6.29**–**6.34** (dashboard Assignments/aside panels, FormKit/UiKit sync, appearance presets, module boundaries, architecture convergence) plus Playwright E2E and PHPUnit suite layout. **v1.2.0** adds Vitest + Compose `env_file` / local port hygiene. **v1.3.0** adds DRY maintainability **6.35** (`086`); **v1.3.1** closes FormKit host-form parity + demo `ProjectFactory` + unit tests for pipeline/hooks/factories. **v1.4.0** ships project `project.*` permissions + Administration Roles/Permissions UI + security audit hardening **6.36** (`087`). **v1.5.0** adds project membership role **`full`** + InstanceRole delete-in-use guards **6.37** (`088`); **v1.5.1** polishes owner-row membership UI and kit admin modal chrome. **v1.6.0** adds project config export/import **6.38** (`089`), DSN UUID path, and AuthKit 1.16. **v1.6.1** hardens ingest gate / config import + Mercure JWT guard; **v1.6.2** tabs the export/import Settings card; **v1.6.3** restores show-once DSN, caps JSON imports at 2 MiB, and public-only Cookie Consent; **v1.6.4** fixes PWA session overwrite / Mercure 0.8 and extends session + Remember me lifetimes. **v1.7.0** ships CSRF via Symfony Forms **6.39** (`090`), kit Administration chrome sync (`081`), AuthKit **1.17**, and CSP kit-admin polish. **v1.8.0** ships member alert preferences **6.40** (`091`), UserKit **1.1.6**, and per-user Mercure topics. **v1.9.0** ships maintenance mode **6.41**, security residual **6.42**, and PHPStan FrankenPHP 1.1.0 **6.43**. **v1.10.0** ships product FormKit `filter` / `beacon` + Twig `form_row` consolidation **6.44**. **v1.11.0** ships audit residual hardening **6.45** (`095`). **v1.12.0** ships audit follow-up **6.46** (`096`) — Read API rate limit, hash-at-rest ingest secrets, Project-subject voter abstain, membership write DRY, filter DTOs, QR disabled, Slack user-id hygiene. **v1.13.0** ships E2E CI + AuthKit login throttle **6.47** (`097`), FormKit 2.3.0, and expanded Playwright catalog. **v1.14.0** ships shared MySQL mode + account profile split + `app_user`→`user` **6.48** (`098`). **v1.15.0** ships setup wizard product-complete + Redis scale (**6.49** / `056`, **6.50** / `099`). **v1.15.1** hardens cookie consent guest skin + PHPUnit/E2E. **v1.16.0** ships phone-input kit on Account → Profile + `.env.local` (**6.51** / `100`). **v1.17.0** ships kit CSP upstream + shared helpers (**6.52** / `101` — PhoneInput 1.3 / CookieConsent 1.9 / FormKit 2.4 / UiKit 1.8) and prefers shared Mailpit. **v1.18.4** adds issue detail path tabs + temporary API DSN reveal (**6.53** / `102`). **v1.19.0** hard-gates includable coverage at 100% (**033** / REQ-QA-002). **v1.20.0** ships Cookie Consent Vite bundle + dogfood earliest-admin resolve + E2E security denials (**6.54** / `103`). **v1.21.0** ships isolated Playwright E2E (`app_e2e` / `:9460`) + Morphicons chrome (**6.55** / `104`). **v1.22.0** adds dogfood `make beacon-suite` multi-event UI probe + explicit client tags (`058`). **v1.23.0** ships kit polish: hot-reload-bundle **1.3.2**, PWA `install_links` vendor-only, AuthKit gated until setup (`056` FR-014), PhoneInput SCSS theme bridge, and BP-004 / ENGINEERING-AUDIT close (**6.56**). **v1.23.1** refreshes exact Composer pins (hot-reload **1.4.0**, FormKit **2.4.1**, password kits, Mercure **0.5.0**, Symfony **8.1.4**) and makes `make update-deps` run the helper `--run`. **v1.23.3** hardens prod kit YAML and isolates E2E Messenger Redis (`104`). **v1.24.0** ships AuthKit security kits **6.57** / `105`, ops ingest hardening **6.58** / `106`, and SQL error context **6.59** / `107`. **v1.24.1** defaults the setup token gate and versions `.env.e2e.dist`. **v1.24.2** ships HotReloadBundle **1.5.1** (`shared_worker`), CSP inline-script nonce stamping + optional CSP extras, and shared Redis AUTH outside local env. **v1.0.0** was the first stable major (Phases 0–6 through **6.28**). **Next**: Later Phase 6+ (SAML / WebAuthn / QR SMS OTP / Hotwire Native `008`) when prioritized.

### Security hardening (priority track — platform review 2026-07-21)

Baseline is solid for self-hosted use: AuthKit + login throttle, CSRF on privileged POSTs, Halite encryption for API secrets / webhooks / Mailer DSN, ingest `hash_equals` + project binding, share-token hashing, Twig auto-escape on issue/comment bodies. No anonymous auth bypass found. Prioritize the High items below before net-new product surface (especially before read API / Prometheus).

| Severity | Finding (summary) | Spec | Status |
|----------|-------------------|------|--------|
| **High** | **Webhook SSRF via redirects**: `OutboundUrlGuard` checks the initial URL; HttpClient still follows redirects → 302 to RFC1918/metadata | `045-webhook-ssrf-redirects` | **Done** |
| **High** | **Share link issue scope not enforced**: session stores `issue` but `hasActiveShareGrant()` grants project-wide viewer | `046-share-link-issue-scope` | **Done** |
| **High** | **Open redirect** on admin view-as-member (`redirect` accepts `//evil.com`; locale switch already rejects `//`) | `047-admin-safe-redirect` | **Done** |
| **High** | **Encrypt key not durable in prod Compose**: `var/secrets` / Halite key not volume-mounted; container recreate loses decrypt ability | `048-prod-encrypt-key` | **Done** |
| Medium | Deprecate / warn on ingest auth via **query string** (`beacon_secret` → proxy/access logs, Referer) | `049-deprecate-query-ingest-auth` | **Done** |
| Medium | **`/health/ready`** must not echo exception messages publicly | `050-health-error-hardening` | **Done** |
| Medium | SSRF **DNS rebinding / TOCTOU** (resolve-then-connect without pin) | extends `045` | **Done** (v0.13.0) |
| Medium | **Re-check ingest suspend** (and quota if needed) in `ProcessEnvelopeHandler` after ACK | `051-ingest-worker-recheck` | **Done** |
| Medium | Harden / document **public API key** handling (hash or treat as opaque id; secret always required) | `052-api-public-key-hardening` | **Done** |
| Medium | Expand **PRODUCTION.md**: trusted proxies, encrypt key, `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS=0`, health binding, HSTS/CSP | `048` / docs | **Done** (encrypt key; headers baseline v0.13.0) |
| Low | Security **headers** in Caddy (CSP, HSTS, `X-Frame-Options`, `Referrer-Policy`) | `053-security-headers` | **Done** (v0.13.0 baseline; CSP no script unsafe-inline + default HSTS in **v0.15.0**) |
| Low | Restrict **Nelmio `/api/doc`** to `ROLE_ADMIN` | `054-api-doc-admin-only` | **Done** (v0.13.0) |
| Low | Generic client errors on Envelope parse (detail → logs only) | `051` / ingest | **Done** (HTTP already returns `invalid envelope`) |
| Low | Prefer POST-only magic-login consume + `Referrer-Policy` (reduce GET token leakage) | extends AuthKit / `026` | **Done** (v0.13.0; confirm click hardened in v0.14.0) |
| Low | Cookie-consent POST CSRF (double-submit header; kit ≥ 1.4.8) | kit config | **Done** |
| Med | SiteBackup local defaults on public `/setup` + `/_site_backup` | `SITE_SETUP_TOKEN` + guard | **Done** |
| Med | Guest locale open redirect (`/\\…`) vs `SafeInternalRedirect` | guest locale | **Done** (v0.14.0) |
| Med | `/metrics` query `?token=` leakage | extends `038` | **Done** (v0.14.0; Bearer only) |
| Low | Web Push unsubscribe IDOR (endpoint hash without user scope) | member push | **Done** (v0.14.0) |
| Info | Audit **Mailer DSN** changes in `UserAction`; optional Mailer scheme allowlist | extends `034` / `065` | **Done** (v0.17.0; see 6.15) |

**Suggested patch order:** Security + `039` Done through **v0.15.0**; `033` + `043` + `040`–`044` / `061`–`064` + RoutingKit / branded errors Done in **v0.16.0**; Bundle console/Scheduler + Monolog Done in Bundle **v1.6.10**; Mailer DSN audit + Mailpit + Ops defaults + Social login admin Done in **v0.17.0**; OTLP logs (`067` / 6.19); Slack/Teams Resolve (`068`/`069`); OTLP traces (`070` / 6.22); Slack Assign-to-me (`071` / 6.23). **Next**: pull from Later when prioritized.

### Done (v0.13.0 product — was Next)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.1 | **Ops overview dashboard**: cross-project error spikes, open issues, failed deliveries (instance admin + optional project filter) | Beacon | `035-ops-overview` | **Done** (v0.13.0) |
| 6.2 | **Admin identity audit timeline** for users & groups (extends blame fields + `UserAction` into Admin → User/Group show) | Beacon | `036-admin-identity-audit` | **Done** (v0.13.0) |
| 6.3 | **Identity kit polish**: remaining account chrome (area nav, social links, security activity) — AuthKit already owns login/register; **not** a greenfield AuthKit migration | Beacon | `037-authkit-identity-migration` | **Done** (v0.13.0) |
| 6.4 | **Monthly event quota** (promote `032`) | Beacon | `032-monthly-quota` | **Done** (v0.13.0) |
| 6.4a | **Install / seed layers**: platform (menus) vs demo (identity+DSN) vs sample sizes; upgrade = migrate + platform seed (`055`) | Beacon | `055-install-seed-layers` | **Done** (v0.12.4) |
| 6.4b | **Setup / cold-start**: SiteBackupBundle `/setup` + `/_site_backup`; catalog-empty redirect; `AdminUserProvisioner`; marker → `setup_completed_at` (`056`) | Beacon | `056-setup-wizard` | **Done** (v0.12.4–v0.12.8; SiteBackup v0.13.0) |
| 6.4c | **Product tour**: contextual driver.js (dashboard / project Issues / admin); role-aware steps; prefs mark-seen / replay (`057`) | Beacon | `057-product-tour` | **Done** (v0.12.5) |
| 6.4d | **Member push**: Mercure hub + PWA Web Push for new issues on associated projects | Beacon | — | **Done** (v0.12.6) |
| 6.4e | **Cookie consent seed + DB config**; schema ER docs (`DATABASE.md`) | Beacon | — | **Done** (v0.12.7) |
| 6.4f | **Dual public URLs** (AuthKit `unlocalized: serve`); AuthKit password reset/magic; catalogue parity de/nl/fr/it/pt | Beacon | `026` / ADDING-LOCALES | **Done** (v0.12.8) |
| 6.4g | **Self Beacon client** (dogfood): Packagist `beacon-bundle`; `make ready` / `make dogfood` → `BEACON_DSN`; `make beacon-test` + `make beacon-suite` (multi-event UI probe, explicit client tags) | Beacon | `058-self-beacon-client` | **Done** (v1.22.0 suite) |
| 6.4h | **AuthKit social OAuth** (DB credentials; Google/GitHub/Microsoft); `create_user_if_missing: false` | Beacon | `060-authkit-social-login` | **Done** |
| 6.4i | **Mailer-gated AuthKit magic/reset UI** (encrypted deliverable DSN required) | Beacon | extends `034` / `026` | **Done** |
| 6.4j | **AI issue export** (`beacon-ai-export/v1` Markdown/JSON; scrubbed headers) | Beacon | `059-ai-issue-export` | **Done** |

### Done (v0.14.0)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.5 | **Prometheus metrics** scrape (`GET /metrics`): ingest ACK/reject, Messenger depth, failed destinations — `ROLE_ADMIN` or Bearer `BEACON_METRICS_TOKEN` (prod requires token) | Beacon | `038-prometheus-metrics` | **Done** (v0.14.0) |
| 6.5a | Security residual: reject query ingest auth by default; guest locale `SafeInternalRedirect`; metrics Bearer-only; Web Push unsubscribe scoped to owner; magic-login Continue click; Telegram DNS pin; CSP/`theme-boot` | Beacon | extends `038` / hardening | **Done** (v0.14.0) |

### Done (v0.15.0)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.6 | **Notification circuit breaker**: pause / back off a destination after N consecutive failures; admin resume | Beacon | `039-notification-circuit-breaker` | **Done** (v0.15.0) |
| 6.6a | **CSP / HSTS**: drop `script-src 'unsafe-inline'`; HSTS by default (except localhost); kit-admin / swagger-ui-boot Vite entries | Beacon | extends `053` | **Done** (v0.15.0) |
| 6.6b | Appearance palette (warn / paper / ink / surface light+dark) + Tours form / preferences sidebar current fixes | Beacon | — | **Done** (v0.15.0) |

### Done (v0.16.0 — coverage + GDPR + collaboration)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.7 | **CI coverage soft gate** (promote `033`; informational first, modest threshold later) | Beacon | `033-coverage-ci` | **Done** (v0.16.0; soft 35% later; **hard 100%** in **v1.19.0** / QA-002) |
| 6.8 | **GDPR helpers**: account data export + soft-delete / anonymize path. Prod path is app-owned; `nowo-tech/anonymize-bundle` is **dev/test-only** (staging dumps) — do not use it as the runtime anonymize executor | Beacon | `043-gdpr-user-export` | **Done** (v0.16.0) |
| 6.9 | **Issue mentions + assignee notify**: `@user` in comments; email (instance Mailer) on assign / mention | Beacon | `040-issue-mentions-notify` | **Done** (v0.16.0) |
| 6.10 | **Similar issues** suggestions on issue show (fingerprint / title proximity; link or mark-duplicate shortcut) | Beacon | `041-similar-issues` | **Done** (v0.16.0) |
| 6.11 | **Read API + project tokens** | Beacon | `042-read-api-tokens` | **Done** (v0.16.0) |
| 6.12 | **Instance settings export/import** | Beacon | `044-instance-config-export` | **Done** (v0.16.0) |
| 6.12a | **SiteBackup setup locale-in-path** (`both` + `serve`; kit ≥ 1.7.0) + friendly token gate | Beacon | `056-setup-wizard` | **Done** (v0.16.0) |
| 6.12b | **RoutingKit** install (`/_routing/`; `#[Routable]` discovery) | Beacon | `064-routing-kit` | **Done** (v0.16.0) |
| 6.12c | **Branded HTTP errors** 404/403/500 + mascot; `/_error/{code}` preview **dev-only** | Beacon | `063-branded-http-errors` | **Done** (v0.16.0) |

### Done (Bundle companion — v1.6.10)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.13 | **BeaconBundle**: enrich **console / cron** failure extras (`extra.console` nested) + optional **Scheduler** `ScheduledStamp` context on final Messenger failures (`include_scheduler_context`). Base console capture existed since Bundle ≥1.1.0 | Bundle | — | **Done** (Bundle **v1.6.10**) |
| 6.14 | **BeaconBundle**: opt-in **Monolog** bridge (`monolog_handler` → Envelope events/messages) | Bundle | — | **Done** (Bundle ≥1.1.0; documented closed with **v1.6.10**) |

### Done (v0.17.0 — Mailer audit, Mailpit, Ops defaults)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.15 | **Mailer DSN change audit**: Admin Mailer DSN/From updates recorded in `UserAction` (redacted scheme/host only); scheme allowlist on `MailerDsnValidator` | Beacon | extends `034` (`065-mailer-dsn-audit`) | **Done** (v0.17.0) |
| 6.16 | **Local Mailpit**: Compose profile `mail` + `make mailpit` for catching SMTP in development; prefers shared `developer.local.server/server` `mailpit` (`smtp://mailpit:1025`) when present, else app-local `mailer` (`smtp://mailer:1025`); not in production Compose | Beacon | `066-local-mailpit` | **Done** (v0.17.0; shared preference v1.17.0) |
| 6.17 | **Ops defaults in database**: retention, ingest rate, daily/monthly quotas, delivery-history size, circuit-breaker settings under Administration → Ops defaults; drop env tuning for those knobs; instance config export v2 | Beacon | — | **Done** (v0.17.0) |
| 6.18 | **Social login admin UI**: CRUD for AuthKit `auth_kit_social_credential`; remove `app:seed-social-login` / `AUTH_KIT_SOCIAL_*` env bootstrap | Beacon | extends `060` | **Done** (v0.17.0) |

### Done (OTLP logs adapter)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.19 | **OTLP logs ingest** (HTTP JSON ExportLogsServiceRequest → Beacon events; same DSN auth; WARN+ only; cap 200) | Beacon | `067-otlp-ingest` | **Done** |

### Done (Slack interactive Resolve)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.20 | **Slack interactive Resolve**: Block Kit button + `POST /hooks/slack/interactions` (signed); optional encrypted signing secret per destination | Beacon | `068-slack-interactive-actions` | **Done** |

### Done (Teams interactive Resolve)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.21 | **Teams interactive Resolve**: MessageCard HttpPOST + `POST /hooks/teams/actions` (HMAC token); reuses destination signing secret | Beacon | `069-teams-interactive-actions` | **Done** |

### Done (OTLP traces adapter)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.22 | **OTLP traces ingest** (HTTP JSON ExportTraceServiceRequest → ERROR spans → Beacon events; same DSN auth; cap 200) | Beacon | `070-otlp-traces` | **Done** |

### Done (Slack Assign-to-me)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.23 | **Slack Assign-to-me** + Account Slack user ID mapping; Resolve actor when mapped; shared `IssueAssigneeChanger` | Beacon | `071-slack-assign-mapping` | **Done** |

### Done (Teams Assign OpenUri)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.25 | **Teams Assign to me** via OpenUri (HMAC + Beacon session + triage; no `teamsUserId`) | Beacon | `073-teams-assign-openuri` | **Done** |

### Done (OTLP metrics adapter)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.26 | **OTLP metrics ingest** (HTTP JSON ExportMetricsServiceRequest → failure-like data points → Beacon events; same DSN auth; cap 200) | Beacon | `074-otlp-metrics` | **Done** |

### Done (QR login image)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.27 | **QR login image** (AuthKit 1.12.1 + `endroid/qr-code`; PNG with GD else SVG) | Beacon | `075-qr-png` | **Done** |

### Done (Inbound email comments)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.28 | **Inbound email → issue comment** (webhook + Reply-To token + Message-ID idempotency) | Beacon | `076-inbound-email-comment` | **Done** |

### Done (Dashboard triage panels)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.29 | **Dashboard Assignments** panel (mine / teammates / unassigned across accessible projects) | Beacon | `079-dashboard-assignments` | **Done** (v1.1.0) |
| 6.30 | **Dashboard aside panels**: Summary, Activity, Mentions inbox (`issue_mention`), Alerts (member failed deliveries), New in release | Beacon | `080-dashboard-aside-panels` | **Done** (v1.1.0) |
| 6.31 | **FormKit / UiKit kit sync** (UiKit host 1.7; FormKit 2.2; AuthKit 1.15; RoutingKit 1.3; Menu 2.0 / Breadcrumb 2.1 / Cookie 1.6 / HttpLog 1.1; profiles, shell, pagination, panel form) | Beacon + kits | `081-formkit-uikit-kit-sync` | **Done** (v1.1.0) |
| 6.32 | **Appearance theme presets**: named light/dark palettes that overwrite colors; independent `theme_id` / `theme_id_dark`; tabbed Themes / Brand / Layout / Colors; corner / border / fixed footer | Beacon | `082-appearance-theme-presets` | **Done** (v1.1.0) |
| 6.33 | **Module boundary hardening**: map `Api`/`Setup`; Shared growth rules; Identity↔Project direction; Issues/Ingest maintainability; async drain isolation; unique spec numbers; Analytics/Performance tests | Beacon | `083-module-boundaries` | **Done** (v1.1.0) |
| 6.34 | **Architecture convergence**: Envelope domain writers; `Ops` module; Compose `messenger-notify`; AI export controller; channel formatters; Project admin tests; CI boundaries; `UserUiPreferences` embeddable; demo JSON fixtures | Beacon | `085-architecture-convergence` | **Done** (v1.1.0) |
| 6.35 | **DRY refactor**: `OtlpIngestPipeline` + mappers; Project/Issue factories & normalizers; Twig shells; `make ensure-up`; platform `.checkbox` + password-toggle gap/eye | Beacon | `086-dry-refactor` | **Done** (v1.3.0) |
| 6.35b | **FormKit host parity + 086 follow-up**: all host Form Types on `FormKitAbstractType`; demo via `ProjectFactory`; `OtlpIngestGatewayInterface`; unit tests for pipeline / hooks / factory | Beacon | extends `086` | **Done** (v1.3.1) |
| 6.36 | **Security audit hardening**: API DSN gating (create/rotate banner; active-key listing / revoked hidden — amended 2026-08-11); seed-demo env gate; APP_SECRET fail-closed; metrics require-token default; fail-closed config import; high-entropy public keys; prod session cookies; Slack challenge reflector removed | Beacon | `087-security-audit-hardening` | **Done** (v1.4.0) |
| 6.37 | **Project role `full`**: same `project.*` as owner without primary ownership; transfer demotes to full; InstanceRole delete blocked when users assigned | Beacon | `088-project-full-role` | **Done** (v1.5.0) |
| 6.38 | **Project config export/import**: `beacon-project-bundle` v1; unique `project.code`; membership `active`; Admin creates users; Settings skips unknown emails | Beacon | `089-project-config-export` | **Done** (v1.6.0) |
| 6.39 | **CSRF via Symfony Forms**: `CsrfOnlyType` + named Types (triage / danger / Settings / admin) + GET `AbstractGetFilterType`; migrate off hand-rolled Twig `csrf_token()` POSTs | Beacon | `090-csrf-symfony-forms` | **Done** (v1.7.0) |
| 6.40 | **Member alert preferences**: Account matrix (master / events / scope / per-project); Mercure `/users/{uuid}/member-alerts`; Web Push filtered; viewers edit own prefs from Account | Beacon | `091-member-push-preferences` | **Done** (v1.8.0) |
| 6.41 | **Site-wide maintenance mode**: `nowo-tech/maintenance-mode-bundle`; Administration panel + preview; branded `error-503.png` public page; kit chrome | Beacon | `092-maintenance-mode` | **Done** (v1.9.0) |
| 6.42 | **Security residual hardening**: hard-delete Envelope query-auth; Ops security posture warning; hook IP rate limits; metrics require-token upgrade banner; Setup/Teams query hygiene docs; thin Ingest→Notifications Messenger decoupling | Beacon | `093-security-residual-hardening` | **Done** (v1.9.0) |
| 6.43 | **PHPStan FrankenPHP 1.1.0**: production gate `rules.neon`; new worker process-state + `pcntl_signal` hardening identifiers; path-scoped PHPUnit `umask` ignore | Beacon | `094-phpstan-frankenphp-110` | **Done** (v1.9.0) |
| 6.44 | **Product FormKit form profiles + Twig `form_row`**: `filter` / `beacon` contracts; `translations/form.*.yaml`; host Twig `_fields` consolidation; prefixed field names | Beacon | `081` / `077` / `090` follow-up | **Done** (v1.10.0) |
| 6.45 | **Audit residual hardening**: phone QR no auto-verify; maintenance ingest-only exclusions; Mercure hub URL guard; `ProjectPermissionVoter` / Port / Metrics→Ops; batch member-alert prefs; admin pagination; Settings traits; EXTRA_LAZY + SqlLike | Beacon | `095-audit-residual-hardening` | **Done** (v1.11.0) |
| 6.46 | **Audit follow-up hardening**: Read API IP rate limit; ingest `secret_hash`; `InstancePermissionVoter` abstain on `Project`; membership/group write services + FormType role POSTs; filter DTOs / `MapQueryString`; QR disabled; Slack user-id password+unique; interaction token 24h; OTLP iterator / status transitions | Beacon | `096-audit-follow-up-hardening` | **Done** (v1.12.0) |
| 6.47 | **E2E CI + AuthKit login throttle**: per-username AuthKit limiter decorator; CI E2E 90m + Mailpit + `node_modules` chown; Playwright reliability + atomic UC catalog (~247 Covered) | Beacon | `097-e2e-ci-login-throttle` | **Done** (v1.13.0) |
| 6.48 | **Shared MySQL + profile split**: `make up-shared` / `SHARED-SERVER.md`; Account basic vs password-gated sensitive forms; rename `app_user` → `user` | Beacon | `098-shared-server-profile-split` | **Done** (v1.14.0) |
| 6.49 | **Setup wizard product-complete**: SiteBackup **1.13.0** (`cache_doctrine`, empty-schema cold-start, optional `database_url` Skip) + CookieConsent **1.8.0** (mid-migration schema-ready); durable done store; no host gate/progress/consent workarounds. AuthKit gated until setup 100% (FR-014) in **v1.23.0**. | Beacon | `056-setup-wizard` | **Done** (v1.15.0; FR-014 v1.23.0) |
| 6.50 | **Redis horizontal scale**: dual-mode Redis (standalone + server share); sessions + rate-limit + Messenger streams; promoted `event_tag` / `request_url`; split `ProjectAccessService`; `AdminUserMutator` | Beacon | `099-redis-horizontal-scale` | **Done** (v1.15.0) |
| 6.51 | **Phone input kit on Account → Profile**: `nowo-tech/phone-input-bundle` 1.2.1; CSP Stimulus prefix picker; `qr_login` disabled by default / enabled `when@dev`+`when@test`; `.env.local` (REQ-ENV-003); `make restart` reloads env | Beacon | `100-phone-input-profile` | **Done** (v1.16.0) |
| 6.52 | **Kit CSP upstream + shared helpers**: PhoneInput **1.3.0**, CookieConsent **1.9.0**, FormKit **2.4.0**, UiKit **1.8.0**; drop host phone/cookie **Twig/JS** forks; CSRF/GET factories in FormKit; Stimulus peers from UiKit. Thin `_cookie_consent.scss` (v1.20.0) / `_phone_input.scss` (v1.23.0) theme bridges. | Beacon | `101-kit-csp-shared-helpers` | **Done** (v1.17.0) |
| 6.53 | **Issue detail tabs + temporary API DSN reveal**: path tabs `main`/`similar`/`history`; flat app-paper chrome; create/rotate DSN via Stimulus temporary-reveal (show-once preserved) | Beacon | `102-issue-detail-tabs-api-reveal` | **Done** (v1.18.4) |
| 6.54 | **Cookie Consent Vite bundle + dogfood admin resolve + E2E security denials**: kit CSS in `app` entry (ad-blocker resilient); earliest `ROLE_ADMIN` dogfood owner; Playwright `UC-SEC-01`…`12` | Beacon | `103-cookie-consent-vite-e2e-security` | **Done** (v1.20.0) |
| 6.55 | **Isolated Playwright E2E stack**: parallel Compose `symfony-beacon-e2e` / schema `app_e2e` / `:9460`; `make test-e2e-isolated`; optional BeaconBundle dogfood (`E2E_BEACON_TARGET`); Morphicons UI toggles | Beacon | `104-isolated-e2e-stack` | **Done** (v1.21.0) |
| 6.56 | **Kit polish + first-run gate**: hot-reload-bundle **1.3.2** (pin **1.5.1** in v1.24.2; was **1.4.2** in v1.24.0); drop PWA `install_links` fork; BP-004 / ENGINEERING-AUDIT close; AuthKit gated until SiteBackup setup; PhoneInput `_phone_input.scss` theme bridge. **v1.23.1**: `make update-deps` runs helper `--run`; FormKit **2.4.1**; password-strength **2.2.0**; password-toggle **2.1.1**; Mercure **0.5.0**; Symfony **8.1.4** | Beacon | `053` / `056` / `058` / `081` / `083` / `100` / `101` | **Done** (v1.23.0; pins/DX v1.23.1) |
| 6.57 | **AuthKit security kits**: AuthKit **1.20.0** + slide-to-confirm **1.1.0** (register `gate`; Clear history `danger`) + device-intelligence **1.1.1** (collect, `di_obs`, Trusted browsers, new-device mail) + otp-input on `/reset-password/complete`; FormKit **2.5.2**; Device ID is not a credential; QR Approve stays a button; SMS OTP still Later | Beacon | `105-authkit-security-kits` | **Done** (v1.24.0) |
| 6.58 | **Ops ingest hardening**: Redis Messenger depths + failed gauge; quota usage cache; Envelope unique-constraint retry; batched retention + EVENT-STORAGE; shared `PrivateNetworkTarget` SSRF; legacy API-key ciphertext command; AuditKit timestamps; PHPStan empty baseline + FrankenPHP seams; includable coverage 100%; Dashboard Menu **2.1.10** | Beacon | `106-ops-ingest-hardening` | **Done** (v1.24.0) |
| 6.59 | **SQL / database error context** on issue detail (Query panel, in-app stack, culprit 255) + BeaconBundle `contexts.db` **1.8.0** | Beacon + Bundle | `107-sql-error-context` | **Done** (v1.24.0) |

### Next (immediate queue)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| — | Pull from Later (SSO / QR SMS OTP / WebAuthn / Hotwire Native) when prioritized | Beacon | — | **Next** |

### Done (AuthKit 1.12 foundation)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 6.24 | **AuthKit 1.12.0** bump + QR login foundation (phone fields, routes, enterprise SSO admin flag) | Beacon | `072-authkit-1.12` | **Done** |

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| — | Watching / favorites (projects or issues) on Dashboard | Beacon | extends `080` | **Later** |
| — | **SSO/SAML/OIDC** via AuthKit (SAML still Later; OIDC enterprise flag shipped in 1.12 / `072`) | Beacon | — | **Later** (OIDC ready) |
| — | **QR SMS OTP** verify (`phone_otp` / notifiers; image shipped in `075` / 6.27; reset-code OTP **UX** shipped in `105` / 6.57 — not SMS) | Beacon | extends `072`/`075`/`105` | **Later** |
| — | **WebAuthn / passkeys** when AuthKit runtime ships | Beacon | — | **Later** |
| — | **Hotwire Native / Symfony UX Native** server contract (`ux-native` + Turbo, native-aware Twig, `/config/*_v1.json`) — **not started**; PWA remains the mobile path | Beacon | `008-ux-native` | **Later** |
| — | OTLP gRPC / protobuf / Bundle exporter / Performance TSDB (HTTP JSON metrics shipped in `074` / 6.26) | Beacon (+ optional Bundle) | extends `074` | **Later** |
| — | Teams→member mapping / Adaptive Cards (OpenUri Assign shipped in `073` / 6.25) | Beacon | extends `073` | **Later** |
| — | IMAP / attachments / provider-native adapters (webhook inbound shipped in `076` / 6.28) | Beacon | extends `076` | **Later** |

---

## Explicitly out of scope (for now)

- Multi-region SaaS control plane / multi-org tenancy
- **SSO/SAML/OIDC** until an enterprise dedicated spec (AuthKit); not the same as magic links (`026`) or social OAuth (`060`)
- Source maps / session replay / profiling
- Uptime monitors / cron check-ins as first-class products
- Native store apps / Hotwire Native shells **inside this repo** until `008-ux-native` is prioritized (**Later** on this roadmap; PWA is the current mobile path)
- **PagerDuty-native** (generic HTTP webhook / digests may still target it)
- Public anonymous issue boards (share links in `026` still require constrained auth / viewer semantics)
- Mutation testing / 100% coverage of HTTP controllers and demo/install seed CLI (those stay excluded; includable `src/` is hard-gated at 100% — see `033` / [COVERAGE.md](COVERAGE.md))

See `docs/ARCHITECTURE.md` non-goals and constitution.

---

## Suggested release slicing

| Release (indicative) | Contents |
|----------------------|----------|
| **v0.7.0** | Phase 1 — project notifications + ignored regression reopen |
| **v0.8.0** | Phase 2 — retention, rate limit, health |
| **Bundle v1.4.0** | Phase 3.1–3.2 (Messenger + auto HTTP tx) |
| **Bundle v1.5.0** | Phase 3.3–3.4 (spans, tags, before_send) |
| **Bundle v1.6.0** | Phase 3.3–3.5 (spans, tags, before_send, transport sync/async/messenger + versioned UA) |
| **Bundle v1.6.1** | Phase 3.6 golden Envelope contract fixtures + ingest tests |
| **v0.9.0+** | Phase 4 slices; admin Projects; transfer ownership |
| **v0.10.0** | Phase 4 product depth (`014`–`022`) + Bundle companion docs (`023`–`024`) |
| **v0.10.1** | Issue aside / duplicate-modal UX; admin menu seeder sync; Phase 5 specs started |
| **v0.10.2** | Phase 5 backlog specs `027`–`033`; unified confirm/kit modal chrome |
| **v0.11.0** | Analytics charts (`025`); locales de/nl/fr/it/pt; UI density/motion; danger colors; shared table pagination |
| **v0.11.1** | Magic login + viewer + share links (`026`); golden Envelope contract (3.6) |
| **v0.12.0** | Phase 5 high/medium: threshold alerts (`027`), release health (`028`), FULLTEXT (`029`), delivery history (`030`), admin audit (`031`) |
| **v0.12.1** | Encrypted Mailer DSN (`034`); account appearance extras; admin user/group AuditKit meta; security/profile UX polish |
| **v0.12.2** | Security hardening: High `045`–`048` + Medium `049`–`052` (query-auth deprecation, health errors, worker recheck, secret-always) + magic-login Mailer gate |
| **v0.12.3** | Brand mark (tower + arcs), Montserrat UI font, docs/constitution PWA-only sync |
| **v0.12.4** | Install seed layers (`055`) + setup wizard UI (`056`) |
| **v0.12.5** | Product tour (`057`) + event tenancy / issue level hardening |
| **v0.12.6** | Mercure live alerts + PWA Web Push; encrypted Mailer From / Mercure URLs; sample seed Mercure defaults; tour Select all |
| **v0.12.7** | Public setup bootstrap (min / bulk); cookie consent platform seed + professional copy; DATABASE.md ER docs; Compose MySQL bind mount; fresh-install migration hardening |
| **v0.12.8** | Dual AuthKit/setup public URLs (bare `DEFAULT_LOCALE`); empty-catalog setup redirect; AuthKit password reset/magic; catalogue parity for all enabled locales; PHP 512M cache:clear |
| **v0.13.0** | Phase 6 product: ops overview (`035`), identity audit (`036`), AuthKit identity polish (`037`), monthly quota (`032`); SiteBackup setup, dogfood (`058`), AI export (`059`), social login (`060`); security residual (DNS pin, query-auth reject, Web Push allowlist, POST magic login, Caddy headers, `/api/doc` admin-only) |
| **v0.14.0** | Prometheus (`038`) + security residual (Bearer-only metrics, guest locale redirect, Web Push unsubscribe scope, magic-login Continue, query-auth default reject, Telegram DNS pin, CSP/`theme-boot`) |
| **v0.15.0** | Notification circuit breaker (`039`); CSP without script `unsafe-inline` + default HSTS; appearance palette; Tours/sidebar UX fixes |
| **v0.16.0** | Coverage (`033`) + GDPR (`043`); collaboration/API (`040`–`042`, `044`, `061`); SiteBackup dual setup locale (`056`) + secrets guard (`062`); RoutingKit (`064-routing-kit`); branded HTTP errors (`063-branded-http-errors`); CSP PHP delivery + display-pref defaults |
| **v0.17.0** | Mailer DSN audit (`6.15` / `065`); local Mailpit (`066`); Ops defaults in DB (`6.17`); Social login admin UI (`6.18`); Constitution v1.3.0 (no `env(VAR):` defaults in parameters) |
| **Bundle v1.6.10** | Phase 6.13–6.14: nested console extras + Scheduler context; Monolog bridge already shipped (docs closed) |
| **v1.0.0** | First stable major: OTLP logs/traces/metrics (`067`/`070`/`074`); Slack/Teams Resolve + Assign (`068`/`069`/`071`/`073`); AuthKit 1.12 + QR image (`072`/`075`); inbound email comments (`076`); branded 4xx/5xx expansion; Constitution Principle X |
| **v1.0.1** | Docs layout (`product`/`ops`/`dev`); Doctrine N+1 / query amplification fixes on list/export, retention, ingest thresholds |
| **v1.1.0** | Appearance presets (`082` / 6.32); FormKit/UiKit sync (`081` / 6.31); dashboard Assignments + aside panels (`079`/`080`); Ops env→DB (`084`); module boundaries + architecture convergence (`083`/`085` / 6.33–6.34); Playwright E2E; PHPUnit Unit/Functional/Integration layout + coverage expansion; Codex Security remediations |
| **v1.2.0** | Vitest frontend unit + deeper PHPUnit/Playwright; Compose `env_file: .env`; local port defaults; MySQL host ports removed |
| **v1.3.0** | DRY maintainability (`086` / 6.35): OTLP pipeline, factories, Twig shells, `ensure-up`, platform checkboxes + password-toggle chrome |
| **v1.3.1** | FormKit host-form parity; demo `ProjectFactory`; `OtlpIngestGatewayInterface`; unit tests (pipeline / hooks / factory) |
| **v1.4.0** | Project `project.*` permissions + Admin Roles/Permissions UI; `/admin` settings URLs; security audit hardening (`087` / 6.36) |
| **v1.5.0** | Project membership role `full` + InstanceRole delete-in-use guards (`088` / 6.37) |
| **v1.5.1** | Owner membership row UI (no edit/remove); kit admin `.nowo-ui-modal` Beacon chrome |
| **v1.6.0** | Project config export/import (`089` / 6.38); DSN UUID path; AuthKit 1.16 magic-login confirm; revoked API keys hide DSN |
| **v1.6.1** | IngestProjectAccessGate; config import N+1 + ownership guards; Mercure JWT secret fail-closed; notify/threshold query batching; Dashboard Menu 2.1.1 / Cookie Consent 1.6.2 |
| **v1.6.2** | Export/import Settings + Admin projects card uses Export \| Import tabs |
| **v1.6.3** | Show-once API DSN restore; 2 MiB JSON import cap; Cookie Consent 1.6.3 public-only `render_routes` |
| **v1.6.4** | PWA no Set-Cookie on manifest/SW; Mercure 0.8 Grant API; session 1d / Remember me 30d; `SYMFONY_BEACON_SESSID` |
| **v1.7.0** | CSRF via Symfony Forms (`090` / 6.39); kit Administration chrome (Menu / Breadcrumb / Routing / Http Log); AuthKit 1.17; CSP kit-admin polish; RoutingKit 1.4 / password-toggle 2.1.1 |
| **v1.8.0** | Member alert preferences (`091` / 6.40); Mercure `/users/{uuid}/member-alerts`; UserKit 1.1.6 disabled-account PreAuth; viewers edit own prefs from Account |
| **v1.8.1** | CS / LiveComponent DI polish; restore `seedTestOpsDefaults` PHPUnit helper name |
| **v1.8.2** | Firewall `user_checker` → UserKit AccountStatusUserChecker (disabled magic login); PHPStan / Rector / CS CI harden |
| **v1.9.0** | Maintenance mode (`092` / 6.41); security residual (`093` / 6.42 — breaking query-auth removal); PHPStan FrankenPHP 1.1.0 (`094` / 6.43); Settings/search maintainability |
| **v1.10.0** | Product FormKit `filter` / `beacon` + `form` catalogues + Twig `form_row` consolidation (`081` / `077` / `090` / 6.44); prefixed HTML form field names |
| **v1.11.0** | Audit residual hardening (`095` / 6.45): phone QR verify hygiene; maintenance ingest-only exclusions; Mercure hub guard; `#[IsGranted]` / Port / Metrics→Ops; batch prefs; admin pagination |
| **v1.12.0** | Audit follow-up (`096` / 6.46): Read API rate limit; hash-at-rest ingest secrets; Project voter abstain; membership write DRY; filter DTOs; QR disabled; Slack user-id hygiene; beacon-bundle 1.7.0 |
| **v1.13.0** | E2E CI + AuthKit login throttle (`097` / 6.47); FormKit 2.3.0; Playwright catalog ~247 Covered; FrankenPHP hot reload |
| **v1.14.0** | Shared MySQL mode + account profile split + `app_user`→`user` (`098` / 6.48); `SHARED-SERVER.md` |
| **v1.15.0** | Setup wizard 100% (`056` / 6.49): SiteBackup 1.13 + CookieConsent 1.8 + `cache_doctrine`; shared infra Compose; Redis dual-mode + payload promotions + access split (`099` / 6.50) |
| **v1.15.1** | Cookie consent guest skin (host SCSS + bottom-left seed); PHPUnit SQLite cold-start; Appearance/theme/locale E2E mutations + Vitest/PHPUnit coverage |
| **v1.16.0** | Phone input kit on Account → Profile (`100` / 6.51); `.env.local` (REQ-ENV-003); `make restart` force-recreate; CI shared-infra + Composer flock |
| **v1.17.0** | Kit CSP upstream + shared helpers (`101` / 6.52): PhoneInput 1.3 / CookieConsent 1.9 / FormKit 2.4 / UiKit 1.8; shared Mailpit preference |
| **v1.18.4** | Issue detail tabs + temporary API DSN reveal (`102` / 6.53); dogfood `ignore_exceptions` for expected 403s; PHPStan CLI sleep ignore for `app:beacon:test` |
| **v1.19.0** | REQ-QA-002 closed (`033`): hard `COVERAGE_MIN=100` on includable PHP + Vitest 100% whitelist; `docs/COVERAGE.md`; engineering-audit REF-001 closed |
| **v1.20.0** | Cookie Consent Vite bundle + dogfood earliest `ROLE_ADMIN` + E2E security denials (`103` / 6.54); PWA cache v5; catalog ~259 Covered |
| **v1.21.0** | Isolated Playwright E2E stack (`104` / 6.55): `app_e2e` / `:9460` / `make test-e2e-isolated`; BeaconBundle `E2E_BEACON_TARGET`; Morphicons theme/burger/password morphs |
| **v1.22.0** | Dogfood probe suite (`058`): `make beacon-suite` / `app:beacon:test --suite`; where-explicit client tags (`console.command`, `url` / `http.route`, …) |
| **v1.23.0** | Kit polish + first-run gate (`6.56`): hot-reload-bundle 1.3.2; PWA `install_links` vendor-only; AuthKit gated until setup (`056` FR-014); PhoneInput theme bridge; BP-004 / ENGINEERING-AUDIT close |
| **v1.23.1** | Pin refresh + Composer DX: hot-reload **1.4.0** (`nowo:hot-reload:check`); FormKit **2.4.1**; password-strength **2.2.0**; password-toggle **2.1.1**; Mercure **0.5.0**; Symfony **8.1.4**; `make update-deps` runs helper `--run` |
| **v1.23.2** | CI Quality: drop removed Rector `SymfonySetList::SYMFONY_81`; keep Symfony code-quality sets only (`091`) |
| **v1.23.3** | Kit pin refresh + prod hardening (`when@prod` HttpLog/Maintenance/SiteBackup/Dashboard Menu; `/admin` → `ROLE_ADMIN`); E2E Messenger `?dbindex=` + Compose `env_file: !override` (`104`) |
| **v1.24.0** | AuthKit security kits (`105` / 6.57) + ops ingest hardening (`106` / 6.58) + SQL error context (`107` / 6.59): slide-to-confirm / device intelligence / OTP UX; Redis queue+failed depths; Query panel + BeaconBundle **1.8.0** |
| **v1.24.1** | Setup token gate default (`SITE_SETUP_TOKEN=beacon-local-setup` + 403 paste form); versioned `.env.e2e.dist` for isolated Playwright (`104`) |
| **v1.24.2** | HotReloadBundle **1.5.1** `shared_worker`; CSP vendor inline nonce stamp + `app.csp.*_extra`; Redis AUTH required outside `dev`/`test` (REQ-INFRA-SHARED-003); E2E slide-to-confirm PointerEvents |
| **Unreleased** | — |
| **Next** | Later Phase 6+ (SSO/SAML, WebAuthn, QR SMS OTP, Hotwire Native `008`, …) when specified |

Versions are indicative; cut releases when exit criteria for a phase (or a coherent subset) are met.

---

## How to work this roadmap

1. Pull items from **Later** when prioritized.
2. Mark rows **Done** and bump the indicative release when shipping.

Last updated: 2026-08-29 (v1.24.2 hot-reload 1.5.1 + CSP stamp + Redis AUTH).
