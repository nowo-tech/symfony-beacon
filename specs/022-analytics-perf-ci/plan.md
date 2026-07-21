# Implementation Plan: Analytics and Performance CI Coverage

**Branch**: `022-analytics-perf-ci`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Tests | `tests/Analytics/AnalyticsAccessTest.php`, `tests/Performance/PerformanceAccessTest.php` via `DatabaseWebTestCase` |
| CI | Default `phpunit.dist.xml` suite includes `tests/`; `.github/workflows/ci.yml` runs `vendor/bin/phpunit` with no excludes |
| Docs | CONTRIBUTING notes Analytics/Performance coverage in `make test` |

## Constitution Check

- Spec-first (`022`); deterministic access checks only (no flaky clocks)
