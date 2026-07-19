# Upgrading Guide

This guide helps you upgrade between versions of **symfony-beacon**.

## Table of contents

- [Upgrading from 0.2.0 to the next release](#upgrading-from-020-to-the-next-release)
- [Upgrading from 0.1.0 to 0.2.0](#upgrading-from-010-to-020)
- [First install (no previous version)](#first-install-no-previous-version)

---

## Upgrading from 0.2.0 to the next release

No further release yet. When upgrading past `v0.2.0`:

1. Read the new section in [`CHANGELOG.md`](CHANGELOG.md).
2. Diff env templates: compare your local `.env` against the new `.env.dist` and add any missing keys.
3. Rebuild containers after Docker/FrankenPHP/Node changes: `make down && make build && make up`.
4. Run migrations: `make console ARGS='doctrine:migrations:migrate -n'`.
5. Run quality checks: `make qa` (or at least `make test`).

### Stack versions (0.2.0)

| Component | Constraint / image | Notes |
|---|---|---|
| PHP | `>=8.5` / `dunglas/frankenphp:1-php8.5` | Canonical image line |
| Symfony | `8.1.*` | Application framework |
| MySQL | Compose service (see `compose.yaml`) | Default host port `3308` |
| Auth | `nowo-tech/auth-kit-bundle` ^1.5 | First-user registration |
| Vite / Tailwind / SCSS | `pentatrion/vite-bundle` ^8.2, Tailwind 4, Sass | Assets via HTTPS `/build` proxy |

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

- Login/logout route names are now `nowo_auth_kit_login` / `nowo_auth_kit_logout` (paths remain `/login` and `/logout`).
- `form_login` uses nested fields: `login_form[_username]`, `login_form[_password]`, `login_form[_csrf_token]`.
- Custom `App\Identity\Controller\SecurityController` was removed — use AuthKit + Twig overrides under `templates/bundles/NowoAuthKitBundle/`.
- Empty databases can bootstrap via **https://localhost:9444/register** (first user only, `ROLE_ADMIN`). After any user exists, `/register` redirects to login.
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
# Open https://localhost:9444/login — Tailwind UI should load
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

- Register the first admin at https://localhost:9444/register, or
- Seed demo data: `make console ARGS='app:seed-demo'` (login `admin@symfony-beacon.local` / `admin123`)

Open https://localhost:9444/ (or http://localhost:9081/).

### Breaking expectations for consumers

This is a **self-hosted application**, not a Composer library. “Upgrading” usually means:

- **Pulling** tagged releases into your deployment clone, or
- **Rebasing / cherry-picking** upstream changes into your fork.

There is no `composer update nowo-tech/symfony-beacon` path for application code.

### Env file policy

- Only `.env.dist` is versioned.
- Do not commit `.env`, `.env.dev`, or `.env.local`.
- After pulling upstream changes, always merge new keys from `.env.dist` into your local `.env`.
