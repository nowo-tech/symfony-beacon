# Upgrading Guide

This guide helps you upgrade between versions of **symfony-beacon**.

## Table of contents

- [Upgrading from 0.3.0 to the next release](#upgrading-from-030-to-the-next-release)
- [Upgrading from 0.2.0 to 0.3.0](#upgrading-from-020-to-030)
- [Upgrading from 0.1.0 to 0.2.0](#upgrading-from-010-to-020)
- [First install (no previous version)](#first-install-no-previous-version)

---

## Upgrading from 0.3.0 to the next release

No further release yet. When upgrading past `v0.3.0`:

1. Read the new section in [`CHANGELOG.md`](CHANGELOG.md).
2. Diff env templates: compare your local `.env` against the new `.env.dist` and add any missing keys.
3. Rebuild containers after Docker/FrankenPHP/Node changes: `make down && make build && make up`.
4. Run migrations: `make console ARGS='doctrine:migrations:migrate -n'`.
5. Run quality checks: `make qa` (or at least `make test`).

### Stack versions (0.3.0)

| Component | Constraint / image | Notes |
|---|---|---|
| PHP | `>=8.5` / `dunglas/frankenphp:1-php8.5` | Canonical image line |
| Symfony | `8.1.*` (Flex) / exact pins in `composer.json` | Application framework |
| MySQL | Compose service (see `compose.yaml`) | Default host port `3308` |
| Auth | `nowo-tech/auth-kit-bundle` `1.5.1` | First-user registration + i18n |
| Password UX | `password-toggle-bundle` `2.0.4`, `password-strength-bundle` `1.3.0` | Toggle + medium strength on register |
| Vite / Tailwind / SCSS | `pentatrion/vite-bundle` `8.2.4`, Tailwind 4, Sass | Assets via HTTPS `/build` proxy |

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
