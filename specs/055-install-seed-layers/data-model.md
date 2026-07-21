# Data Model: Install & Seed Layers

No new Doctrine entities required for MVP.

## Existing entities touched

| Entity / catalog | Layer | Behavior |
|------------------|-------|----------|
| Dashboard menu + items (kit) | Platform | Upsert by menu code + item route |
| Breadcrumb definitions (kit) | Platform | Upsert by route collection |
| `User` | Demo | Create-once by email |
| `Project` (`slug=demo`) | Demo | Create-once; owner membership |
| `ProjectApiKey` | Demo | Create if missing on demo project |
| `Issue` / `Event` | Sample | Insert batches; purge deletes for target project |
| `PerfTransaction` / `PerfSpan` | Sample | Via `PerformanceDemoSeeder` (+ scaled extras for load/huge) |
| `DailyProjectStat` | Sample | Via `AnalyticsDemoSeeder` (+ extended window for load/huge) |

## Logical markers

- **Demo project**: `Project.slug = 'demo'` (stable).
- **Sample sandbox**: Same project by default; override with `--project=<slug>`.
- **Purge**: Delete issues, events, perf rows, and daily stats for that project only; never delete users, memberships, API keys, or platform catalogs.

## Validation rules

- Platform: no writes to User/Project/Issue.
- Demo: must not call Analytics/Performance/Issue sample seeders.
- Sample `huge`: refuse unless `--force` (or non-interactive confirm equivalent).
- Sample without target project: error with message to run demo seed or pass `--project=`.

## Future (out of MVP)

- Optional `is_sample` columns or `sample_batch_id` for multi-project cohabitation with real data on the same project.
