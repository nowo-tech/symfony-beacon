# Install & seed layers

Beacon separates **schema**, **platform catalogs**, **demo identity**, and **optional sample telemetry**.
Cold-start UI is provided by [`nowo-tech/site-backup-bundle`](https://packagist.org/packages/nowo-tech/site-backup-bundle) **≥ 1.13** at **`/setup`** (ops panel: **`/_site_backup`**). Progress uses **`cache_doctrine`** (Redis `cache.app` + DBAL) — not `var/site-backup/setup-progress.json`. Cookie Consent **≥ 1.8** skips its modal until `dashboard_cookie_config` exists. Per-step journal rows use runtime DDL (no host migration for those tables).

| Layer | Command / Make | Purpose |
|-------|----------------|---------|
| Schema | `doctrine:migrations:migrate` | Database structure |
| Platform | `app:seed-platform` / `make seed-platform` | Menus + breadcrumbs + cookie consent profile/inventory (idempotent; safe after upgrades) |
| Demo | `app:seed-demo` / `make seed` | Local admin + **Symfony Beacon** project (`slug=symfony-beacon`) + `.demo-client.env` (**mode 600**) + optional server `BEACON_DSN` |
| Dogfood | `make dogfood` (`app:seed-demo --skip-demo-user --sync-server-dsn`) | Same project + **re-wires** server `BEACON_DSN` **without** creating `admin@symfony-beacon.local`; grants Symfony Beacon access to **every existing `ROLE_ADMIN`** (preferred owner / `.demo-client.env` login hint = **earliest registered** admin). Does **not** bind to a hard-coded personal email. |
| Sample | `app:seed-sample` / `make seed-sample` | QA/load issues & charts (`dev` / `load` / `huge`); also enables Mercure with env defaults (see [MERCURE.md](ops/MERCURE.md)) |
| Ready | `make ready` | `bootstrap` + `seed` — recommended first local run |
| Setup UI | `/setup` | SiteBackup wizard (bootstrap choice, migrations, platform seed, admin / optional sample, or full SQL dump) |

## Fresh install

```bash
cp .env.dist .env.local
# Set SiteBackup secrets in .env.local (do not commit them):
#   SITE_SETUP_TOKEN=$(openssl rand -hex 24)
#   docker compose exec php bin/console nowo:site-backup:hash-password
#   → paste into SITE_BACKUP_PASSWORD_HASH
# Outside dev/test, empty or historically known local values fail closed (docs/PRODUCTION.md).
make up
make ready              # migrate + platform + demo + dogfood BEACON_DSN when empty
# or step by step:
# make bootstrap        # migrate + platform seed + setup.done marker
# make seed             # optional: demo user + project + DSN
make seed-sample        # optional: PROFILE=dev samples
# Optional local SMTP catcher (not production):
# make mailpit          # Mailpit UI + smtp://mailpit:1025 (shared) / smtp://mailer:1025 — see docs/ops/MAILPIT.md
#
# Later: make test / make phpstan / make shell auto-call `make ensure-up`
# (starts Compose if php is down — no rebuild / no Vite). Use `make up` for first boot.
```

After `make ready`, restart PHP if `BEACON_DSN` was just written (`make restart`) so the Kernel picks up dogfooding. Empty `BEACON_DSN` disables self-reporting.

**Before exposing the HTTP(S) port beyond localhost:** finish `/setup` and/or the first `/register` (first user becomes `ROLE_ADMIN`). Keep `SYMFONY_TRUSTED_PROXIES` empty unless an outer load balancer terminates TLS — see [PRODUCTION.md](PRODUCTION.md) operational inventory. If `.demo-client.env` already exists from an older seed, run `chmod 600 .demo-client.env`.

To exercise magic login, password reset, or email notifications locally, start **Mailpit** (`make mailpit`), save `smtp://mailpit:1025` (shared `server/` catcher) or `smtp://mailer:1025` (app-local profile) under **Administration → Mailer**, then use **Send sample email**. Details: [MAILPIT.md](ops/MAILPIT.md). Do not run Mailpit in production.

Alternatively open the app with a missing or empty database: the FrankenPHP entrypoint only waits for the MySQL **server** (it does **not** create the schema or run migrations). The SiteBackup gate redirects to **`/setup`**. Open **`/setup?token=$SITE_SETUP_TOKEN`**. Choose **guided** (`database_create`, migrations, Messenger transports, `app:seed-platform`, first `ROLE_ADMIN`, optional sample) or **full database** (SQL dump import then migrations / seed). Deep-link: `/setup?token=…&profile=full_database`.

Headless equivalent:

```bash
docker compose exec -T php bin/console nowo:site-backup:setup --profile=fresh_install --no-interaction \
  --admin-email=ops@example.com --admin-password='…'
```

After setup, signed-in users get a one-time **product tour** on the dashboard (and later on project Issues / Administration when those pages are first opened). Tours respect role and project permissions; finish or close to dismiss — replay from Account → Display.

See also [README](../README.md) and [quickstart](../specs/055-install-seed-layers/quickstart.md).

## Upgrade

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
# Ensure SiteBackup does not re-open the gate on existing instances:
mkdir -p var/site-backup && touch var/site-backup/setup.done
# or: make seed-platform
make vite-build
```

Do **not** rely on `app:seed-demo` only to fix missing admin menu items — use platform seed.

## Sample sizes

```bash
make seed-sample                    # dev
PROFILE=load make seed-sample
docker compose exec -T php bin/console app:seed-sample --size=huge --force
docker compose exec -T php bin/console app:seed-sample --purge --project=demo
```

Purge removes issues/events/performance/stats for the target project; the project and API keys remain.

## Backup / restore panel + setup token

| Secret | Purpose | `.env.dist` | Production |
|--------|---------|-------------|------------|
| `SITE_SETUP_TOKEN` | Gate `/setup` (`?token=` or `X-Setup-Token`) | empty (set in `.env`) | **Required unique** — empty / known-local values refused at boot |
| `SITE_BACKUP_PASSWORD_HASH` | Password for `/_site_backup` | empty (generate into `.env`) | **Required unique** — empty / known-local hashes refused at boot |

```bash
# Generate secrets into .env (never commit .env)
openssl rand -hex 24   # → SITE_SETUP_TOKEN=
docker compose exec php bin/console nowo:site-backup:hash-password
# → SITE_BACKUP_PASSWORD_HASH='…'
```

Docs: [SiteBackupBundle](https://github.com/nowo-tech/SiteBackupBundle/tree/main/docs), [PRODUCTION.md](PRODUCTION.md).
