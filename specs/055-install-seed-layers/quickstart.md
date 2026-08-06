# Quickstart: Install & Seed Layers

## Prerequisites

- Stack up: `make up`
- Empty or existing MySQL from Compose

## Fresh install (platform only)

```bash
make bootstrap
# → ensure-halite-secrets + migrations + app:seed-platform
```

Then either:

- Open cold-start UI: `https://localhost:9444/setup` ([SiteBackupBundle](https://packagist.org/packages/nowo-tech/site-backup-bundle); see `056`), or
- Register first admin: `https://localhost:9444/register` (AuthKit; locale-prefixed variants also work), or
- `make seed` for demo admin + Symfony Beacon project + `.demo-client.env`

Expected: Administration sidebar and breadcrumbs work after login; no sample issues until sample seed.

## Local demo + light samples

```bash
make bootstrap
make seed
make seed-sample          # PROFILE=dev
# or: docker compose exec -T php bin/console app:seed-sample --size=dev
```

Expected:

- Login `admin@symfony-beacon.local` / `admin123`
- Project slug `symfony-beacon` (legacy `demo` is upgraded on seed)
- Issues list has dozens of rows; Analytics has a multi-day series; Performance `?nplus1=1` shows demo N+1

## Dogfood server DSN only

```bash
make dogfood
# → ensure-halite-secrets + app:seed-demo --skip-demo-user
# restart php if BEACON_DSN was written
```

Expected: Symfony Beacon project + API key for existing admins; server `BEACON_DSN` loopback when previously empty (`058`).

## Upgrade navigation only

```bash
docker compose exec -T php bin/console doctrine:migrations:migrate -n
make seed-platform
```

Expected: new menu items (e.g. Mailer) appear without recreating demo user.

## Purge sample telemetry

```bash
docker compose exec -T php bin/console app:seed-sample --purge --project=symfony-beacon
```

Expected: project remains; issues/events/perf/stats for that project gone; menus unchanged.

## Load / huge (QA)

```bash
docker compose exec -T php bin/console app:seed-sample --size=load
docker compose exec -T php bin/console app:seed-sample --size=huge --force
```

## Verify tests

```bash
docker compose exec -T php vendor/bin/phpunit tests/Integration/Shared/SeedPlatformCommandTest.php tests/Integration/Identity/SeedDemoCommandTest.php tests/Integration/Shared/SeedSampleCommandTest.php
```
