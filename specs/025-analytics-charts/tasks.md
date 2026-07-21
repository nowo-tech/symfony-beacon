# Tasks: Analytics Charts and Filters

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)  
**Prerequisites**: plan + research complete

## Phase 1: Setup

- [x] T001 Plan / research / data-model / quickstart
- [x] T002 Add `chart.js` dependency (`package.json` / pnpm)
- [x] T003 Register Stimulus `analytics-chart` controller

## Phase 2: Foundational backend

- [x] T004 `AnalyticsPeriodResolver` — presets, custom range, max 366, validation
- [x] T005 `AnalyticsDayPoint` DTO + `AnalyticsSeriesService` (zero-fill, dual sources)
- [x] T006 `DailyProjectStatRepository` range queries (`findInRange`)
- [x] T007 `EventRepository` daily error counts with env/release/level filters
- [x] T008 Wire `AnalyticsController` + flash on invalid period

## Phase 3: UI (US1 + US2)

- [x] T009 Template: chart canvas, period presets, custom from/to form, keep table
- [x] T010 Stimulus Chart.js multi-series (errors; tx/N+1 when unfiltered)
- [x] T011 SCSS/tokens for chart panel; i18n (en + other locales)

## Phase 4: Filters (US3)

- [x] T012 Filter form fields + URL query preserve on pagination
- [x] T013 Filtered mode: errors-only + help copy

## Phase 5: Tests & docs

- [x] T014 Functional tests: chart present, period, empty, filter, invalid range
- [x] T015 CHANGELOG / UPGRADING / ROADMAP / README / spec status
- [x] T016 `make cs` + targeted PHPUnit

## Dependencies

T002–T003 → T009–T010; T004–T007 → T008 → T009; T012 after T008; T014 after UI.
