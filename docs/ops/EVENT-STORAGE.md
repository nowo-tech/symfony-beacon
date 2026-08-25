# Event storage growth (cold path / partitioning)

Beacon stores full Envelope payloads on `event.payload` (JSON) plus promoted filter columns.
Indexes already cover the hot filters (`idx_event_project_received`, issue/env/release/user/url).
Retention (`app:retention:purge`) now deletes in **batches of 1000** so age/cap rules do not hold long locks.

## When this matters

Plan a cold path when any of these are true for a long-lived project or instance:

- Tens of millions of `event` rows, or multi‑GB `event` tables
- `app:retention:purge` routinely runs longer than your maintenance window
- Backups / replicas lag because of `event` I/O

## Recommended stages (do not skip)

1. **Retention first** — set Ops defaults + per-project retention days / max events; schedule `app:retention:purge` (cron). Keep batches at the default (1000) unless ops needs smaller chunks under replication lag.
2. **Quota counters** — ingest already caches daily/monthly usage in `cache.app` (`EventQuotaUsageStore`); do not reintroduce per-request `COUNT(*)` on the ACK path.
3. **Cold table (optional)** — move aged rows to `event_cold` with the same shape (or compressed payload) via a one-shot / nightly job; UI detail can fall back to cold when hot miss. Prefer this before MySQL partitioning if you need portable SQLite tests to stay simple.
4. **RANGE partitioning (MySQL only)** — partition `event` by `received_at` (monthly). Requires dropping FKs that block partitioning or converting them to application-enforced integrity. Document operator downtime; do **not** enable in default migrations.
5. **Object storage for payloads (Later)** — store large payloads outside MySQL; keep promoted columns + pointer. Out of scope until a dedicated spec.

## Non-goals

- Automatic partitioning in Flex recipes
- Changing Envelope wire format
- Replacing Messenger Redis streams

## Related

- [PRODUCTION.md](../PRODUCTION.md) — retention schedule
- `App\Ops\Retention\RetentionPurger` — batched deletes
- Constitution principle VI — efficient ingest (ACK fast, persist async)
