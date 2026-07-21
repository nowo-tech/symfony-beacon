# Quickstart: Install & Seed Layers

## Prerequisites

- Stack up: `make up`
- Empty or existing MySQL from Compose

## Fresh install (platform only)

```bash
make bootstrap
# → migrations + app:seed-platform
```

Then either:

- Register first admin: `https://localhost:9444/en/register`, or
- `make seed` for demo admin + project + `.demo-client.env`

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
- Issues list has dozens of rows; Analytics has a multi-day series; Performance `?nplus1=1` shows demo N+1

## Upgrade navigation only

```bash
docker compose exec -T php bin/console doctrine:migrations:migrate -n
make seed-platform
```

Expected: new menu items (e.g. Mailer) appear without recreating demo user.

## Purge sample telemetry

```bash
docker compose exec -T php bin/console app:seed-sample --purge --project=demo
```

Expected: demo project remains; issues/events/perf/stats for that project gone; menus unchanged.

## Load / huge (QA)

```bash
docker compose exec -T php bin/console app:seed-sample --size=load
docker compose exec -T php bin/console app:seed-sample --size=huge --force
```

## Verify tests

```bash
docker compose exec -T php vendor/bin/phpunit tests/Shared/SeedPlatformCommandTest.php tests/Identity/SeedDemoCommandTest.php tests/Shared/SeedSampleCommandTest.php
```
