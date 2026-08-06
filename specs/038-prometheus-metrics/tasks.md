# Tasks: 038-prometheus-metrics

## Phase 1: Foundation

- [x] T001 Add `BEACON_METRICS_TOKEN` to `.env.dist` + `beacon.metrics_token` parameter
- [x] T002 Create `MetricsCollector` under `src/Ops/Metrics/` + `PrometheusTextFormatter` under `src/Shared/Metrics/` (`085` moved collector to Ops)
- [x] T003 Create `MetricsController` `GET /metrics` with admin-or-token gate
- [x] T004 Wire `security.yaml` PUBLIC_ACCESS for `^/metrics$`

## Phase 2: Instrumentation

- [x] T005 Increment ACK/reject counters from `EnvelopeController`
- [x] T006 Expose messenger pending + failed-destination gauges

## Phase 3: Docs & tests

- [x] T007 Document in `docs/PRODUCTION.md` + CHANGELOG Unreleased
- [x] T008 Functional tests in `tests/Functional/Shared/MetricsEndpointTest.php`
- [x] T009 Mark ROADMAP 6.5 / spec status Implemented (Unreleased)
