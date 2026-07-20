# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-07-20

### Added

- Rich event context: microsecond `event_timestamp` / `received_at`, promoted `php_version` / `symfony_version` / `user_identifier`, structured event detail UI (`docs/event-context.md`, spec `010-rich-event-context`)
- Event detail UI renders `breadcrumbs.values` from Envelope payloads (BeaconBundle `addBreadcrumb`)
- Project **Settings** (`/projects/{id}/settings`): API keys / DSN, members, danger zone
- Project danger zone: clear history (owner/admin) and delete project with typed name confirmation (owner) — spec `011-project-danger-zone`
- Human-friendly API key labels and public keys (`calm-otter-a3f2…`) with Suggest name control
- Project section nav (Issues / Performance / Analytics / Settings); opening a project lands on Issues
- Symfony UX Native + Turbo for Hotwire Native shells (`docs/native-mobile.md`)
- Public legal pages + cookie consent via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) (`docs/legal-and-cookies.md`)
- Main nav / breadcrumbs / forms / PWA via Nowo kits (`dashboard-menu`, `breadcrumb-kit`, `form-kit`, `pwa-bundle`)
- Account preferences split: `/account/profile`, `/account/security`, `/account/display`
- Appearance settings for admins; admin hub at `/admin`
- DSN docs: capability matrix and Docker HTTP ingest notes (`docs/dsn.md`)

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

[Unreleased]: https://github.com/nowo-tech/symfony-beacon/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/nowo-tech/symfony-beacon/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/nowo-tech/symfony-beacon/releases/tag/v0.1.0
