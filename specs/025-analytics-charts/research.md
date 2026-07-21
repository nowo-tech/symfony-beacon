# Research: Analytics Charts and Filters

## Decision 1 — Chart library

**Choice**: Chart.js 4 via pnpm + Stimulus controller.

**Rationale**: Mature, tree-shakeable, no jQuery dependency, works with canvas and Beacon CSS tokens. Avoids heavier Apex/Plotly for a single multi-series line chart.

**Alternatives**: CSS-only sparkline (too limited); server SVG (harder interactive tooltips).

## Decision 2 — Unfiltered vs filtered sources

**Choice**:
- No filters → `DailyProjectStat` (errors, transactions, N+1).
- Any of `environment` / `release` / `level` → daily `COUNT(Event)` for errors only.

**Rationale**: Daily rollups have no dimensions. Perf transactions lack env/release columns. Spec allows optional secondary series; filtered mode documents errors-only.

## Decision 3 — Period encoding

**Choice**: `period` presets `7|14|30|90|custom` with `from`/`to` for custom; default `30`; reject span &gt; 366 days or `to` &lt; `from` with flash + fallback to default 30.

**Timezone**: UTC calendar days (`statDate` and `DATE(received_at)` in UTC), labeled as UTC in UI help.

## Decision 4 — Zero-fill

**Choice**: Build a continuous list of days from inclusive `from` through inclusive `to`; missing aggregates = 0. Chart uses full list; table paginates the same list (newest first).
