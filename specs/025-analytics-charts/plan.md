# Implementation Plan: Analytics Charts and Filters

**Branch**: `025-analytics-charts`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Upgrade project Analytics from a fixed 30-day table to period-based **Chart.js** time series (errors / transactions / N+1) with URL-driven presets and custom ranges, plus optional filters (environment, release, level). Unfiltered series use `DailyProjectStat`; filtered error volume aggregates from `Event` (UTC day buckets). Table remains; chart complements it.

## Technical Context

| Area | Decision |
|------|----------|
| Language | PHP 8.5 / Symfony 8.1 / TypeScript (Vite) |
| Chart library | `chart.js` + Stimulus `analytics-chart` controller |
| Unfiltered data | `DailyProjectStat` by `statDate` (UTC calendar days) |
| Filtered data | `COUNT(event)` grouped by `DATE(received_at)` UTC; join `issue` for `level`; `event.environment` / `event.releaseVersion` |
| Period | Query: `period=7\|14\|30\|90\|custom`, `from`/`to` (`Y-m-d`); default `30`; **max span 366 days** |
| Filters | Query: `environment`, `release`, `level` (optional); shareable URL |
| Filtered tx/N+1 | Not available (no env/release on perf rollups) — UI shows errors only + note |
| Sparse days | Zero-fill every calendar day in range for chart + table source |
| Access | Unchanged: project membership required |
| Testing | PHPUnit functional (`AnalyticsAccessTest` extended + filter/period cases) |

## Constitution Check

- Spec-first (`025`); English UI / PHPDoc / docs
- Telemetry focus; no new top-level product
- Prefer kits where applicable (n/a for charts)

## Project Structure

```text
specs/025-analytics-charts/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── tasks.md

src/Analytics/
├── Controller/AnalyticsController.php
├── Dto/AnalyticsDayPoint.php
├── Service/AnalyticsPeriodResolver.php
├── Service/AnalyticsSeriesService.php
└── Repository/DailyProjectStatRepository.php  # range queries

src/Issues/Repository/EventRepository.php     # daily error counts with filters

assets/controllers/analytics_chart_controller.ts
templates/analytics/show.html.twig
```

## Complexity Tracking

| Item | Why needed |
|------|------------|
| Dual data paths | `DailyProjectStat` lacks env/release; filtered accuracy requires event aggregation |
| Zero-fill | Spec requires sparse days as zero in chart and table |
