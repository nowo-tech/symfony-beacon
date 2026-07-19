# Upgrading Guide

This guide helps you upgrade between versions of **symfony-beacon**.

## Table of contents

- [Upgrading from 0.1.0 to the next release](#upgrading-from-010-to-the-next-release)
- [First install (no previous version)](#first-install-no-previous-version)

---

## Upgrading from 0.1.0 to the next release

No further release yet. When upgrading past `v0.1.0`:

1. Read the new section in [`CHANGELOG.md`](CHANGELOG.md).
2. Diff env templates: compare your local `.env` against the new `.env.dist` and add any missing keys.
3. Rebuild containers after Docker/FrankenPHP/Node changes: `make down && make build && make up`.
4. Run migrations: `make console ARGS='doctrine:migrations:migrate -n'`.
5. Run quality checks: `make qa` (or at least `make test`).

### Stack versions (0.1.0)

| Component | Constraint / image | Notes |
|---|---|---|
| PHP | `>=8.5` / `dunglas/frankenphp:1-php8.5` | Canonical image line |
| Symfony | `8.1.*` | Application framework |
| MySQL | Compose service (see `compose.yaml`) | Default host port `3308` |
| Vite / Tailwind | `pentatrion/vite-bundle` ^8.2 | Frontend assets |

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
make console ARGS='app:seed-demo'
```

Then open https://localhost:9444/ (or http://localhost:9081/).

Demo login: `admin@symfony-beacon.local` / `admin123`

### Breaking expectations for consumers

This is a **self-hosted application**, not a Composer library. “Upgrading” usually means:

- **Pulling** tagged releases into your deployment clone, or
- **Rebasing / cherry-picking** upstream changes into your fork.

There is no `composer update nowo-tech/symfony-beacon` path for application code.

### Env file policy

- Only `.env.dist` is versioned.
- Do not commit `.env`, `.env.dev`, or `.env.local`.
- After pulling upstream changes, always merge new keys from `.env.dist` into your local `.env`.
