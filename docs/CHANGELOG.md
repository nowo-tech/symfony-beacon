# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Ops defaults **section tabs** with own routes (`/settings/ops-defaults/{section}`); shared product tabs delegate to UiKit `_tabs`.
- Form field loop convention (`077`): shared `templates/form/_fields.html.twig`; host Symfony forms paint unrendered Type children before actions (FormKit owns field attrs).
- `nowo-tech/http-log-bundle` **1.0.1**: HTTP request/response audit log with admin UI at `/admin/http-log` (ROLE_ADMIN), kit host layout, Messenger async persist/export/purge, MDK table `nowo_http_log_entry`.
- Dashboard **Assignments** panel (`079`): `/dashboard/assignments` — mine / teammates / unassigned across accessible projects with filters (project, level, status, priority, assignee, search).
- Dashboard aside panels (`080`): **Summary**, **Activity**, **Mentions** (`issue_mention` + read state), **Alerts**, **New in release** — menu/breadcrumb seeds under Projects; list panels use shared `PagePagination`.
- `OtlpIngestGateway`: shared OTLP auth / body limits / quotas / rate limit / metrics for logs, traces, and metrics controllers.
- `make kit-smoke`: AuthKit bootstrap + magic login + password reset + login throttle suite after kit bumps.
- Production **operational inventory** checklist (hooks signing secrets, metrics, trusted proxies, SiteBackup, retention).
- `HookMutationPolicy` / Ops defaults **Allow anonymous Resolve** (default off): Slack/Teams Resolve requires a mapped Beacon actor unless legacy flag is enabled.
- `OutboundUrlGuard` validates **A + AAAA** DNS answers and prefers an IPv4 pin when both exist.
- Admin **User / Group / Project** create-edit forms use FormKit Types + `form/_fields.html.twig` (077).
- **Ops env → database** (`079`): envelope max bytes, reject query auth, metrics token/require, inbound email, allow private URLs, anonymous Resolve moved to **Administration → Ops defaults** (encrypted secrets); instance config export **v3**.

### Changed

- Composer kit sync (`081-formkit-uikit-kit-sync`): host **`nowo-tech/ui-kit-bundle` 1.7.0** (`tailwind` / `ux_icon` / `row_actions_display: icon`); FormKit **2.2.0** (`auto_help` / `auto_placeholder`); AuthKit **1.15.0** (Halite on OAuth secrets/tokens); RoutingKit **1.3.0** (FormKit panel form); DashboardMenu **2.0.1**, BreadcrumbKit **2.1.3**, CookieConsent **1.6.0**, HttpLog **1.1.0**, SiteBackup **1.10.0**.
- Authenticated product chrome composes UiKit `_shell_open` / `_shell_close`; flashes → UiKit `_toasts`; page loader embeds UiKit `_thinking_orb`; product tabs → UiKit `_tabs`.
- Security hardening follow-ups: `OutboundUrlGuard` validates public **A + AAAA** (prefer IPv4 pin); SiteBackup `include_paths` omits `.env`; PRODUCTION docs for Messenger `failed` purge + AuthKit encrypt migrate.
- RoutingKit host `panel/form` override uses Symfony/FormKit `form_*` (1.3+) while keeping Beacon card chrome.
- FormKit kit profiles set explicit `translation_domain` + `auto_help`/`auto_placeholder: false` (`auth_kit`, `dashboard_menu`, `breadcrumb_kit`, `http_log`, `cookie_consent`, `routing_kit`); Settings Mailer / Mercure / Ops / Social login Types extend `FormKitAbstractType`.
- Product + kit list pagination converge on `kit/_pagination` → UiKit host `_pagination` (`« »` + page numbers).
- Kit admin UIs use `css_framework: tailwind` + vendor `nowo-ui.css` + `.kit-admin` token remap (Menu / Breadcrumb / Cookie admin / RoutingKit / SiteBackup / HttpLog); Vite `kit-admin` stays Bootstrap-free.
- CI Coverage soft gate: `COVERAGE_MIN=35` (statement %; never 100%).
- Inbound email webhook accepts secret **only** via `X-Beacon-Inbound-Secret` (body `beacon_secret` rejected).
- Cookie Consent admin **1.6.0**: route-based settings sections (`/settings/{section}`); host drops `data-kit-form-tabs` / tabify JS and restyles vendor `.nowo-ui-tabs` to Beacon tab chrome (UiKit consumer — see `081`).
- SiteBackup: explicit root `css_framework: tailwind` + `data-css-framework` on setup/panel host shells (pin **1.10.0**; see `081`).
- Former `BEACON_INGEST_REJECT_QUERY_AUTH`, `BEACON_METRICS_*`, `BEACON_ENVELOPE_MAX_BYTES`, `BEACON_INBOUND_*`, `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS`, and `BEACON_HOOKS_ALLOW_ANONYMOUS_RESOLVE` env vars are no longer read (configure via Ops defaults).

## [1.0.1] - 2026-07-31

### Changed

