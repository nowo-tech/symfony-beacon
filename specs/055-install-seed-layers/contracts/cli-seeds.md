# CLI contracts: seed layers

## `app:seed-platform`

```text
php bin/console app:seed-platform
```

| Aspect | Contract |
|--------|----------|
| Side effects | Upsert menus + breadcrumbs only |
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
```

| Aspect | Contract |
|--------|----------|
| Side effects | Demo user + `slug=demo` project + API key; optional `.demo-client.env`; optional platform if `--with-platform` |
| Does not | Seed analytics / performance / issue volumes |
| Idempotent | Create-once for user/project/key |

## `app:seed-sample`

```text
php bin/console app:seed-sample
  [--size=dev|load|huge]      # default dev (not --profile: reserved by Symfony Console)
  [--project=demo]            # slug
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
| `make bootstrap` | `migrate -n` + `app:seed-platform` |
| `make seed-platform` | `app:seed-platform` |
| `make seed` | `seed-platform` + `app:seed-demo` |
| `make seed-sample` | `app:seed-sample` (`PROFILE` env → `--size`, default `dev`) |

## Compatibility

- Keep command name `app:seed-demo` (docs/scripts already use it).
- Alias note in help text: prefer `make seed-platform` after upgrades.
