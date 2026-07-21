# Feature Specification: Analytics Charts and Filters

**Feature Branch**: `025-analytics-charts`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Upgrade project Analytics from a fixed 30-day table to period-based charts of received issues/errors (and related series), with optional filters.

**Depends on**: `005-analytics` (daily aggregates baseline).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Chart issues over time (Priority: P1)

As a project member, I open Analytics and see a chart of issue/error volume over a selectable time period so I can spot spikes after deploys or incidents.

**Why this priority**: A table alone is hard to scan; charts are the primary operator ask.

**Independent Test**: Seed daily stats across multiple days; open Analytics; assert a chart (or equivalent visual series) renders with expected points for the default period.

**Acceptance Scenarios**:

1. **Given** a project with daily error counts, **When** I open Analytics with the default period, **Then** I see a time-series chart of errors (or issues received) across that period.
2. **Given** empty stats for the selected period, **When** I open Analytics, **Then** the chart shows an empty/zero state without error.
3. **Given** the existing table view, **When** the chart ships, **Then** tabular daily totals remain available (chart complements, does not remove, the table—or an explicit toggle documents the choice).

---

### User Story 2 - Choose a time period (Priority: P1)

As a project member, I change the analytics period (presets and/or custom range) and the chart and table update to that window.

**Why this priority**: Fixed 30 days is insufficient for weekly reviews and longer trends.

**Independent Test**: Switch period query params or UI controls; assert series and table rows match the selected window.

**Acceptance Scenarios**:

1. **Given** Analytics open, **When** I select a preset (e.g. 7 / 14 / 30 / 90 days), **Then** chart and table reflect only that window.
2. **Given** a custom from/to date range within documented limits, **When** I apply it, **Then** results are bounded to that range and the URL (or shareable state) reflects the selection.
3. **Given** an invalid range (end before start, or beyond max span), **When** I apply it, **Then** I see a clear validation message and no 500.

---

### User Story 3 - Filter the series (Priority: P2)

As a project member, I filter Analytics by environment and/or release (and optionally level) so the chart shows only matching volume.

**Why this priority**: Matches release/env triage already present on the issues list; secondary to period charts.

**Independent Test**: Seed events/stats with distinct env/release; apply filters; assert series change.

**Acceptance Scenarios**:

1. **Given** volume across environments, **When** I filter by one environment, **Then** the chart/table show only that environment’s contribution (or documented aggregate semantics).
2. **Given** a release filter, **When** applied, **Then** series reflect events/issues tied to that release per documented rules.
3. **Given** combined period + filters, **When** I clear filters, **Then** the full-period unfiltered series returns.

### Edge Cases

- Timezone: daily buckets use a documented timezone (prefer UTC or project timezone if introduced later); UI labels must not contradict storage.
- Sparse days: missing days appear as zero (or gaps) consistently in chart and table.
- Very large ranges: enforce a documented maximum span; do not silently truncate.
- Filtered dimensions not available on `DailyProjectStat` today may require event-based aggregation or new rollups—implementation plan decides; product behaviour remains correct filtered totals.
- Non-members cannot open Analytics (same access rules as today).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Analytics MUST present a time-series chart of received errors/issues (primary series) for the selected period.
- **FR-002**: Operators MUST be able to select period presets and/or a custom date range within a documented maximum.
- **FR-003**: Selected period MUST drive both chart and tabular daily breakdown (or an explicit single-view mode documented in the UI).
- **FR-004**: Analytics MUST support filtering by at least **environment** and **release** when those dimensions exist on telemetry; unsupported combinations MUST yield empty results, not errors.
- **FR-005**: Filter and period state MUST be shareable via URL query parameters.
- **FR-006**: Project members MAY view; anonymous and non-members MUST be denied.
- **FR-007**: Empty and zero-volume states MUST render without server errors.

### Key Entities

- **Daily (or period) aggregates**: Counts used to drive chart points.
- **Analytics view state**: Period + filters in the URL.
- **Series**: Named lines/bars (errors required; transactions / N+1 optional on the same chart or secondary series).

## Success Criteria *(mandatory)*

- **SC-001**: A member can select a period and see a chart that matches seeded daily totals for that window.
- **SC-002**: Applying environment and release filters changes the series in a testable way.
- **SC-003**: Invalid ranges never produce a 500; functional tests cover default period, empty state, and at least one filter.
- **SC-004**: Docs (UPGRADING / Analytics help copy) describe period presets, max range, and filter semantics.

## Assumptions

- Primary metric is **error/event volume** aligned with today’s `errorCount` (issues received / errors), with transactions and N+1 remaining visible as additional series or columns.
- Prefer reusing existing project nav Analytics entry; no new top-level product.
- Chart library choice is an implementation concern (plan/tasks), not this spec.

## Out of scope

- Real-time streaming charts / WebSocket updates.
- Instance-wide multi-project analytics dashboard.
- Session replay, profiling, or APM heatmaps.
- PagerDuty or external BI exports beyond existing CSV/JSON issue export (`017`).
