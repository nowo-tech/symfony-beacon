# Symfony Beacon — self-hosted error tracking for PHP & Symfony

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Self-hosted error tracking focused on **PHP / Symfony**. Compatible with the **Envelope wire protocol**, so you can point the official PHP SDK (`sentry/sentry` on Packagist) at this server via a project DSN — no SaaS account required.

Built on **Symfony 8.1**, **FrankenPHP** (classic/worker), **MySQL 9.7**, **Messenger**, **AuthKit**, **Vite + TypeScript + SCSS + Tailwind 4**, and **Spec-Driven Development** (GitHub Spec Kit).

> The Symfony instrumentation **bundle** lives in a separate repository and will be published later. Until then, configure the official PHP SDK DSN against this server.

## Features (v1)

- Dashboard login with project-scoped memberships (`owner` / `admin` / `member`)
- **First-user registration** via [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) (`registration_mode: first_user_only`)
- Projects with rotatable **API keys** and Envelope-compatible **DSN**
- `POST /api/{project_id}/envelope/` ingest (auth via `X-Sentry-Auth` / query / envelope `dsn`)
- Fast ACK + async processing (Messenger)
- Issue grouping (fingerprint), filters, event detail + stack trace
- Daily analytics (errors, transactions, N+1 counts)
- Performance transactions/spans with **N+1** detection

## Requirements

- Docker + Docker Compose
- Canonical stack: PHP 8.5 via `dunglas/frankenphp:1-php8.5`, Symfony 8.1.*

## Quick start

```bash
git clone https://github.com/nowo-tech/symfony-beacon.git
cd symfony-beacon
cp .env.dist .env
make up
make console ARGS='doctrine:migrations:migrate -n'
# Option A — register the first admin in the UI: https://localhost:9444/register
# Option B — seed demo user + project + DSN:
make console ARGS='app:seed-demo'
```

- HTTP: http://localhost:9081  
- HTTPS: https://localhost:9444  
- MySQL: `localhost:3308`
- Demo login (after seed): `admin@symfony-beacon.local` / `admin123`
- First-user registration (empty DB only): https://localhost:9444/register

> After the first user exists, `/register` redirects to login.

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

Modular Symfony (not full DDD):

| Module | Responsibility |
|--------|----------------|
| `Identity` | Users (AuthKit login/register), seed command |
| `Project` | Projects, API keys, memberships |
| `Ingest` | Envelope API + async pipeline |
| `Issues` | Grouping, list/filter, event detail |
| `Performance` | Transactions, spans, N+1 |
| `Analytics` | Daily aggregates |

## Spec-Driven Development

Specs live under `specs/`. Constitution: `.specify/memory/constitution.md`.

## Tests

```bash
make test
# or
docker compose exec php php bin/phpunit
```

## Documentation

- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release checklist](docs/RELEASE.md)
- [DSN / SDK](docs/dsn.md)
- [Production](docs/production.md)
- [Contributing](docs/CONTRIBUTING.md)

## License

MIT — see [LICENSE](LICENSE).
