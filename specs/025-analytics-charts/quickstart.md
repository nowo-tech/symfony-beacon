# Quickstart: Analytics Charts

1. Seed demo: `make seed` (or ingest events).
2. Open `/projects/{uuid}/analytics` — default 30-day chart + table.
3. Try `?period=7`, `?period=90`, `?period=custom&from=2026-07-01&to=2026-07-21`.
4. Filter: `?environment=prod&release=1.2.0&level=error` — errors-only series from events.
5. Invalid range (`from` after `to` or span &gt; 366) shows a flash and falls back to 30 days.
