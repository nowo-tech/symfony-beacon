# Symfony Beacon — self-hosted error tracking for PHP & Symfony

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Self-hosted error tracking focused on **PHP / Symfony**. Compatible with the **Envelope wire protocol**, so you can point the official PHP SDK (`sentry/sentry` on Packagist) at this server via a project DSN — no SaaS account required.

Built on **Symfony 8.1**, **FrankenPHP** (classic/worker), **MySQL 9.7**, **Messenger**, **AuthKit**, **Vite + TypeScript + SCSS + Tailwind 4**, and **Spec-Driven Development** (GitHub Spec Kit).

> The Symfony instrumentation **bundle** is [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) (separate repository). Configure `BEACON_DSN` against this server (any host/port). Until Packagist publish, path-repo / VCS install works; you can also point `sentry/sentry` at the same DSN.


## Features

- Dashboard login with project-scoped memberships (`owner` / `admin` / `member`)
- **First-user registration** via [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) (`registration_mode: first_user_only`)
- **i18n** auth routes (`/en/…`, `/es/…`), remember me, password toggle + strength on register
- Projects with rotatable **API keys** and Envelope-compatible **DSN** (human-friendly key names in Settings)
- Project **Settings** with API keys, members, **notification destinations** (Slack / HTTP), and danger zone
- Issue list with filters, **assignee**, similarity fingerprint, 24h / 7d / 30d windows, and a **DataTables** responsive/paginated table (sort + page in the URL)
- Issue detail: Sentry-style layout, collapsible panels, stack source context + copy path, breadcrumbs, request/tags/contexts
- `POST /api/{project_id}/envelope/` ingest (auth via `X-Sentry-Auth` / query / envelope `dsn`)
- Fast ACK + async processing (Messenger); Docker clients can ingest over HTTP `:9081` (`host.docker.internal`)
- Daily analytics (errors, transactions, N+1 counts)
- Project notifications (Slack Incoming Webhook + generic HTTP JSON)
- Retention purge, ingest rate limits, `/health/live` + `/health/ready`
- Performance transactions/spans with **N+1** detection (`/projects/{id}/performance`, filter `?nplus1=1`)
- Main nav via [`nowo-tech/dashboard-menu-bundle`](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) (admin at `/admin/menus`, Beacon shell layout)
- Breadcrumbs via [`nowo-tech/breadcrumb-kit-bundle`](https://packagist.org/packages/nowo-tech/breadcrumb-kit-bundle) (admin at `/breadcrumb-kit-admin`, Beacon shell layout)
- Forms via [`nowo-tech/form-kit-bundle`](https://packagist.org/packages/nowo-tech/form-kit-bundle) (Tailwind / Beacon theme)
- Progressive Web App via [`nowo-tech/pwa-bundle`](https://packagist.org/packages/nowo-tech/pwa-bundle) (manifest, service worker, install prompt)
- **Appearance** settings for `ROLE_ADMIN` (brand name + accent colors) at `/settings/appearance`
- Public **legal** pages + GDPR cookie consent via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) — see [docs/legal-and-cookies.md](docs/legal-and-cookies.md)
- App shell: avatar switches among Preferences / Dashboard / Administration; each area has its own sidebar menu
- Account preferences at `/account/profile`, `/account/security`, `/account/display` (including default collapsed issue panels)
- Admin hub at `/admin` for `ROLE_ADMIN` (users, appearance, menus, breadcrumbs)

## Requirements

- Docker + Docker Compose
- Canonical stack: PHP 8.5 via `dunglas/frankenphp:1-php8.5`, Symfony 8.1.*

## Quick start

```bash
git clone https://github.com/nowo-tech/symfony-beacon.git
cd symfony-beacon
cp .env.dist .env
make up          # starts stack + builds frontend into public/build/
make console ARGS='doctrine:migrations:migrate -n'
# Optional live CSS/JS reload: make vite-hmr  (stop it + make vite-build when done)
# Option A — register the first admin in the UI: https://localhost:9444/en/register
# Option B — seed demo user + project + DSN + N+1 performance samples:
make seed
```

- HTTP: http://localhost:9081  
- HTTPS: https://localhost:9444  
- MySQL: `localhost:3308`
- Demo login (after seed): `admin@symfony-beacon.local` / `admin123`
- After seed, open Performance with N+1 filter: `/projects/1/performance?nplus1=1` (transaction `demo.nplus1.products`)
- After seed, open Analytics: `/projects/1/analytics` (14 days of error / transaction / N+1 counters)
- First-user registration (empty DB only): https://localhost:9444/en/register (Spanish: `/es/register`)
- Login: https://localhost:9444/en/login (includes **Remember me**)

> After the first user exists, `/en/register` redirects to `/en/login`. Auth routes use `/{_locale}` (`en` default). Bare `/`, `/login`, `/register`, and `/logout` redirect to the English AuthKit paths. After sign-in, the app home is **`/dashboard`**.

Seed prints a DSN like:

```text
https://<public_key>@localhost:9444/<project_id>
```

Use that DSN with the PHP SDK package `sentry/sentry` (host/port must match how the SDK reaches this server).

## FrankenPHP worker

```bash
make worker   # FRANKENPHP_MODE=worker
make classic  # per-request boot
```

Application code is written for worker safety (`ResetInterface` when needed). See [docs/frankenphp-coding.md](docs/frankenphp-coding.md).

## Architecture

Modular Symfony (not full DDD). **Why this shape** and **Mermaid flows:** [docs/architecture.md](docs/architecture.md).

| Module | Responsibility |
|--------|----------------|
| `Identity` | Users (AuthKit login/register), account prefs, seed command |
| `Project` | Projects, API keys, memberships, Settings / danger zone |
| `Ingest` | Envelope API + async pipeline |
| `Issues` | Grouping, list/filter, assignee, event detail |
| `Performance` | Transactions, spans, N+1 |
| `Analytics` | Daily aggregates |
| `Notifications` | Slack / HTTP webhook destinations |
| `Native` | Hotwire Native config (`/config/*_v1.json`) |
| `Shared` | Appearance, menus/breadcrumbs glue, legal pages |

## Spec-Driven Development

Specs live under `specs/`. Constitution: `.specify/memory/constitution.md`.

## Tests

```bash
make test
# or
docker compose exec php php bin/phpunit
```

## Documentation

- [Architecture rationale](docs/architecture.md)
- [Product roadmap](docs/ROADMAP.md)
- [Project notifications](docs/notifications.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release checklist](docs/RELEASE.md)
- [DSN / SDK](docs/dsn.md)
- [Event context (timestamps, versions, user)](docs/event-context.md)
- [Native mobile — create iOS/Android apps (Hotwire Native)](docs/native-mobile.md)
- [Legal pages & cookie consent](docs/legal-and-cookies.md)
- [Production](docs/production.md)
- [Contributing](docs/CONTRIBUTING.md)

## License

MIT — see [LICENSE](LICENSE).
