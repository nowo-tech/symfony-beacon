# Research: Install & Seed Layers

## Decision: Three commands, not one with flags

- **Decision**: Separate `app:seed-platform`, `app:seed-demo`, `app:seed-sample` instead of a single `app:seed --layer=…`.
- **Rationale**: Operators and Make targets must make production-safe vs demo vs load obvious; UPGRADING can cite one safe command.
- **Alternatives considered**: Single command with required `--layer` (easy to misuse in scripts); only documenting “run seed-demo carefully” (status quo failure mode).

## Decision: No schema migration for sample tagging (MVP)

- **Decision**: Purge sample telemetry by **project scope** (default `slug=demo`, or `--project=`). Keep project, memberships, and API keys. Do not add `is_sample` columns in this feature.
- **Rationale**: Meets FR-004 with project slug marking; avoids migration risk; matches “demo project is the sample sandbox”.
- **Alternatives considered**: `is_sample` boolean on Issue/Event (cleaner multi-tenant samples, deferred); title prefix filter only (fragile).

## Decision: Profile volumes

| Profile | Issues (approx) | Events (approx) | Analytics window | Perf N+1 | Notes |
|---------|-----------------|-----------------|------------------|----------|-------|
| `dev` (default) | 40 | 200 | 14 days (existing seeder) | 1 tx (existing) | &lt; 5 min |
| `load` | 2_000 | 10_000 | 90 days synthetic stats | 5 txs | Warn duration |
| `huge` | 20_000 | 100_000 | 180 days | 20 txs | Requires `--force` |

- **Rationale**: Distinct enough for pagination/charts vs stress; exact counts may vary ±10% in implementation.
- **Alternatives considered**: Only scale events (harder to exercise issue list); Messenger-based generation (overkill for MVP).

## Decision: Demo seed does not call platform seed automatically

- **Decision**: Document order `platform → demo → sample`. Demo may **optionally** `--with-platform` for convenience, but Make `seed` runs platform then demo; bootstrap = migrate + platform only.
- **Rationale**: Spec allows demo without platform; production never runs demo. Explicit Make chaining is clearer than hidden side effects.
- **Alternatives considered**: Always invoke platform from demo (hides prod/demo boundary).

## Decision: Bootstrap composition

- **Decision**: `make bootstrap` = `doctrine:migrations:migrate -n` + `app:seed-platform`. `make seed` = platform + demo (local DX). `make seed-sample PROFILE=dev` optional.
- **Rationale**: Matches FR-005; first-user register still works after bootstrap without demo credentials.

## Decision: Seed revision table deferred

- **Decision**: Skip `platform_seed_version` storage for MVP; idempotent upsert is enough.
- **Rationale**: Spec marks revision as optional; menu seeders already upsert by route/code.
- **Alternatives considered**: `instance_settings.platform_seed_version` string (add in converge if support needs it).

## Decision: Sample generation path

- **Decision**: Create Issue/Event (and reuse Analytics/Performance demo seeders) via dedicated `IssueSampleSeeder` using ORM in batched flushes — not Envelope HTTP.
- **Rationale**: Faster, deterministic fingerprints, no auth/Messenger dependency in tests.
- **Alternatives considered**: Real Envelope ingest (validates wire path but slow/flaky for huge).
