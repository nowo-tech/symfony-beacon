# CLI contracts: seed layers

## `app:seed-platform`

```text
php bin/console app:seed-platform
```

| Aspect | Contract |
|--------|----------|
| Side effects | Upsert menus + breadcrumbs + cookie consent profile + permission catalog (`project.*` via `InstanceRbacSeeder`); purge leftover `admin.*` permission rows; remove legacy operator InstanceRoles if present |
| Exit 0 | Catalogs present/updated |
| Idempotent | Yes |
| Forbidden | Creating users, projects, issues, events, perf, analytics samples |

## `app:seed-demo`

```text
php bin/console app:seed-demo
  [--email=…] [--password=…]
  [--base-url=…] [--ingest-base-url=…]
  [--write-client-env[=path]]
  [--with-platform]
  [--skip-demo-user]
  [--sync-server-dsn]
```

| Aspect | Contract |
|--------|----------|
| Side effects | Dogfood project (`slug=symfony-beacon`, legacy `demo` upgraded) + API key; optional demo user unless `--skip-demo-user`; optional `.demo-client.env`; optional platform if `--with-platform`; may write server `BEACON_DSN` when empty; `--sync-server-dsn` re-wires `BEACON_DSN` even when already set |
| Does not | Seed analytics / performance / issue volumes |
| Idempotent | Create-once for user/project/key |

## `app:seed-sample`

```text
php bin/console app:seed-sample
  [--size=dev|load|huge]      # default dev (not --profile: reserved by Symfony Console)
  [--project=symfony-beacon]  # slug (legacy `demo` accepted / upgraded)
  [--purge]                   # delete telemetry for project
  [--force]                   # required for size=huge
```

| Size | Approx issues | Approx events |
|------|---------------|---------------|
| `dev` | 40 | 200 |
| `load` | 2_000 | 10_000 |
| `huge` | 20_000 | 100_000 |

Also ensures light perf N+1 + analytics window appropriate to size (see research.md).

## Make targets

| Target | Runs |
|--------|------|
| `make ensure-halite-secrets` | `mkdir -p var/secrets` in `php` (Halite key parent dir) |
| `make bootstrap` | `ensure-halite-secrets` + `migrate -n` + `app:seed-platform` |
| `make seed-platform` | `ensure-halite-secrets` + `app:seed-platform` |
| `make seed` | `seed-platform` + `app:seed-demo` |
| `make dogfood` | `ensure-halite-secrets` + `app:seed-demo --skip-demo-user --sync-server-dsn` |
| `make seed-sample` | `ensure-halite-secrets` + `app:seed-sample` (`PROFILE` env → `--size`, default `dev`) |

## Compatibility

- Keep command name `app:seed-demo` (docs/scripts already use it).
- Alias note in help text: prefer `make seed-platform` after upgrades.
- Dogfood project slug is `symfony-beacon` (stable name **Symfony Beacon**).
