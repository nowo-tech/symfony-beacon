# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/symfony-beacon/compare/v0.9.0...HEAD
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
