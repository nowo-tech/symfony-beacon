# Install & seed layers

Beacon separates **schema**, **platform catalogs**, **demo identity**, and **optional sample telemetry**.

| Layer | Command / Make | Purpose |
|-------|----------------|---------|
| Schema | `doctrine:migrations:migrate` | Database structure |
| Platform | `app:seed-platform` / `make seed-platform` | Menus + breadcrumbs + cookie consent profile/inventory (idempotent; safe after upgrades) |
| Demo | `app:seed-demo` / `make seed` | Local admin + `demo` project + `.demo-client.env` |
| Sample | `app:seed-sample` / `make seed-sample` | QA/load issues & charts (`dev` / `load` / `huge`); also enables Mercure with env defaults (see [MERCURE.md](MERCURE.md)) |

## Fresh install

```bash
cp .env.dist .env
make up
make bootstrap          # migrate + platform seed
make seed               # optional: demo user + project
make seed-sample        # optional: PROFILE=dev samples
```

Or open **`/setup` before login** when the database has no users yet — choose **Minimum** (platform + demo admin) or **Full sample load** (minimum + `load` telemetry), then sign in with `admin@symfony-beacon.local` / `admin123`. Alternatively register the first admin at `/en/register`.

Once users exist, only `ROLE_ADMIN` can reopen `/setup` (dashboard banner until marked complete).

After setup, signed-in users get a one-time **product tour** on the dashboard (and later on project Issues / Administration when those pages are first opened). Tours respect role and project permissions; finish or close to dismiss — replay from Account → Display.

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
