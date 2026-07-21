# Symfony Beacon — self-hosted error tracking for PHP & Symfony

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="public/brand/logo-dark.jpg">
    <img src="public/brand/logo-light.jpg" alt="symfony-beacon" width="480">
  </picture>
</p>

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Self-hosted error tracking focused on **PHP / Symfony**. Compatible with the **Envelope wire protocol**, so clients send events to this server via a project DSN — no SaaS account required.

Built on **Symfony 8.1**, **FrankenPHP** (classic/worker), **MySQL 9.7**, **Messenger**, **AuthKit**, **Vite + TypeScript + SCSS + Tailwind 4**, and **Spec-Driven Development** (GitHub Spec Kit).

> The Symfony instrumentation **bundle** is [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) (separate repository). Configure `BEACON_DSN` against this server (any host/port). Install from Packagist or VCS as documented in that repo.


## Features

- Dashboard login with project-scoped memberships (`owner` / `admin` / `member` / `viewer`)
- Password or **magic login** / **password reset** via AuthKit (`/login/magic`, `/reset-password`) — only when Administration → Mailer has a deliverable encrypted DSN; project **share links** for time-limited viewer access (project-wide or issue-scoped)
- **First-user registration** via [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) (`registration_mode: first_user_only`)
- Login brute-force protection via [`nowo-tech/login-throttle-bundle`](https://packagist.org/packages/nowo-tech/login-throttle-bundle) (5 attempts / 15 minutes on AuthKit `main`)
- **i18n** UI locales: `en`, `es`, `de`, `nl`, `fr`, `it`, `pt`; AuthKit/setup dual URLs (bare for `DEFAULT_LOCALE`, prefixed for others — see [ADDING-LOCALES.md](docs/ADDING-LOCALES.md)); remember me; password toggle + strength; password history/expiry via [`nowo-tech/password-policy-bundle`](https://packagist.org/packages/nowo-tech/password-policy-bundle)
- Account enable/disable + online presence via [`nowo-tech/user-kit-bundle`](https://packagist.org/packages/nowo-tech/user-kit-bundle); audit timestamps/blame via [`nowo-tech/audit-kit-bundle`](https://packagist.org/packages/nowo-tech/audit-kit-bundle)
- Sensitive fields encrypted at rest via [`nowo-tech/doctrine-encrypt-bundle`](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) (API key secrets, notification webhook URLs, push subscriptions, **instance Mailer DSN/From**, **Mercure URL/JWT**)
- **Administration → Mailer** (`/settings/mailer`): encrypted Symfony Mailer DSN + From; **Send sample email**; gates magic login
- **Administration → Mercure** (`/settings/mercure`): optional live new-issue toasts (hub + JWT); see [docs/MERCURE.md](docs/MERCURE.md)
- Declarative Doctrine migrations via [`nowo-tech/migrations-kit-bundle`](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) (MDK + `migrations/FieldDictionary/`)
- Account Display: theme, density, motion, font scale, contrast, sidebar, collapsed-panel prefs via [`nowo-tech/tag-input-bundle`](https://packagist.org/packages/nowo-tech/tag-input-bundle) (Tagify); **product tours** (Select all) + optional **Web Push**; PWA install
- Install seed layers + **Setup** wizard (`/setup` for `DEFAULT_LOCALE`; auto-redirect when catalogs are empty; public bootstrap when no users yet); contextual **product tour** (driver.js) on first dashboard / project Issues / admin visit
- Projects with rotatable / revocable **API keys** and Envelope-compatible **DSN** (human-friendly key names in Settings)
- Project **Settings**: API keys, members, **governance** (retention / rate / daily quota), **notification destinations** (Slack / Discord / Teams / Telegram / email / HTTP; quiet hours + digests + thresholds), **health** (Messenger + delivery history), and danger zone (clear history, **transfer ownership**, delete)
- Issue list with filters (level, status, environment, **release**, assignee, tag, URL, user), **priority**, similarity fingerprint, SQL-backed 24h / 7d / 30d windows, **FULLTEXT** search, **saved views**, **CSV/JSON export**, and a **DataTables** responsive table (server-side sort + page in the URL)
- Issue detail: structured layout, collapsible panels, stack source context + copy path, breadcrumbs, request/tags/contexts, **assignee**, **priority**, **comments**, **mark duplicate** (optional event merge), **resolve/reopen/ignore**, and **assignment & status history**
- `POST /api/{project_id}/envelope/` ingest (`X-Beacon-Auth` / envelope `dsn`; query auth **deprecated**); per-project suspend + daily quota; secret always required
- Fast ACK + async processing (Messenger); Docker clients can ingest over HTTP `:9081` (`host.docker.internal`)
- Daily **analytics** at `/projects/{uuid}/analytics`: Chart.js series, period presets / custom UTC range, env/release/level filters, plus zero-filled daily table (`025-analytics-charts`)
- **Release health** at `/projects/{uuid}/releases` (new-in-release counts + compare)
- Operator **OpenAPI** panel at `/api/doc` (Nelmio) — see [docs/API.md](docs/API.md)
- Phase 5+ product depth: **threshold alerts**, **delivery history**, admin **project audit** timeline, **encrypted Mailer**, **security hardening** (`045`–`052`) — see [ROADMAP](docs/ROADMAP.md) (Phase 6 Next: ops overview, identity audit, Identity kit polish, monthly quota; SSO Later)
- Project notifications (Slack, Discord, Teams, Telegram, email, generic HTTP JSON) including **lifecycle** categories and channel-native **Send test** — [docs/NOTIFICATIONS.md](docs/NOTIFICATIONS.md)
- Retention purge, ingest rate limits, `/health/live` + `/health/ready`
- Performance transactions/spans with **N+1** detection (`/projects/{uuid}/performance`, filter `?nplus1=1`)
- Main nav via [`nowo-tech/dashboard-menu-bundle`](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) (admin at `/admin/menus`, Beacon shell layout)
- Breadcrumbs via [`nowo-tech/breadcrumb-kit-bundle`](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) (admin at `/breadcrumb-kit-admin`, Beacon shell layout)
- Forms via [`nowo-tech/form-kit-bundle`](https://packagist.org/packages/nowo-tech/form-kit-bundle) (Tailwind / Beacon theme)
- Progressive Web App via [`nowo-tech/pwa-bundle`](https://packagist.org/packages/nowo-tech/pwa-bundle) (manifest, service worker, install prompt); **optional** member alerts for new issues — Mercure live toasts via **Administration → Mercure** ([docs/MERCURE.md](docs/MERCURE.md)), Web Push via **Account → Display** ([docs/NOTIFICATIONS.md](docs/NOTIFICATIONS.md))
- Brand: beacon mark + wordmarks under `public/brand/`; UI typeface **Montserrat**
- **Appearance** settings for `ROLE_ADMIN` (brand name + accent colors) at `/settings/appearance`
- Public **legal** pages + GDPR cookie consent via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) — see [docs/LEGAL-AND-COOKIES.md](docs/LEGAL-AND-COOKIES.md)
- App shell: avatar switches among Preferences / Dashboard / Administration; each area has its own sidebar menu
- Account preferences at `/account/profile`, `/account/security`, `/account/display`
- Admin hub at `/admin` for `ROLE_ADMIN` (users, groups, **projects** with ops stats / suspend ingest / view-as-member, Mailer, Mercure, appearance, menus, breadcrumbs); unlink projects from users (Activity) and groups (group detail)

Membership roles: **owner** / **admin** / **member** / **viewer** (read-only). Auth is password (+ remember-me) or **magic login** (`/login/magic`); SSO is Later.

## Requirements

- Docker + Docker Compose
- Canonical stack: PHP 8.5 via `dunglas/frankenphp:1-php8.5`, Symfony 8.1.*

## Quick start

```bash
git clone https://github.com/nowo-tech/symfony-beacon.git
cd symfony-beacon
cp .env.dist .env
make up          # starts stack + builds frontend into public/build/
make bootstrap   # migrate + platform menus/breadcrumbs
make seed        # optional: demo admin + project + .demo-client.env
# Optional QA samples: make seed-sample
# Option A — register the first admin in the UI: https://localhost:9444/en/register
# Option B — demo login after make seed (see below)
```

- HTTP: http://localhost:9081  
- HTTPS: https://localhost:9444  
- MySQL: `localhost:3308`
- Demo login (after seed): `admin@symfony-beacon.local` / `admin123`
- After seed, open Performance with N+1 filter: `/projects/1/performance?nplus1=1` (transaction `demo.nplus1.products`)
- After seed, open Analytics: `/projects/1/analytics` (14 days of error / transaction / N+1 counters)
- First-user registration (empty DB only): https://localhost:9444/register
- Login: https://localhost:9444/login (serves `DEFAULT_LOCALE`; other languages via `/en/login`, …; **Remember me**; header language switcher)
- OpenAPI (after login): https://localhost:9444/api/doc

> After the first user exists, `/register` redirects to login. Auth/setup: bare paths for `DEFAULT_LOCALE`, prefixed for other locales (setup redirects `/es/setup` → `/setup` when default is `es`). Legal bare paths redirect to `/{DEFAULT_LOCALE}/legal/…`. **`.env.dist` ships `DEFAULT_LOCALE=en`; this project's `.env` uses `es`.** After sign-in the app home is **`/dashboard`** with language from the account preference (no `_locale` in dashboard URLs).

Seed prints DSNs and writes `.demo-client.env` for the [BeaconBundle](https://github.com/nowo-tech/BeaconBundle) FrankenPHP demo:

```text
UI DSN: https://<public_key>@localhost:9444/<project_id>
Client DSN (Docker): http://<public_key>@host.docker.internal:9081/<project_id>
```

In `BeaconBundle/demo/symfony8`, `make up` / `make sync-beacon` copies that Client DSN into `BEACON_DSN` so `/exception` can ingest directly.

## FrankenPHP worker

```bash
make worker   # FRANKENPHP_MODE=worker
make classic  # per-request boot
```

Application code is written for worker safety (`ResetInterface` when needed). See [docs/FRANKENPHP-CODING.md](docs/FRANKENPHP-CODING.md).

## Architecture

Modular Symfony (not full DDD). **Why this shape** and **Mermaid flows:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). **Tables / ER diagrams:** [docs/DATABASE.md](docs/DATABASE.md).

| Module | Responsibility |
|--------|----------------|
| `Identity` | Users (AuthKit login/register), account prefs, magic login, seed command |
| `Project` | Projects, API keys, memberships (`viewer` + share links), Settings / danger zone |
| `Ingest` | Envelope API + async pipeline |
| `Issues` | Grouping, list/filter, FULLTEXT, assignee, status + history, event detail |
| `Performance` | Transactions, spans, N+1 |
| `Analytics` | Daily aggregates + charts/filters (`025`); table + Chart.js |
| `Notifications` | Slack / Discord / Teams / Telegram / email / HTTP; digests, thresholds, delivery history |
| `Shared` | Appearance, menus/breadcrumbs glue, legal pages, instance Mailer / Mercure |

## Spec-Driven Development

Specs live under `specs/`. Constitution: `.specify/memory/constitution.md`.

## Tests

```bash
make test
# or
docker compose exec php php bin/phpunit
```

## Documentation

- [Install & seed layers](docs/INSTALL.md)
- [Architecture rationale](docs/ARCHITECTURE.md)
- [Database schema (Mermaid ER)](docs/DATABASE.md)
- [HTTP API overview](docs/API.md)
- [Product roadmap](docs/ROADMAP.md)
- [Project notifications](docs/NOTIFICATIONS.md)
- [Mercure (live alerts, JWT)](docs/MERCURE.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release checklist](docs/RELEASE.md)
- [Security policy](SECURITY.md)
- [DSN / SDK](docs/DSN.md)
- [Event context (timestamps, versions, user)](docs/EVENT-CONTEXT.md)
- [Mobile / PWA (Hotwire Native removed)](docs/NATIVE-MOBILE.md)
- [Legal pages & cookie consent](docs/LEGAL-AND-COOKIES.md)
- [Adding a UI language](docs/ADDING-LOCALES.md)
- [Production](docs/PRODUCTION.md)
- [FrankenPHP coding (worker safety)](docs/FRANKENPHP-CODING.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Funding](docs/FUNDING.md)

## License

MIT — see [LICENSE](LICENSE).
