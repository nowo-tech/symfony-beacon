# Install & seed layers

Beacon separates **schema**, **platform catalogs**, **demo identity**, and **optional sample telemetry**.
Cold-start UI is provided by [`nowo-tech/site-backup-bundle`](https://packagist.org/packages/nowo-tech/site-backup-bundle) at **`/setup`** (ops panel: **`/_site_backup`**).

| Layer | Command / Make | Purpose |
|-------|----------------|---------|
| Schema | `doctrine:migrations:migrate` | Database structure |
| Platform | `app:seed-platform` / `make seed-platform` | Menus + breadcrumbs + cookie consent profile/inventory (idempotent; safe after upgrades) |
| Demo | `app:seed-demo` / `make seed` | Local admin + **Symfony Beacon** project (`slug=symfony-beacon`) + `.demo-client.env` + optional server `BEACON_DSN` |
| Dogfood | `make dogfood` (`app:seed-demo --skip-demo-user`) | Same project/DSN wiring **without** creating `admin@symfony-beacon.local`; grants Symfony Beacon access to existing `ROLE_ADMIN` users |
| Sample | `app:seed-sample` / `make seed-sample` | QA/load issues & charts (`dev` / `load` / `huge`); also enables Mercure with env defaults (see [MERCURE.md](ops/MERCURE.md)) |
| Ready | `make ready` | `bootstrap` + `seed` — recommended first local run |
| Setup UI | `/setup` | SiteBackup wizard (bootstrap choice, migrations, platform seed, admin / optional sample, or full SQL dump) |

## Fresh install

```bash
cp .env.dist .env
# Local defaults: SITE_SETUP_TOKEN=beacon-local-setup
#                 SITE_BACKUP_PASSWORD_HASH → password "beacon-local-panel"
# Rotate both before any internet-facing deploy (see docs/PRODUCTION.md).
make up
make ready              # migrate + platform + demo + dogfood BEACON_DSN when empty
# or step by step:
# make bootstrap        # migrate + platform seed + setup.done marker
# make seed             # optional: demo user + project + DSN
make seed-sample        # optional: PROFILE=dev samples
# Optional local SMTP catcher (not production):
# make mailpit          # Mailpit UI + smtp://mailer:1025 — see docs/ops/MAILPIT.md
```

After `make ready`, restart PHP if `BEACON_DSN` was just written (`make restart`) so the Kernel picks up dogfooding. Empty `BEACON_DSN` disables self-reporting.

To exercise magic login, password reset, or email notifications locally, start **Mailpit** (`make mailpit`), save `smtp://mailer:1025` under **Administration → Mailer**, then use **Send sample email**. Details: [MAILPIT.md](ops/MAILPIT.md). Do not run Mailpit in production.

Alternatively open the app with an empty (or catalog-less) database: the SiteBackup gate redirects to **`/setup`**. Open **`/setup?token=$SITE_SETUP_TOKEN`** (local default `beacon-local-setup`). Choose **guided** (migrations, `app:seed-platform`, first `ROLE_ADMIN`, optional sample) or **full database** (SQL dump import then migrations / seed). Deep-link: `/setup?token=…&profile=full_database`.

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

| Secret | Purpose | Local default (`.env.dist`) | Production |
|--------|---------|-------------------------------|------------|
| `SITE_SETUP_TOKEN` | Gate `/setup` (`?token=` or `X-Setup-Token`) | `beacon-local-setup` | **Required unique** — empty/local default refused at boot |
| `SITE_BACKUP_PASSWORD_HASH` | Password for `/_site_backup` | hash of `beacon-local-panel` | **Required unique** — local default refused at boot |

```bash
# Generate a panel password hash
docker compose exec php bin/console nowo:site-backup:hash-password
# Put the hash in SITE_BACKUP_PASSWORD_HASH and a random SITE_SETUP_TOKEN in .env
```

Docs: [SiteBackupBundle](https://github.com/nowo-tech/SiteBackupBundle/tree/main/docs), [PRODUCTION.md](PRODUCTION.md).
