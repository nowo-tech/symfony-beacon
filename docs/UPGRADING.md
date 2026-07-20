# Upgrading Guide

This guide helps you upgrade between versions of **symfony-beacon**.

## Table of contents

- [Upgrading from 0.7.0 to the next release](#upgrading-from-070-to-the-next-release)
- [Upgrading from 0.6.0 to 0.7.0](#upgrading-from-060-to-070)
- [Upgrading from 0.5.0 to 0.6.0](#upgrading-from-050-to-060)
- [Upgrading from 0.4.0 to 0.5.0](#upgrading-from-040-to-050)
- [Upgrading from 0.3.0 to 0.4.0](#upgrading-from-030-to-040)
- [Upgrading from 0.2.0 to 0.3.0](#upgrading-from-020-to-030)
- [Upgrading from 0.1.0 to 0.2.0](#upgrading-from-010-to-020)
- [First install (no previous version)](#first-install-no-previous-version)

---

## Upgrading from 0.7.0 to the next release

When a newer tag exists:

1. Read the new section in [`CHANGELOG.md`](CHANGELOG.md).
2. Diff your `.env` against `.env.dist`.
3. Rebuild containers if Docker/FrankenPHP/Node changed: `make down && make build && make up`.
4. Run migrations: `make console ARGS='doctrine:migrations:migrate -n'`.
5. Rebuild frontend assets if you deploy without Vite HMR: `make vite-build`.
6. Run quality checks: `make qa` (or at least `make test`).

---

## Upgrading from 0.6.0 to 0.7.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.7.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make vite-build
```

### 2. Environment

Diff `.env` against `.env.dist` and add if missing:

| Key | Role |
|---|---|
| `BEACON_RETENTION_DAYS` | Retention purge age (`0` = off) |
| `BEACON_RETENTION_MAX_EVENTS_PER_PROJECT` | Cap events per project (`0` = off) |
| `BEACON_INGEST_RATE_LIMIT` | Per-project ingest rate (HTTP 429 when exceeded) |

### 3. Database

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

Adds `notification_destination` (and related schema from `Version20260720233000`).

### 4. Issues list behaviour

Sort and paging are **server-side** again (column header links + `per_page` in the filter form). DataTables only collapses columns on narrow viewports. Existing bookmarks with `sort` / `dir` / `page` / `per_page` keep working.

### 5. Ops / product features

- Project → Settings → **Notifications** (Slack / HTTP): [notifications.md](notifications.md)
- Optional cron: `app:retention:purge`
- Probes: `/health/live`, `/health/ready` — [production.md](production.md)
- Login throttling defaults: see `config/packages/nowo_login_throttle.yaml`

### 6. Local BeaconBundle demo (optional)

```bash
make bootstrap   # migrate + seed + write .demo-client.env
```

Then in `BeaconBundle/demo/symfony8`: `make sync-beacon` (see [dsn.md](dsn.md)).

### 7. Verify

```bash
make qa
# or
make test
curl -fsS http://localhost:9081/health/live
```

### Stack versions (0.7.0)

| Component | Constraint / image | Notes |
|---|---|---|
| PHP | `>=8.5` / `dunglas/frankenphp:1-php8.5` | Canonical image line |
| Symfony | `8.1.*` (Flex) / exact pins in `composer.json` | Application framework |
| MySQL | Compose service (see `compose.yaml`) | Default host port `3308` |
| Auth | `nowo-tech/auth-kit-bundle` | First-user registration + i18n |
| Login throttle | `nowo-tech/login-throttle-bundle` | Brute-force protection |
| Cookies / legal | `nowo-tech/cookie-consent-bundle` | Consent modal + legal pages |
| Menus / breadcrumbs / forms / PWA | Nowo kit bundles | See README Features |
| Autocomplete | `symfony/ux-autocomplete` | Issue assignee field |
| Issues table | DataTables 2 + Responsive (+ jQuery) | Responsive only; sort/page server-side |
| Vite / Tailwind / SCSS | Tailwind 4, Sass, Stimulus | Assets via HTTPS `/build` proxy |

---

## Upgrading from 0.5.0 to 0.6.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.6.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install   # pulls DataTables / jQuery
make vite-build
```

No new Doctrine migrations in 0.6.0.

### 2. Frontend (required)

`pnpm install` + `make vite-build` are required: the issues index now depends on DataTables assets baked into `public/build/`.

### 3. URL / bookmark notes

Issues list query params:

| Param | Role |
|---|---|
| `q`, `level`, `status`, `assignee`, `environment` | Server-side filters (GET form) |
| `sort`, `dir` | Initial sort (server) + kept in sync by DataTables |
| `page`, `per_page` | DataTables paging (`per_page` ∈ 10/25/50/100) |

Example: `/projects/1/issues?q=demo&sort=last_seen&dir=desc&page=1&per_page=25`

### 4. Verify

```bash
make test
# /projects/{id}/issues → paging, responsive collapse, sort updates the URL
# Issue detail → Stack Trace → Copy path
```

---

## Upgrading from 0.4.0 to 0.5.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.5.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make console ARGS='doctrine:migrations:migrate -n'
make vite-build   # required when Vite dev server is not used in the deploy
```

### 2. Database migrations (required)

New columns:

| Migration | Change |
|---|---|
| `Version20260720214500` | `issue.assignee_id` (nullable FK → `app_user`) |
| `Version20260720223000` | `app_user.preferred_collapsed_issue_panels` (JSON) |

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

### 3. Behaviour notes (non-breaking for operators)

- **Fingerprints** are recalculated for new events only; existing issues keep their stored fingerprint. Similar new events may join an existing group more often (line numbers no longer dominate).
- **Assignee** is optional; members appear via `/autocomplete/project_member` (requires login).
- **Panel collapse** defaults live under Account → Display; browsers also store open/closed state in `localStorage` (`beacon.issuePanelState`).
- Stack **source context** appears when the client (e.g. BeaconBundle ≥ 1.3.0) sends `pre_context` / `context_line` / `post_context`.

### 4. Client pairing

For full stack source snippets in the UI, upgrade the PHP client to **BeaconBundle `v1.3.0+`** (or another SDK that sends frame source context).

### 5. Verify

```bash
make test
# Issue list → assign filter; open issue → assignee autocomplete + collapsible stack frames
# Account → Display → default collapsed panels
```

---

## Upgrading from 0.3.0 to 0.4.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.4.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make console ARGS='doctrine:migrations:migrate -n'
make console ARGS='assets:install public -n'
make seed   # refreshes breadcrumb/menu demos if needed
```

### 2. Database migrations (required)

New tables/columns include cookie-consent storage, dashboard menu / breadcrumb kit tables, appearance, and rich event fields (`php_version`, `symfony_version`, `user_identifier`, `DATETIME(6)` timestamps). Always run:

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

### 3. Project URLs (bookmarks)

| Before (0.3.0) | After (0.4.0) |
|---|---|
| `/projects/{id}` (API keys + members) | Redirects to **`/projects/{id}/issues`** |
| — | Settings: **`/projects/{id}/settings`** (keys, members, clear/delete) |

Update any hard-coded links that expected the old project overview page.

### 4. Docker / Envelope ingest

Caddy now serves **`/api/*` over HTTP** for `host.docker.internal` and `127.0.0.1` (in addition to redirecting browsers from `http://localhost` to HTTPS). Restart PHP after pulling so the Caddyfile reloads:

```bash
docker compose restart php
```

BeaconBundle demos should use:

```env
BEACON_DSN=http://PUBLIC_KEY@host.docker.internal:9081/1
```

See [`dsn.md`](dsn.md).

### 5. Legal / cookies

Review public legal placeholders under `/legal/*` and cookie categories in `config/packages/nowo_cookie_consent.yaml`. Operators must replace placeholder operator text before production.

### 6. Verify

```bash
make test
# https://localhost:9444/dashboard → open a project → Issues
# Project Settings → API keys / Danger zone
# From BeaconBundle demo: http://localhost:8011/report → issue appears
```

---

## Upgrading from 0.2.0 to 0.3.0

### 1. Pull and refresh dependencies

```bash
git fetch --tags
git checkout v0.3.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
```

Composer **require** entries are now exact versions (e.g. `1.5.1`, not `^1.5`). Prefer `composer install` from the lock file; bump pins deliberately when upgrading packages.

### 2. Routes and URLs (breaking bookmarks)

| Before (0.2.0) | After (0.3.0) |
|---|---|
| `/` (dashboard when logged in) | `/` → redirect to `/en/login`; dashboard at **`/dashboard`** |
| `/login`, `/register` (no locale) | Prefer **`/en/login`**, **`/en/register`** (bare paths redirect to `/en/…`) |
| — | Spanish: `/es/login`, `/es/register` |

Update bookmarks, reverse proxies, and any hard-coded links to use `/dashboard` and locale-prefixed auth URLs.

### 3. Auth UX packages

- Enable/install assets if needed: `make console ARGS='assets:install public -n'`.
- Registration enforces **medium** password strength (min 8, lower, upper, digit) via PasswordStrengthBundle.
- Login/register password fields include show/hide (PasswordToggleBundle) and strength feedback on register.
- Remember-me checkbox is available on login (7-day cookie). Firewall `remember_me` must remain configured (see `config/packages/security.yaml`).

### 4. i18n

- `framework.enabled_locales` and `nowo_auth_kit.enabled_locales` must stay in sync (`en`, `es` by default).
- Security `access_control` patterns must include every enabled locale prefix (see [`CONTRIBUTING.md`](CONTRIBUTING.md)).
- Locale switcher is a top-right dropdown on AuthKit layouts.

### 5. Verify

```bash
make console ARGS='doctrine:migrations:migrate -n'
make test
# https://localhost:9444/ → /en/login
# https://localhost:9444/en/register (empty DB) or /dashboard when authenticated
```

No database schema migration is required for 0.3.0 auth/i18n changes.

---

## Upgrading from 0.1.0 to 0.2.0

### 1. Pull and refresh dependencies

```bash
git fetch --tags
git checkout v0.2.0   # or merge/rebase main
cp .env.dist .env.dist.upstream && diff -u .env .env.dist.upstream || true
# Merge any new keys from .env.dist into your .env (especially DEFAULT_URI / Vite notes)
make down
make up
docker compose exec php composer install
docker compose exec vite pnpm install
```

### 2. AuthKit (breaking for custom login forks)

- Login/logout route names are now `nowo_auth_kit_login` / `nowo_auth_kit_logout`.
- `form_login` uses nested fields: `login_form[_username]`, `login_form[_password]`, `login_form[_csrf_token]`.
- Custom `App\Identity\Controller\SecurityController` was removed — use AuthKit + Twig overrides under `templates/bundles/NowoAuthKitBundle/`.
- Empty databases can bootstrap via **https://localhost:9444/en/register** (first user only, `ROLE_ADMIN`). After any user exists, register redirects to login.
- Existing users continue to work; no schema migration is required for AuthKit in 0.2.0.

### 3. Frontend / Vite

- Styles entry is TypeScript + SCSS + Tailwind: `assets/app.ts` imports `styles/tailwind.css` and `styles/app.scss`.
- Caddy proxies `/build*` to the Vite container. Ensure `DEFAULT_URI` matches your public HTTPS URL (default `https://localhost:9444`).
- Rebuild/restart so PHP picks up the Caddyfile and Vite listens on **5173** inside Compose:

```bash
make down && make up
```

Hard-refresh the browser if old HTTP Vite URLs were cached.

### 4. Verify

```bash
make console ARGS='doctrine:migrations:migrate -n'
make test
# Open https://localhost:9444/en/login — Tailwind UI should load
```

---

## First install (no previous version)

If you are starting from this project for the first time:

```bash
git clone git@github.com:nowo-tech/symfony-beacon.git
cd symfony-beacon
cp .env.dist .env
git config core.hooksPath .githooks   # optional: strip Cursor co-author trailers
make up
make console ARGS='doctrine:migrations:migrate -n'
```

Then either:

- Register the first admin at https://localhost:9444/en/register, or
- Seed demo data: `make console ARGS='app:seed-demo'` (login `admin@symfony-beacon.local` / `admin123`)

Open https://localhost:9444/ (redirects to `/en/login`). After login you land on `/dashboard`.

### Breaking expectations for consumers

This is a **self-hosted application**, not a Composer library. “Upgrading” usually means:

- **Pulling** tagged releases into your deployment clone, or
- **Rebasing / cherry-picking** upstream changes into your fork.

There is no `composer update nowo-tech/symfony-beacon` path for application code.

### Env file policy

- Only `.env.dist` is versioned.
- Do not commit `.env`, `.env.dev`, or `.env.local`.
- After pulling upstream changes, always merge new keys from `.env.dist` into your local `.env`.