- Documentation layout: secondary manuals moved under `docs/product/`, `docs/ops/`, and `docs/dev/`; canonical guides stay at `docs/` root. Index: [README.md](README.md) (Fixes #44 / #46). Relative links from nested manuals to Compose / Caddy / `.env.dist` corrected.

### Fixed

- Doctrine N+1 / query amplification on hot paths: hydrate `Issue.assignee` + `duplicateOf` for list/export/API; batch retention aggregate recompute; dedupe volume-threshold evaluation per envelope; share-link `issue` fetch-join; sole-owner and membership lookups without lazy collection walks; performance detail span hydrate.

## [1.0.0] - 2026-07-31

First **stable major** release: Phases 0–6 through **6.28** are Done. Upgrade from **0.17.0** is additive (migrations + Composer). Enterprise SSO/SAML, WebAuthn, and QR SMS OTP remain ROADMAP **Later**.

### Added

- **Inbound email → issue comment** (`076-inbound-email-comment`, Fixes #42): opt-in webhook `POST /hooks/email/inbound`; Reply-To tokens on mention/assign mail; shared `IssueCommentCreator`; Message-ID idempotency. See [INBOUND-EMAIL.md](product/INBOUND-EMAIL.md).
- **QR login image** (`075-qr-png`, Fixes #40): AuthKit **1.12.1** + `endroid/qr-code`; QR show pages render PNG (with GD) or SVG data-URI. SMS OTP remains Later.
- **OTLP metrics ingest** (`074-otlp-metrics`, Fixes #38): `POST /api/{projectId}/otlp/v1/metrics` accepts OTLP ExportMetricsServiceRequest JSON; failure-like data points map to Beacon events via the Envelope worker; same auth/governance as OTLP logs/traces; cap 200.
- **Teams Assign to me** (`073-teams-assign-openuri`, Fixes #36): MessageCard **OpenUri Assign to me** when a destination signing secret is set; `GET /hooks/teams/assign-me` validates HMAC, requires Beacon login + triage, assigns via `IssueAssigneeChanger` (`via: teams`).
- **AuthKit 1.12.0** (`072-authkit-1.12`, Fixes #34): bump from 1.11.4; QR login foundation (`qr_login` enabled, `/login/qr*` public, `app_user.phone` / `phone_verified_at`, challenge table); admin **Enterprise SSO** flag on social credentials; Account profile phone field.
- **Slack Assign-to-me** (`071-slack-assign-mapping`, Fixes #32): optional Account **Slack user ID**; Block Kit **Assign to me** on Slack alerts; Resolve attributes actor when mapped + triage; shared `IssueAssigneeChanger`.
- **OTLP traces ingest** (`070-otlp-traces`, Fixes #28): `POST /api/{projectId}/otlp/v1/traces` accepts OTLP ExportTraceServiceRequest JSON; ERROR spans (status +/or exception attributes) map to Beacon events via the Envelope worker; same auth/governance as OTLP logs; cap 200.
- **Teams interactive Resolve** (`069-teams-interactive-actions`, Fixes #26): MessageCard HttpPOST **Resolve** when a destination signing secret is set; `POST /hooks/teams/actions` verifies HMAC token (7-day expiry) and resolves via `IssueStatusChanger` (`via: teams`).
- **Slack interactive Resolve** (`068-slack-interactive-actions`, Fixes #24): optional encrypted Slack signing secret on notification destinations; Block Kit **Resolve** on issue alerts; `POST /hooks/slack/interactions` verifies `X-Slack-Signature` and resolves via shared `IssueStatusChanger`.
- **OTLP logs ingest** (`067-otlp-ingest`, Fixes #20): `POST /api/{projectId}/otlp/v1/logs` accepts OTLP ExportLogsServiceRequest JSON; WARN+ LogRecords map to Beacon events via the Envelope worker; same `X-Beacon-Auth` / rate / quota / size limits; query auth rejected; dogfood `before_send` also drops `/otlp/` paths.
- **Branded HTTP errors** expanded: Twig pages for **400**, **401**, **408**, **429**, and **502** (in addition to 403/404/500).

### Changed

- **Constitution Principle X**: forbid Cursor / `@cursor.com` / `Made-with: Cursor` attribution on commits, issues, and PRs (human git identity only).
- CI MySQL service images use the AWS Public ECR mirror of `mysql:9.7` to avoid Docker Hub pull timeouts on GitHub Actions.

### Fixed

- Dark mode page-loader flash and sidebar slide motion.
- Mercure / Slack interaction test and CS Fixer / Rector CI blockers.

## [0.17.0] - 2026-07-31

### Added

- **Local Mailpit** (`066-local-mailpit`): optional Compose profile `mail` + `make mailpit` / `make mailpit-logs` for catching SMTP in development; docs [MAILPIT.md](ops/MAILPIT.md). Not started by `make up`; not in `compose.prod.yaml`.
- **Mailer DSN change audit** (`6.15`, Fixes #18): Admin Mailer save/clear records `UserAction` `instance.mailer_updated` with redacted `scheme`/`host` only (never DSN secrets); `MailerDsnValidator` scheme allowlist (`smtp`/`smtps`/sendmail/native + common provider schemes).
- **Ops defaults in database**: retention, ingest rate, daily/monthly quotas, delivery-history size, and notification circuit-breaker settings under **Administration → Ops defaults**; migration `Version20260731170000`; included in instance config export v2.
- **Social login admin** (extends `060`): OAuth provider credentials managed in Administration → Social login (`auth_kit_social_credential`); replaces env/`app:seed-social-login` bootstrap.

### Changed

- **Constitution v1.3.0**: Principle IX — forbid `env(VAR):` default map entries in `config/parameters.yaml` (defaults belong in `.env.dist` / `when@…` package config).
- Former `BEACON_RETENTION_*`, `BEACON_INGEST_RATE_LIMIT`, `BEACON_EVENT_QUOTA_*`, `BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT`, and `BEACON_NOTIFICATION_CIRCUIT_BREAKER_*` env vars are no longer read (configure via Ops defaults).
- **Roadmap**: Phase **6.15** / **6.16** Done in **v0.17.0**; Bundle **v1.6.10** closed **6.13** / **6.14**; **Next** = Later Phase 6+ when prioritized.

## [0.16.0] - 2026-07-31

### Added

- **Instance config export/import** (`044`, Fixes #14): ROLE_ADMIN JSON export/import of allowlisted appearance + instance flags (`beacon-instance-config` v1); secrets rejected; audit `instance.config_*`
- **Read API + project tokens** (`042`, Fixes #12): Bearer `brt_` tokens (hashed at rest) for `GET /api/projects/{uuid}/issues` and issue detail; create/revoke in Project Settings; OpenAPI `BeaconReadToken`; ingest keys rejected; migration `Version20260731160000`
- **Issue mentions + assignee email** (`040`, Fixes #8): `@name` in comments notifies project members via encrypted instance Mailer; assign notifies the new assignee (skipped when Mailer is not deliverable)
- **Similar issues** (`041`, Fixes #10): issue show suggests title-similar issues in the same project (cap 5) with mark-duplicate shortcut
- **Share link max uses** (`061`, Fixes #2): optional `max_uses` / `use_count` on `project_share_link`; Settings UI defaults new links to **1** use (clear the field for unlimited until expiry); migration `Version20260731150000`
- **GDPR account export / anonymize** (`043`): Account → Privacy JSON download (`beacon-account-export/v1`); self-service and admin anonymize (scrub + disable + `anonymized_at`); blocks sole project owner / last admin; app-owned (not anonymize-bundle runtime); migration `Version20260731140000`
- **CI coverage soft gate** (`033`): GitHub Actions **Coverage** job (PCOV) uploads Clover + HTML artifacts; local `make test-coverage`; optional `COVERAGE_MIN` statement-% soft threshold (unset = informational; never 100% by default)
- **CI secret scan (Gitleaks):** job **Secret scan** fails the workflow when committed secrets are detected (full git history); local `make secrets-scan`; config [`.gitleaks.toml`](../.gitleaks.toml)
- **CI git hygiene:** job **Git hygiene** fails when any commit on `HEAD` includes Cursor `Co-authored-by` / `Made-with` trailers (`make check-no-cursor-coauthor`; same as `.githooks`)
- **Issue / PR templates:** Question + CI/ops issue forms; typed PR templates (`bugfix`, `feature`, `docs`, `chore`) under `.github/PULL_REQUEST_TEMPLATE/`
- **RoutingKit** (`064-routing-kit`): `nowo-tech/routing-kit-bundle` dual `/path` + `/{locale}/path` for `#[Routable]` app controllers; admin panel `/_routing/` in kit chrome
- **Branded HTTP errors** (`063-branded-http-errors`): Twig 404/403/500 with Beacon illustrations + mascot; `/_error/{code}` preview **dev-only**
- **SiteBackup setup dual locale** (`056`): SiteBackupBundle ≥ 1.7.0 `setup.locale` (`both` + `serve`) — bare `/setup` for `DEFAULT_LOCALE`, `/{_locale}/setup` for others; friendlier setup token gate template

### Changed

- **PHPStan / FrankenPHP CI** (`063-phpstan-frankenphp-ci`): FormBuilder generics on `AccountDisplayType`; SiteBackup guard instance latch; ingest query-auth test swaps `IngestQueryAuthSettings` instead of `putenv`
- **SiteBackup secrets guard** (`062`, Fixes #3): fail closed for empty/local-default `SITE_SETUP_TOKEN` / `SITE_BACKUP_PASSWORD_HASH` in **all environments except `dev`/`test`** (covers `staging` and misnamed deploys, not only `prod`)
- **SiteBackup guard + Docker build** (`064-sitebackup-guard-skip-cache-clear`): skip guard for `cache:clear` / `cache:warmup` / `assets:install` so `frankenphp_prod` image builds can run Composer auto-scripts; HTTP and other console commands still fail closed
- **CSP delivery:** `Content-Security-Policy` moved from Caddy to PHP (`ContentSecurityPolicySubscriber`) so the Web Debug Toolbar can merge script/style nonces; kit page `window.*Config` scripts rewritten to JSON islands (`KitInlineConfigScriptSubscriber`)

### Fixed

- Account display preferences always persist concrete defaults on user create/update (locale = `%default_locale%`, theme `light`, contrast/motion `system`); legacy null rows heal on `/account/display` and effective getters no longer leave selects empty
- CSP `script-src 'self'` no longer blocks login password toggle (`password-toggle` Stimulus), kit dashboard config scripts, or the Symfony profiler toolbar nonce
- Web Debug Toolbar no longer stuck on “loading”: debug CSP allows `'unsafe-eval'` (toolbar `eval()`s `/_wdt` scripts); `/_wdt`/`/_profiler` fragments skip app CSP
- Register Stimulus controllers added for CSP hardening (`confirm-submit`, `navigate-select`, `issue-panels-reset`, `password-confirm-mirror`) and load SameOrigin CSRF helper
- Guest shell loads Vite `theme-boot` (was an empty include after the inline-theme migration)
- Cookie consent banner keeps horizontal inset when `.show` overrides vendor padding

## [0.15.0] - 2026-07-31

### Added

- **Notification circuit breaker** (`039`): auto-pause destinations after consecutive delivery failures; admin **Resume** (CSRF); optional cooldown via `BEACON_NOTIFICATION_CIRCUIT_BREAKER_*`
- **Appearance palette**: Administration → Appearance can set warn / paper / ink / surface colors (light + dark); migration `Version20260731130000`

### Changed

- **CSP / HSTS hardening:** default CSP drops `script-src 'unsafe-inline'` (Vite `kit-admin` + `swagger-ui-boot`, Stimulus for confirms/selects); HSTS enabled by default except `localhost` / `127.0.0.1`; kit admin Bootstrap self-hosted (no jsDelivr)

### Fixed

- Expanded choice checkboxes (e.g. Account → Display → Tours) keep input and label on one row
- Preferences sidebar marks **Display** / **Security** / **Profile** current for all related `account_*` sub-routes (not only exact paths)

## [0.14.0] - 2026-07-31

### Added

- **Prometheus metrics** (`038`): `GET /metrics` (text exposition) — Messenger depth, failed notification destinations, ingest ACK/reject counters; `ROLE_ADMIN` or `BEACON_METRICS_TOKEN` (prod requires token configured)

### Changed

- **Security residual hardening:** `BEACON_INGEST_REJECT_QUERY_AUTH` defaults to **1** in all environments; prod `/metrics` requires a non-empty `BEACON_METRICS_TOKEN` (`BEACON_METRICS_REQUIRE_TOKEN`); CSP adds `object-src 'none'` and theme prefs boot via Vite entry `assets/theme-boot.ts` + `data-*` (no inline theme scripts)

### Fixed

- Guest locale switch redirects use `SafeInternalRedirect` (reject `/\\…` / backslash open redirects)
- `/metrics` accepts only `Authorization: Bearer` (query `?token=` rejected)
- Web Push unsubscribe deletes only the current user's subscription (endpoint hash scoped to owner)
- Magic login confirm requires an explicit Continue click (no auto-POST), preserving `check_post_only`
- Telegram notification delivery pins DNS via `OutboundUrlGuard` like other HTTP channels

## [0.13.0] - 2026-07-31

### Added

- **Security hardening residual:** webhook DNS pin (`OutboundUrlGuard` + HttpClient `resolve`); Web Push endpoint allowlist; `BEACON_INGEST_REJECT_QUERY_AUTH` (prod default reject query auth); POST-only magic-login confirm interstitial; Caddy security headers (`053`); `/api/doc` → `ROLE_ADMIN` (`054`); HTTP Envelope `429` tests for daily/monthly quotas + OpenAPI multi-cause `429`
- **Ops overview** (`035`): `/admin/ops` for instance admins — Messenger queue depth, open issues, suspended ingest, error spikes, failed deliveries (optional project filter); Administration hub card + menu/breadcrumb seed
- **Admin identity audit** (`036`): filterable `user_action` timelines on Admin → Group show and Admin → User activity (parity with project audit `031`); shared Twig partial
- **Identity kit polish** (`037`): shared Account area nav (Profile|Security|Display); linked social accounts + security activity on `/account/security*`; AuthKit audit subscribers set `subjectUser`
- **Monthly event quota** (`032`): `BEACON_EVENT_QUOTA_MONTHLY` + `project.event_quota_monthly`; UTC month boundary; Settings governance; Envelope/worker `429` / drop; 80% approaching flash
- SiteBackup hardening: `setup.setup_token` wired to `SITE_SETUP_TOKEN`; prod refuses empty/local token and the documented local panel password hash (`SiteBackupSecurityDefaultsGuard`); `compose.prod.yaml` requires both secrets
- AuthKit password reset **OTP code** path (`delivery: both`): `/reset-password/complete`, Beacon Twig override, email body includes `%code%` backup; rate limit + audit (`auth.password_reset_requested`)
- Admin directory search (`q`) on Users and Groups (Projects already had it); table layout aligned across Users / Groups / Projects
- **SiteBackupBundle** (`nowo-tech/site-backup-bundle` **1.5.0**): panel `/_site_backup`, cold-start wizard `/setup` (migrations, `app:seed-platform`, admin provisioner, optional sample data); `SITE_BACKUP_PASSWORD_HASH` in `.env.dist`
  - Durable setup progress: `progress_storage: chain` (filesystem + Doctrine DBAL; load prefers DB so wiping `var/` keeps the current step)
  - `IncompleteSetupProgressDetector` (`detectors.incomplete_progress: true`) — site gate while phase is `running` / `waiting_input` / `failed`
  - Progress timestamps `started_at` / `completed_at`; route prefixes honour `setup.path_prefix` / `panel.path_prefix`
  - **1.3.x:** `bootstrap_mode` (guided admin vs full SQL dump), profile `full_database`, `when_answer` filters, `advance_mode`, optional YAML `tabs` + checkers
  - **1.4–1.5:** `setup.layout_template` + `panel.layout_template` (`kit/site_backup_*_layout.html.twig`); Twig globals; vendor reasons + DB connection UX; restyle via `.nowo-site-backup-*` / `.nowo-ui-*`
- **Dogfooding** (`058`): require `nowo-tech/beacon-bundle`; `config/packages/nowo_beacon.yaml`; `make ready`; demo seed writes loopback `BEACON_DSN` when empty; stable demo secret; `DropSelfIngestBeforeSend` anti-loop
- **AI issue export** (`059`): `beacon-ai-export/v1` Markdown/JSON per issue; Copy for AI + downloads; [docs/product/AI-EXPORT.md](product/AI-EXPORT.md)
- Cookie Consent admin Web UI (`web_ui` + `kit/cookie_consent_admin_layout.html.twig`, ROLE_ADMIN)
- Dev: `nowo-tech/phpstan-frankenphp` (`ruleset-classic` + `ruleset-worker` + `ruleset-hardening` in `phpstan.neon.dist`)

### Changed

- Composer kit bumps: AuthKit **1.11.4** (`auth_panel` / mail-ready / form themes / Twig UI globals), PWA **1.2.0** (mark/button config), SiteBackup **1.6.0**, Beacon client **1.6.9**, Dashboard Menu **1.0.4** / Breadcrumb **2.0.11** (`--nowo-ui-*`), Cookie Consent **1.4.7** (CSRF-optional modal); dropped AuthKit `security/*` and PWA Twig forks; kit admin maps `--nowo-ui-*` under `.kit-admin`
- Cookie Consent **1.4.5**: vendor Tailwind modal/form use `--nowo-cc-*` tokens; dropped host form-theme fork and manual `twig.paths` (bundle `TwigPathsPass` prepends app overrides)
- Cookie consent **modal** uses vendor Twig again (skin via `_cookie_consent.scss`); dropped host fork of `cookie_consent.tailwind.html.twig` so `display_config` / two-step / preference sections track CookieConsent upgrades
- AuthKit layout extends shared `layouts/guest_shell.html.twig` (single guest chrome; bubble via `cookie_consent_extras` block)
- Removed duplicate password-strength `requirement.*` / `generator.*` keys from `messages.*` (AuthKit domain + PasswordStrength vendor remain)
- AuthKit logout CSRF enabled (`enable_csrf: true`); user menu passes `_csrf_token` (AuthKit 1.7.6+)
- Menus / Breadcrumbs kit chrome: Beacon page heading + `.panel` shell; `nowo-ui-*` restyled under `.kit-admin` to admin tokens (still no page Twig forks; kits’ TwigPathsPass has no `@!` namespace)
- Replaced the custom `/setup` wizard with SiteBackupBundle setup (`/setup`); removed `SetupWizardController` / `LocalizedPublicPath` / setup rate limiter
- Composer deps: **site-backup-bundle 1.5.0** (Packagist); Symfony 8.1 patch bump (`framework-bundle`/`console`/`form`/… **8.1.2**, `http-client` **8.1.3**); PHPUnit **13.2.6**, PHPStan **2.2.7**, Rector **2.5.8**, PHP-CS-Fixer **3.95.17**
- SiteBackup setup + panel: `layout_template` kit shells + `.nowo-site-backup-*` / `.nowo-ui-*` CSS (REQ-UI-001; no Twig page forks)
- Composer kits/Symfony bump: AuthKit 1.8, Dashboard Menu 1.0, Cookie Consent 1.4, Breadcrumb 2.0.9, Login Throttle 3.1, Password Strength 2.1, Password Policy 1.4, plus related nowo-tech / Doctrine Bundle 3.3 / Web Push 11
- Kit Twig strategy: remove forked dashboard Menu/Breadcrumb pages; host shells only (`templates/kit/*_dashboard_layout.html.twig` + `body_before`/`body_after` wrappers) so kit upgrades land without re-copying CRUD Twig
- Password Strength form theme extends `@NowoPasswordStrengthBundle` (2.x namespace); Password Policy overrides use domain `NowoPasswordPolicyBundle`
- Dashboard Menu / Breadcrumb: `css_framework` / `icon_set` / `security.access_roles` configured
- Ingest worker: one Doctrine flush per envelope (deferred notifications/thresholds); Messenger `--memory-limit=256M`
- Retention aggregate recompute uses SQL `COUNT`/`MIN`/`MAX` instead of hydrating all events
- `IssueRepository::findByRelease` capped at 500 (same as environment compare)

### Fixed

- Cookie consent inventory aligned with CookieConsentBundle names (`Cookie_Consent`, `Cookie_Consent_Key`, `Cookie_Category_*`) plus Symfony `csrf-token_*`; legal cookies page and platform seeder updated
- Envelope ingest clears the EntityManager when auth left a managed `ProjectApiKey` (sync Messenger) so notification flushes cannot double-encrypt Halite secrets
- PHPUnit bootstrap writes a durable Halite key under `var/secrets/` so KernelBrowser reboots keep decrypting API secrets (multi-request Envelope ingest)
- AuthKit mailer gate + magic-login rate limit also match `*_unlocalized` dual-URL routes (`locale.unlocalized: serve`)
- Setup locale switcher: returning to bare `/setup` (default locale) no longer keeps a sticky non-default session language

## [0.12.8] - 2026-07-21

### Added

- Setup auto-redirect when platform catalogs are empty (menus / breadcrumbs / cookie consent) — HTML GETs go to locale-aware `/setup` (`PlatformBootstrapState` + `PlatformSetupRedirectSubscriber`) (`056`)
- AuthKit **password reset** + **magic login** flows (bundle routes/templates; mail via instance Mailer; rate-limit + audit subscribers); migration `Version20260721250000` (`password_reset_token` / `password_reset_expires_at`)
- Dual public URLs for AuthKit + setup: default locale bare (`/login`, `/setup`), other locales prefixed (`/en/login`, `/en/setup`); setup redirects prefixed default-locale URLs to bare (`LocalizedPublicPath`)
- Full UI catalogue parity for enabled locales (`messages.{en,es,de,nl,fr,it,pt}.yaml`)
- FrankenPHP / PHP `memory_limit = 512M` for Twig/kit `cache:clear` in prod (`.docker/frankenphp/conf.d/10-app.ini`)

### Changed

- Setup wizard: **required** platform install, then optional AuthKit first-admin register + Full sample load (no minimum/bulk one-click presets)
- AuthKit `locale.in_path: both` + `unlocalized: serve` (was bare-only / redirect-centric); guest locale via path switcher or `/locale/{locale}`; dashboard URLs still never carry `_locale`
- Legal pages keep bare → `/{DEFAULT_LOCALE}/legal/…` redirects (not yet aligned with AuthKit/setup serve pattern)

### Fixed

- Fresh-install `Version20260721230000` no longer re-ADDs `event.project_id` when the column already exists

## [0.12.7] - 2026-07-21

### Added

- Database schema documentation with Mermaid ER diagrams: [DATABASE.md](dev/DATABASE.md)
- Platform seed (`app:seed-platform` / Setup platform step) also seeds cookie consent profile + inventory (`CookieConsentDemoSeeder`); `use_database_config: true`

### Changed

- Setup wizard (`/setup`) is public **before the first user** with two one-click presets (minimum vs full sample load); after users exist, only `ROLE_ADMIN` (login links to setup while bootstrap is open)
- Local Compose MySQL data uses host bind mount `./.data/mysql` (gitignored) instead of a named Docker volume
- Professional cookie-consent copy (modal, inventory, legal page, category labels)

### Fixed

- Fresh-install migrations: re-introspect before dropping `uniq_event_id`; only stamp `setup_completed_at` when users exist; clear setup flag when no users; idempotent AuditKit indexes on concurrent migrate (`Version20260721195000`)

## [0.12.6] - 2026-07-21

### Added

- Mercure hub (Compose + Caddy `/.well-known/mercure`) for **optional** live member alerts when a **new issue** is created on an associated project — enable under **Administration → Mercure** (off by default; URL/JWT from UI or `MERCURE_*` env); operator manual [MERCURE.md](ops/MERCURE.md)
- Optional PWA / browser **Web Push** (VAPID) opt-in under **Account → Display**; encrypted `push_subscription` storage and Messenger fan-out (`DeliverWebPushForProjectMessage`); service worker push handlers appended to `nowo-tech/pwa-bundle` SW
- Account → Display **product tours** card: per-tour enable/disable with Select all (`nowo-tech/select-all-choice-bundle`)
- Sample seed (`app:seed-sample` / Setup sample actions) enables Mercure with `MERCURE_*` env defaults when instance fields are empty

### Changed

- Instance Mailer **From** and Mercure **URL / public URL** stored encrypted at rest (with DSN + JWT secret); `mailer_from` widened to `text` (`Version20260721242000`)
- Account → Display groups layout, product tours, and push notifications into separate panels

## [0.12.5] - 2026-07-21

### Added

- Product tour (`057`): contextual driver.js walks on dashboard, project Issues, and admin hub — steps filtered by instance role and project permissions; per-page seen tracking (`product_tour_seen_pages`); finish/close hides that tour until Account → Display → Replay
- Indexes `idx_event_issue_environment` and `idx_event_issue_release` for filtered event queries

### Changed

- `event.event_id` uniqueness is scoped per project (`uniq_project_event_id`); `event.project_id` denormalized for tenant queries (`Version20260721230000`)
- `issue.level` is a typed whitelist (`fatal` / `error` / `warning` / `info` / `debug`); unknown ingest values map to `error`
- Retention purge recomputes issue aggregates (`event_count`, first/last seen, release/env) after deleting events
- Duplicate marking walks the `duplicateOf` chain to reject longer cycles; share-link consume re-checks issue/project match; assignee assignment centralized in `IssueAssigneeGuard`

### Fixed

- PHPUnit SQLite database path uses `/dev/shm` in Docker to avoid readonly / disk I/O failures on bind-mounted `var/cache`

## [0.12.4] - 2026-07-21

### Changed

- Install seed layers (`055`): `app:seed-platform` (menus/breadcrumbs), slim `app:seed-demo` (identity+DSN), `app:seed-sample` (dev/load/huge); `make bootstrap` = migrate + platform only — see [INSTALL.md](INSTALL.md)
- Setup wizard UI (`056`): `/setup` for `ROLE_ADMIN` (platform / demo / sample steps + dismiss); upgrade migration marks existing instances complete

## [0.12.3] - 2026-07-21

### Changed

- Brand mark refreshed (tower + three signal arcs): SVG wordmarks/mark, `favicon.ico`, and PWA icons under `public/brand/` and `public/icons/`
- UI typeface aligned with brand wordmarks: **Montserrat** replaces Source Serif 4 / IBM Plex Sans (mono remains IBM Plex Mono)
- PWA service-worker `cache_version` bumped to `v2` so installed clients pick up new icons and CSS
- Docs/constitution product sync: PWA-only (Hotwire removed from constitution), ARCHITECTURE viewer/auth/notifications, README coverage (Mailer, `/api/doc`, locales), `docs/API.md`, retrospective specs `045`–`052`, `008` marked superseded

## [0.12.2] - 2026-07-21

### Security

- Webhook delivery no longer follows HTTP redirects (`max_redirects: 0`), closing SSRF via 302-to-private after `OutboundUrlGuard` (`045`)
- Issue-scoped share links no longer grant project-wide viewer access; list/analytics need a project-wide grant; issue detail uses `requireIssueRead` (`046`)
- Admin view-as-member and account locale redirects reject open redirects (`//host`, scheme-relative, off-site) via `SafeInternalRedirect` (`047`)
- Production Compose mounts durable `php_secrets` volume for Halite keys; PRODUCTION.md documents encrypt-key backup (`048`)
- Query-string Envelope auth (`beacon_key` / `beacon_secret`) is deprecated: still accepted with `Deprecation` + `Warning` response headers and a server log (`049`)
- `/health/ready` returns generic `error: unavailable` on failure (exception detail stays in logs only) (`050`)
- `ProcessEnvelopeHandler` re-checks ingest suspend and daily quota after HTTP ACK and drops queued envelopes when blocked (`051`)
- Ingest always requires a non-empty API secret (`hash_equals`); public key documented as opaque non-secret id (`052`)

### Changed

- Magic-link sign-in is available only when Administration → Mailer has an encrypted, non-null DSN; otherwise `/login/magic` redirects to password login and the login-page link is hidden (env `MAILER_DSN` alone does not enable it)
- **Send test** notification samples are channel-native (Slack attachments, Discord embeds, Teams facts, richer Telegram/email text, HTTP canonical JSON with stub issue) and can deliver to disabled destinations
- Administration → **Mailer**: DSN validation (reject invalid / `null://`) plus **Send sample email** to verify magic-login credentials

### Fixed

- Account → Security password generator control and suggested-password modal use Beacon styling (no Bootstrap/Tailwind kit leftovers)

## [0.12.1] - 2026-07-21

### Added

- Administration → **Mailer**: store Symfony `MAILER_DSN` encrypted in `instance_settings` (Halite / doctrine-encrypt-bundle) with optional From address; runtime mailer prefers DB over env fallback (`034-encrypted-mailer-dsn`)
- Developer manual **[Adding a UI language](dev/ADDING-LOCALES.md)** (enable locales, catalogues, security, seeders, tests)
- Account → Display: **font scale**, **contrast**, and **sidebar** default preferences (theme boot + CSS)
- Admin → Users / Groups: AuditKit **created / updated** timestamps and **created by / updated by** meta
- Account → Security: password **generator** (PasswordStrength modal) and **password-change history** dates from `password_history`
- Account → Profile: richer overview (avatar/roles, UUID, member since, last activity, password changed, projects, groups)
- ROADMAP **Phase 6** (ops/product backlog) plus a **security hardening** track from the platform review (`045`–`054`)

### Changed

- Magic login and email notification delivery read Mailer DSN/From from instance settings (`ConfiguredMailer`); `.env` `MAILER_DSN` remains bootstrap/fallback only (`null://null` by default)
- Password-policy flash messages use structured toast title/body overrides (`NowoPasswordPolicyBundle.*.yaml`)
- Filter UIs always expose **Clear filters**; dashboard project list keeps search / filter / clear / new-project on one toolbar
- Batch-load Doctrine associations on several admin, issue, notification, and dashboard paths (fewer N+1 queries)
- SECURITY.md: operators configure Mailer via Administration (encrypted), not only env

## [0.12.0] - 2026-07-21

### Added

- Project Settings **threshold alerts**: per-project rolling error/fatal volume rules with optional environment/release filters, cooldown, `volume.threshold` notification category, and functional coverage for fire-once cooldown behaviour (`027-threshold-alerts`)
- Project Settings **delivery history**: last N attempts per notification destination (success/fail, truncated error), pruned via `BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT` (default 20), with expandable Health UI (`030-delivery-history`)
- Project **Release health** panel at `/projects/{uuid}/releases`: pick a release to see new-issue counts from `firstRelease`, preview matching issues, compare two releases, and deep-link to the existing issue-list environment compare (`028-release-health`)
- Issue list `q` search uses MySQL **FULLTEXT** on title/culprit (BOOLEAN MODE); SQLite/tests keep `LIKE` fallback (`029-issue-fulltext`)
- Admin → Project show now includes a filterable **audit timeline** backed by `user_action` (`context.project_uuid`) with action/date filters, empty state, and functional coverage (`031-admin-project-audit`)

### Fixed

- Issues **saved views**: **Save view** button height aligned with adjacent inputs / filter controls

## [0.11.1] - 2026-07-21

### Added

- Project role **viewer** (read-only Issues / Performance / Analytics; no triage or Settings mutations) (`026-magic-links-viewer`)
- Passwordless **magic login** via Symfony Security `login_link` at `/login/magic` (10-minute, single-use links; rate-limited; respects disabled accounts)
- Project Settings **share links**: time-limited signed URLs for project or issue read-only access (session viewer grant; revocable)
- Golden Envelope **contract fixtures** + `EnvelopeGoldenContractTest` mirrored from BeaconBundle (Phase 3.6); `make check-envelope-goldens`

### Changed

- Issue mutations (status, assignee, comments, priority, duplicate, saved views) require at least **member** (`requireTriage`)
- Group-linked project roles may include **viewer** (owner still direct-only)
- PHPUnit SQLite DB moved to `var/cache/test/phpunit.db` (more reliable wipe between tests)

## [0.11.0] - 2026-07-21

### Added

- Project **Analytics charts**: Chart.js time series with period presets (7/14/30/90), custom UTC date range (max 366 days), and filters (environment / release / level); table remains with zero-filled days (`025-analytics-charts`)
- Account → Display: **UI density** (comfortable / compact) and **motion** (system / reduce / full)
- Administration → Appearance: **danger / error** colors for light and dark themes (`--beacon-alert`)
- Header theme toggle persists day/night to the account (`POST /account/theme`) when signed in
- Shared server-side table pagination (`PagePagination` + `shared/_table_pagination.html.twig`) for project Issues, Performance, and Analytics lists (`page` / `per_page` query params)
- UI locales **German** (`de`), **Dutch** (`nl`), **French** (`fr`), **Italian** (`it`), and **Portuguese** (`pt`) alongside `en` / `es` (catalogues, AuthKit paths, cookie consent, breadcrumb/menu seeders)

### Changed

- Display preferences intro / i18n cover density and motion; site appearance form includes danger color pickers
- Performance and Analytics list tables use the shared paginator (no DataTables paging; Issues already server-side, now shares the same partial)
- PHPUnit `DatabaseWebTestCase` resets SQLite by deleting `var/test.db` (plus rate-limiter cache) each test for isolation; `memory_limit` 512M; `SiteAppearanceProvider` implements `ResetInterface`

## [0.10.2] - 2026-07-21

### Added

- Phase 5 backlog draft specs: threshold alerts (`027`), release health (`028`), issue FULLTEXT (`029`), delivery history (`030`), admin project audit timeline (`031`), monthly quota (`032`), CI coverage report (`033`); ROADMAP Phase 5 high / medium / later tables

### Changed

- Confirm / form modals share one visual system with kit Bootstrap modals (header / content / footer chrome, 32rem default width, 36rem wide, shared backdrop and shadow)
- Issue mark-duplicate, new-project, admin delete, and Settings dialogs aligned to that modal chrome; kit admin modal widths/backdrop matched
- Roadmap / ARCHITECTURE / README: Phase 5 backlog pointers (`025`–`033`); related specs (`016`/`018`/`019`/`021`/`022`) note deferred follow-ups

### Fixed

- CS / Rector tidy on OpenAPI attributes and a few Project / Health / Notifications call sites (no behaviour change)
- Functional tests: mark-as-duplicate CSRF selector targets the confirm-dialog form; dashboard asserts `dialog.confirm-dialog` (dropped `--form` modifier)
- Native tests updated for removed Hotwire Native (`/config/*` → 404; no `page-shell--native`)
- LoginThrottleTest: unique email per run; assert lock on the 5th failure (`max_attempts: 5`)

## [0.10.1] - 2026-07-21

### Added

- Issue detail sidebar panel ids for **Triage**, **Duplicate**, and **Activity** (account Display collapsed-panel prefs)
- Combobox Stimulus controller for searchable pickers (used by mark-as-duplicate)
- Phase 5 specs (not implemented yet): `025-analytics-charts`, `026-magic-links-viewer`

### Changed

- Issue detail aside: split the overloaded Assignee card into **Triage** (status + priority), **Assignee**, **Duplicate**, and **Activity**
- Mark-as-duplicate: open a modal with autocomplete canonical-issue search (optional merge-events checkbox); modal layout keeps Cancel / Submit visible
- Administration sidebar seeder syncs existing menu item position / label / permission on `app:seed-demo` (ensures **Projects** appears under Administration after upgrade)
- Roadmap / README / ARCHITECTURE: document Analytics table limits and Phase 5 Next (`025` / `026`)

### Fixed

- CS: import `InvalidArgumentException` in `IssueController` (merge path)

## [0.10.0] - 2026-07-21

### Added

- Issues list: tag / request URL / event-user filters; 24h / 7d / 30d occurrence sorts are SQL-backed with correct pagination (`016-issue-search`)
- Notification **quiet hours** and **digest** flush (`020-notification-digest`): per-destination window/timezone, `notification_digest_buffer`, `app:notifications:flush-digests` (PagerDuty not included)
- Project Settings / Admin project **Health** panel (`021-project-health-ui`): Messenger async pending + last delivery status per destination
- Analytics and Performance **access** functional tests in the default PHPUnit / CI suite (`022-analytics-perf-ci`)
- Project export (`017-export-webhooks`): owner/admin `GET /projects/{uuid}/export/issues.{csv,json}` and `events.{csv,json}` with issue-list filters (1,000-row cap; CSV streamed)
- Lifecycle notification categories: `issue.resolved`, `issue.reopened`, `issue.assigned`, `issue.commented`, `issue.duplicated` (opt-in on destinations; dispatched from issue status/assign/comment/duplicate)
- Project Settings → **Governance**: per-project retention, max events, ingest rate limit, and daily event quota (empty inherits env); approaching-quota warning at 80% (`018-project-governance`)
- Project Settings → API keys: **Revoke** and **Rotate** (hard cutover; audit `project.api_key_revoked` / `project.api_key_rotated`)
- Administration → Projects: ops stats (open issues, events last 7d, last ingest), **Suspend/Resume ingest**, and **View as member** (`019-admin-projects-ops`)
- Env `BEACON_EVENT_QUOTA_DAILY` (default 0 = unlimited); Envelope returns `403 ingest disabled` when suspended and `429` when daily quota exceeded
- Issue workflow (`015-issue-workflow`): priority (`low`/`medium`/`high`/`critical`, default medium), plain-text comments, mark-as-duplicate (link + ignored), optional **merge events** into the canonical issue (recomputes counts / seen / release fields), and per-user saved issue list views
- Issues: denormalized `firstRelease` / `lastRelease` / `lastEnvironment` from ingest; filter by release; “New in release” badge; compare issues across two environments (`014-releases`)
- Companion docs for BeaconBundle Phase 3.3–3.4: EVENT-CONTEXT / DSN cross-links for **tags**, **before_send**, and **Doctrine/HttpClient spans** (`023-client-tags-scrubbing`, `024-client-spans`)
- Event detail Tags panel: distinct **Client tags** (`payload.tags`) vs system tags

### Changed

- Issues list occurrence window sorts (24h / 7d / 30d) run in SQL via correlated event counts; the controller no longer fetches all matches to sort in PHP

### Fixed

- Event detail Tags: release label no longer errors when `releaseVersion` is set but payload has no `release` key

## [0.9.4] - 2026-07-21

### Added

- Administration → **Projects**: list/search all projects, create/edit, manage direct members and group links, delete with typed confirmation (instance `ROLE_ADMIN` gets effective owner access on every project)

### Fixed

- Confirm dialogs: portal `<dialog>` to `document.body` on open (not on connect) so Stimulus targets stay valid until `showModal()`

## [0.9.3] - 2026-07-21

### Fixed

- Confirm dialogs (including **Transfer ownership**) open reliably: portal `<dialog>` to `document.body` so `.panel` isolation / overflow cannot trap `showModal()`

## [0.9.2] - 2026-07-21

### Added

- Project Settings → Danger zone: **Transfer ownership** to another direct member (former owner becomes admin; requires typing the project name)

## [0.9.1] - 2026-07-21

### Added

- Administration → Groups: list linked projects and **unlink** a group from a project
- Administration → Users → Activity: list direct project memberships and **remove** a user from a project (last owner still protected)
- Demo breadcrumbs for admin group routes (`admin_groups`, `_new`, `_show`, `_edit`)

### Changed

- Instance `ROLE_ADMIN` may manage project memberships and group links without being a project member (`ProjectMembershipManager`)

### Fixed

- Twig CS spacing in dashboard menu kit override (`dashboard/index.html.twig`)
- `PublicUuidListener`: drop redundant `is_object` check before `method_exists`

## [0.9.0] - 2026-07-21

### Added

- Password history + expiry via [`nowo-tech/password-policy-bundle`](https://packagist.org/packages/nowo-tech/password-policy-bundle) (`password_history` table, `password_changed_at` on `app_user`; account security form validates reuse)
- Account enable/disable, last activity, and online presence via [`nowo-tech/user-kit-bundle`](https://packagist.org/packages/nowo-tech/user-kit-bundle) (admin users table)
- Automatic timestamps + blame fields via [`nowo-tech/audit-kit-bundle`](https://packagist.org/packages/nowo-tech/audit-kit-bundle) on `User`, `Project`, `SiteAppearance`, and `NotificationDestination`
- Field encryption at rest via [`nowo-tech/doctrine-encrypt-bundle`](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) (Halite; API key secrets + notification webhook URLs)
- Declarative Doctrine migrations via [`nowo-tech/migrations-kit-bundle`](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) (MDK definitions; existing versions rewritten in place)
- Account Display issue-panel defaults via [`nowo-tech/tag-input-bundle`](https://packagist.org/packages/nowo-tech/tag-input-bundle) (Tagify whitelist of panel ids)
- Issue assignment & status history (`issue_history`): record assignee changes and resolve/reopen/ignore (including ingest reopen)
- Public `uuid` columns (UUID v7) on Project, Issue, PerfTransaction, NotificationDestination, and User for opaque UI URLs
- Project Settings membership management: add existing users by email with owner/admin/member roles, change role, remove (guards for last owner; admins cannot manage owners)
- User **groups** (`user_group`): admin CRUD + members; projects can link groups with admin/member role so all group users gain access (owners stay direct users)
- Administration → Users: create accounts, change instance role (User/Admin), enable/disable (UserKit)
- User **activity history** (`user_action`): admin timeline of user/group/project membership actions and explicit product actions; per-user page at `/admin/users/{uuid}/activity`
- Project notifications: Discord, Microsoft Teams, Telegram (`bot_token@chat_id`), and email (Symfony Mailer) destinations alongside Slack / HTTP
- OpenAPI / Swagger UI in the Panel shell (`/api/doc`, `/api/doc.json`) via NelmioApiDocBundle (`specs/013-api-docs-panel`)
- Shared login-throttle DB table `login_attempts` for multi-worker FrankenPHP / multi-pod deployments
- GitHub community files: issue templates, PR template, `CODEOWNERS`, root [`SECURITY.md`](../SECURITY.md)
- Dev tooling: [`nowo-tech/composer-update-helper`](https://packagist.org/packages/nowo-tech/composer-update-helper) (`make composer-outdated`)
- Functional coverage for AuthKit login lockout (`LoginThrottleTest`)

### Changed

- **Breaking (ingest auth):** Envelope auth uses Beacon-native wire names only — header `X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…`, query `beacon_key` / `beacon_secret`. Pair with [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) **≥ 1.5.0**.
- UI routes use public **UUID** path segments (integer PKs remain internal; Envelope ingest `/api/{projectId}` stays numeric)
- Project access resolves the highest role from direct membership **or** linked groups (`ProjectAccessService`)
- App shell: fixed sidebar while scrolling; thinner scrollbars on shell / kit surfaces
- Specs/docs: `004-issues` status UI + history; `003-ingest` / `013-api-docs-panel`; architecture Mermaid + README/ROADMAP/CONTRIBUTING (MDK migrations)

### Fixed

- Kit admin Bootstrap modals (Menus / Breadcrumbs): backdrop no longer covers the dialog (modals portaled to `document.body`)
- Test env: `cache.rate_limiter` uses filesystem adapter so Symfony `login_throttling` state survives KernelBrowser request resets

## [0.8.1] - 2026-07-21

### Added

- Brand assets: beacon mark + light/dark wordmark (`public/brand/`), used in header, auth, favicon, PWA offline/install, and README

### Changed

- Documentation filenames under `docs/` are **UPPERCASE** (`ARCHITECTURE.md`, `DSN.md`, …); cross-links in README, specs, and constitution updated

### Fixed

- Prod Docker image: load `nowo_twig_inspector` config only under `when@dev` (bundle is `require-dev`)
- Twig CS whitespace in kit overrides and issue templates (CI)
- Issue/transaction breadcrumbs: parent “Project” / “Issues” links use `projectId`, not the nested `{id}` (issue or transaction)

## [0.8.0] - 2026-07-20

### Changed

- Product docs and specs no longer reference third-party SaaS SDKs; prefer [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) + Envelope wire protocol ([DSN.md](DSN.md), README, architecture)
- `EnvelopeAuthParser` returns `public_key` / `secret_key` (still accepts historical Envelope auth header / query field names for compatibility)
- Issue UI / CHANGELOG wording: “structured” detail layout (no third-party brand comparisons)

### Added

- Git hygiene: `make hooks`, `make check-no-cursor-coauthor`, and `.githooks` to block Cursor co-author / Made-with trailers ([CONTRIBUTING.md](CONTRIBUTING.md))

## [0.7.2] - 2026-07-20

### Fixed

- PHP-CS-Fixer style across retention, notifications, ingest timestamps, issues, and related tests (CI `php-cs-fixer check`)

## [0.7.1] - 2026-07-20

### Changed

- Documentation: README architecture no longer lists a Hotwire Native module; first-install path uses `make bootstrap`
- CHANGELOG / UPGRADING: clarify that **0.7.0** removed Turbo / UX Native and that demo seed includes N+1 + analytics samples

## [0.7.0] - 2026-07-20

### Added

- Project **notifications**: Slack Incoming Webhook and generic HTTP JSON destinations (Settings UI), async delivery via Messenger (`specs/009-project-notifications`)
- **Retention purge** (`app:retention:purge`) via `BEACON_RETENTION_DAYS` / `BEACON_RETENTION_MAX_EVENTS_PER_PROJECT` (`specs/012-safe-self-hosting`)
- **Ingest rate limit** per project (`BEACON_INGEST_RATE_LIMIT`, HTTP 429)
- Public **health probes** `GET /health/live` and `GET /health/ready` (DB + Messenger queue depth)
- Login throttling via [`nowo-tech/login-throttle-bundle`](https://packagist.org/packages/nowo-tech/login-throttle-bundle)
- Docs: [product roadmap](ROADMAP.md), [notifications](product/NOTIFICATIONS.md), [architecture](ARCHITECTURE.md); expanded [production](PRODUCTION.md)
- Demo bootstrap: `make bootstrap` (migrate + seed); `app:seed-demo` writes `.demo-client.env` for BeaconBundle `make sync-beacon`
- Demo seed samples: performance N+1 (`demo.nplus1.products`) and a 14-day analytics window

### Changed

- Issues list: **server-side** sort and paging (column header links + `per_page`); DataTables only handles responsive column collapse
- Issues list: responsive filter grid and wrap-friendly title/culprit cells
- Issue ingest reopens **ignored** issues to unresolved on a matching event (same as resolved), so regression alerts match the notifications spec

### Removed

- `symfony/ux-turbo` and `symfony/ux-native` (Hotwire Native shell). Prefer the PWA (`nowo-tech/pwa-bundle`); see [NATIVE-MOBILE.md](dev/NATIVE-MOBILE.md)

### Fixed

- Issues list → issue detail navigation (full page loads; DataTables no longer rewrites `history` / client-side paging)

## [0.6.0] - 2026-07-20

### Added

- Issues list: **DataTables** (responsive columns, client-side paging 10/25/50/100) with Beacon-themed controls
- Issues list URL state for refreshable views: `sort`, `dir`, `page`, `per_page` (plus existing filters `q` / level / status / assignee / environment)
- Stack Trace: **Copy path** control copies `abs_path:lineno` (or `filename:lineno`) without toggling the frame

### Changed

- Assignee autocomplete styled for Beacon (Tom Select default CSS disabled; sidebar layout without duplicate label)
- Issues index column sorting is driven by DataTables while the server still applies the initial `sort`/`dir` for the rendered rows

## [0.5.0] - 2026-07-20

### Added

- Issue **assignee**: assign a project member from the issue detail sidebar (Symfony UX Autocomplete); list filter by assignee / unassigned
- Collapsible issue/event detail panels with browser persistence (`localStorage`) and Account → Display defaults for which panels start collapsed
- Stack Trace frames are individually collapsible (first frame open; remaining collapsed); source context (`pre_context` / `context_line` / `post_context`) when the client sends it
- Occurrence stats on issues: total events, first/last seen, and **24h / 7d / 30d** windows

### Changed

- Issue grouping fingerprint uses similarity (normalized messages, exception type + file/function without fragile line numbers); resolved issues reopen on new events
- Issue/event detail layout follows a structured order: hero → Highlights → Stack Trace → Breadcrumbs → HTTP Request → Tags → Contexts → Extra → Raw, with a details sidebar

### Fixed

- Issue/event UI: dark-theme payload was invisible (`bg-ink` + light text); structured message / request / extra / stack / breadcrumbs panels
- Message events render root `stacktrace.frames` (not only `exception.*.stacktrace`)
- Project Settings danger-zone confirm dialogs no longer close immediately on open (same-click backdrop)

## [0.4.0] - 2026-07-20

### Added

- Rich event context: microsecond `event_timestamp` / `received_at`, promoted `php_version` / `symfony_version` / `user_identifier`, structured event detail UI (`docs/product/EVENT-CONTEXT.md`, spec `010-rich-event-context`)
- Event detail UI renders `breadcrumbs.values` from Envelope payloads (BeaconBundle `addBreadcrumb`)
- Project **Settings** (`/projects/{id}/settings`): API keys / DSN, members, danger zone
- Project danger zone: clear history (owner/admin) and delete project with typed name confirmation (owner) — spec `011-project-danger-zone`
- Human-friendly API key labels and public keys (`calm-otter-a3f2…`) with Suggest name control
- Project section nav (Issues / Performance / Analytics / Settings); opening a project lands on Issues
- Symfony UX Native + Turbo for Hotwire Native shells (`docs/dev/NATIVE-MOBILE.md`)
- Public legal pages + cookie consent via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) (`docs/product/LEGAL-AND-COOKIES.md`)
- Main nav / breadcrumbs / forms / PWA via Nowo kits (`dashboard-menu`, `breadcrumb-kit`, `form-kit`, `pwa-bundle`)
- Account preferences split: `/account/profile`, `/account/security`, `/account/display`
- Appearance settings for admins; admin hub at `/admin`
- DSN docs: capability matrix and Docker HTTP ingest notes (`docs/DSN.md`)

### Changed

- Project show URL (`/projects/{id}`) redirects to Issues; configuration moved under Settings
- After creating a project, redirect goes to Settings (DSN copy)
- HTTP Caddy site serves Envelope ingest for `host.docker.internal` / `127.0.0.1` (Docker clients)

### Fixed

- BeaconBundle demo (and other Docker clients) no longer get a fake empty HTTP `200` on `:9081` when Host is not `localhost` — ingest now hits Symfony and Messenger

## [0.3.0] - 2026-07-19

### Added

- AuthKit i18n with `locale_in_path` (`/en/login`, `/es/login`, …), message catalogues (`messages.*`, `NowoAuthKitBundle.*`), and a top-right locale dropdown
- Remember me on login (`remember_me.enabled: true`, 7-day cookie)
- Password show/hide via [`nowo-tech/password-toggle-bundle`](https://packagist.org/packages/nowo-tech/password-toggle-bundle) `2.0.4`
- Password strength policy and live feedback via [`nowo-tech/password-strength-bundle`](https://packagist.org/packages/nowo-tech/password-strength-bundle) `1.3.0` (medium level on registration)
- Convenience redirects: `/`, `/login`, `/register`, `/logout` → default-locale AuthKit paths (`/en/…`)
- Contributing guide section for adding locales (`docs/CONTRIBUTING.md`)

### Changed

- Dashboard home moved from `/` to `/dashboard` (`dashboard_home`); `/` redirects to `/en/login`
- Composer direct dependencies pinned to exact versions (no `^` / `8.1.*` on app requires); `bump-after-update: false`
- Auth layout scroll/overflow so tall registration forms (strength requirements) remain usable

### Fixed

- Auth pages blocked scrolling (`overflow: hidden` on `.page-shell`) when the register form exceeded the viewport

## [0.2.0] - 2026-07-19

### Added

- First-user bootstrap registration via [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) (`registration_mode: first_user_only`, role `ROLE_ADMIN`)
- Tailwind AuthKit template overrides and form theme aligned with the dashboard UI
- Frontend toolchain: TypeScript entry + SCSS components + Tailwind 4 (`assets/styles/tailwind.css` + `app.scss`)
- Vite assets proxied over HTTPS through FrankenPHP/Caddy (`/build` → `vite:5173`) to avoid mixed-content blocks
- Cursor rule preferring Nowo.tech kits and reminding about legal/cookie consent UX
- PHPUnit coverage for AuthKit bootstrap (`AuthKitBootstrapTest`)

### Changed

- Login/logout routes and firewall now use AuthKit (`nowo_auth_kit_login` / `nowo_auth_kit_logout`) with nested `login_form[*]` parameters
- Removed custom `SecurityController` and `templates/security/login.html.twig` in favor of AuthKit
- Compose Vite service always listens on container port `5173`; host maps `VITE_PORT` (default `5174`)
- README quick start documents `/register` (empty DB) as an alternative to `app:seed-demo`

### Fixed

- Tailwind/CSS not loading on `https://localhost:9444` (HTTP Vite URL + Docker port mismatch)

## [0.1.0] - 2026-07-19

### Added

- Initial **symfony-beacon** server (forked from [symfony-frankenphp-boilerplate](https://github.com/nowo-tech/symfony-frankenphp-boilerplate))
- Modular Symfony modules: Identity, Project, Ingest, Issues, Performance, Analytics
- Envelope-compatible ingest (`POST /api/{project_id}/envelope/`) + Messenger async pipeline
- Dashboard with Tailwind (projects, issues, performance/N+1, analytics)
- Project API keys / DSN, memberships (`owner` / `admin` / `member`)
- Demo seed command (`app:seed-demo`) and PHPUnit coverage for parsers, ingest, dashboard access
- Spec-Driven Development layout (`specs/`, constitution, Spec Kit skills)

[Unreleased]: https://github.com/nowo-tech/symfony-beacon/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/nowo-tech/symfony-beacon/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.17.0...v1.0.0
[0.17.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.16.0...v0.17.0
[0.16.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.15.0...v0.16.0
[0.15.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.14.0...v0.15.0
[0.14.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.13.0...v0.14.0
[0.13.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.8...v0.13.0
[0.12.8]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.7...v0.12.8
[0.12.7]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.6...v0.12.7
[0.12.6]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.5...v0.12.6
[0.12.5]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.4...v0.12.5
[0.12.4]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.3...v0.12.4
[0.12.3]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.2...v0.12.3
[0.12.2]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.1...v0.12.2
[0.12.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.0...v0.12.1
[0.12.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.11.1...v0.12.0
[0.11.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.10.2...v0.11.0
[0.10.2]: https://github.com/nowo-tech/symfony-beacon/compare/v0.10.1...v0.10.2
[0.10.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.10.0...v0.10.1
[0.10.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.4...v0.10.0
[0.9.4]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.3...v0.9.4
[0.9.3]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.2...v0.9.3
[0.9.2]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.8.1...v0.9.0
[0.8.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.8.0...v0.8.1
[0.8.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.7.2...v0.8.0
[0.7.2]: https://github.com/nowo-tech/symfony-beacon/compare/v0.7.1...v0.7.2
[0.7.1]: https://github.com/nowo-tech/symfony-beacon/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/nowo-tech/symfony-beacon/releases/tag/v0.1.0
