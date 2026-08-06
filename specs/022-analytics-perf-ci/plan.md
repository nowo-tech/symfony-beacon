# Implementation Plan: Analytics and Performance CI Coverage

**Branch**: `022-analytics-perf-ci`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Tests | `tests/Functional/Analytics/AnalyticsAccessTest.php`, `tests/Functional/Performance/PerformanceAccessTest.php` via `DatabaseWebTestCase` |
| CI | Default `phpunit.dist.xml` suites (`Unit` / `Functional` / `Integration`) under `tests/`; `.github/workflows/ci.yml` runs `vendor/bin/phpunit` with no excludes |
| Docs | CONTRIBUTING notes Analytics/Performance coverage in `make test` |

## Constitution Check

- Spec-first (`022`); deterministic access checks only (no flaky clocks)
