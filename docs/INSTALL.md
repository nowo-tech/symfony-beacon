# Install & seed layers

Beacon separates **schema**, **platform catalogs**, **demo identity**, and **optional sample telemetry**.

| Layer | Command / Make | Purpose |
|-------|----------------|---------|
| Schema | `doctrine:migrations:migrate` | Database structure |
| Platform | `app:seed-platform` / `make seed-platform` | Menus + breadcrumbs (idempotent; safe after upgrades) |
| Demo | `app:seed-demo` / `make seed` | Local admin + `demo` project + `.demo-client.env` |
| Sample | `app:seed-sample` / `make seed-sample` | QA/load issues & charts (`dev` / `load` / `huge`) |

## Fresh install

```bash
cp .env.dist .env
make up
make bootstrap          # migrate + platform seed
make seed               # optional: demo user + project
make seed-sample        # optional: PROFILE=dev samples
```

Or register the first admin at `/en/register` after `make bootstrap` (no demo seed required).

Admins can also use the **Setup** wizard at `/setup` (banner on the dashboard until marked complete).

See also [README](../README.md) and [quickstart](../specs/055-install-seed-layers/quickstart.md).

## Upgrade

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
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
