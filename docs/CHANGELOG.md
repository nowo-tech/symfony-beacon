# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

- Database schema documentation with Mermaid ER diagrams: [DATABASE.md](DATABASE.md)
- Platform seed (`app:seed-platform` / Setup platform step) also seeds cookie consent profile + inventory (`CookieConsentDemoSeeder`); `use_database_config: true`

### Changed

- Setup wizard (`/setup`) is public **before the first user** with two one-click presets (minimum vs full sample load); after users exist, only `ROLE_ADMIN` (login links to setup while bootstrap is open)
- Local Compose MySQL data uses host bind mount `./.data/mysql` (gitignored) instead of a named Docker volume
- Professional cookie-consent copy (modal, inventory, legal page, category labels)

### Fixed

- Fresh-install migrations: re-introspect before dropping `uniq_event_id`; only stamp `setup_completed_at` when users exist; clear setup flag when no users; idempotent AuditKit indexes on concurrent migrate (`Version20260721195000`)

## [0.12.6] - 2026-07-21

### Added

- Mercure hub (Compose + Caddy `/.well-known/mercure`) for **optional** live member alerts when a **new issue** is created on an associated project — enable under **Administration → Mercure** (off by default; URL/JWT from UI or `MERCURE_*` env); operator manual [MERCURE.md](MERCURE.md)
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
- Developer manual **[Adding a UI language](ADDING-LOCALES.md)** (enable locales, catalogues, security, seeders, tests)
- Account → Display: **font scale**, **contrast**, and **sidebar** default preferences (theme boot + CSS)
- Admin → Users / Groups: AuditKit **created / updated** timestamps and **created by / updated by** meta
- Account → Security: password **generator** (PasswordStrength modal) and **password-change history** dates from `password_history`
- Account → Profile: richer overview (avatar/roles, UUID, member since, last activity, password changed, projects, groups)
- ROADMAP **Phase 6** (ops/product backlog) plus a **security hardening** track from the platform review (`045`–`054`)

### Changed

- Magic login and email notification delivery read Mailer DSN/From from instance settings (`ConfiguredMailer`); `.env` `MAILER_DSN` remains bootstrap/fallback only (`null://null` by default)
- Password-policy flash messages use structured toast title/body overrides (`PasswordPolicyBundle.*.yaml`)
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
- Docs: [product roadmap](ROADMAP.md), [notifications](NOTIFICATIONS.md), [architecture](ARCHITECTURE.md); expanded [production](PRODUCTION.md)
- Demo bootstrap: `make bootstrap` (migrate + seed); `app:seed-demo` writes `.demo-client.env` for BeaconBundle `make sync-beacon`
- Demo seed samples: performance N+1 (`demo.nplus1.products`) and a 14-day analytics window

### Changed

- Issues list: **server-side** sort and paging (column header links + `per_page`); DataTables only handles responsive column collapse
- Issues list: responsive filter grid and wrap-friendly title/culprit cells
- Issue ingest reopens **ignored** issues to unresolved on a matching event (same as resolved), so regression alerts match the notifications spec

### Removed

- `symfony/ux-turbo` and `symfony/ux-native` (Hotwire Native shell). Prefer the PWA (`nowo-tech/pwa-bundle`); see [NATIVE-MOBILE.md](NATIVE-MOBILE.md)

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

- Rich event context: microsecond `event_timestamp` / `received_at`, promoted `php_version` / `symfony_version` / `user_identifier`, structured event detail UI (`docs/EVENT-CONTEXT.md`, spec `010-rich-event-context`)
- Event detail UI renders `breadcrumbs.values` from Envelope payloads (BeaconBundle `addBreadcrumb`)
- Project **Settings** (`/projects/{id}/settings`): API keys / DSN, members, danger zone
- Project danger zone: clear history (owner/admin) and delete project with typed name confirmation (owner) — spec `011-project-danger-zone`
- Human-friendly API key labels and public keys (`calm-otter-a3f2…`) with Suggest name control
- Project section nav (Issues / Performance / Analytics / Settings); opening a project lands on Issues
- Symfony UX Native + Turbo for Hotwire Native shells (`docs/NATIVE-MOBILE.md`)
- Public legal pages + cookie consent via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) (`docs/LEGAL-AND-COOKIES.md`)
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

[Unreleased]: https://github.com/nowo-tech/symfony-beacon/compare/v0.12.8...HEAD
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
