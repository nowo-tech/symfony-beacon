# Implementation Plan: Prometheus Metrics Scrape

**Branch**: `038-prometheus-metrics` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/038-prometheus-metrics/spec.md`

## Summary

Expose `GET /metrics` in Prometheus text exposition 0.0.4 with gauges for Messenger async depth and notification last-failure counts, plus process-local counters for Envelope ACK/reject. Secure with `ROLE_ADMIN` session **or** `BEACON_METRICS_TOKEN` bearer/query for scrapers; document network restriction in PRODUCTION.md.

## Technical Context

**Language/Version**: PHP 8.5 / Symfony 8.1  
**Primary Dependencies**: Existing `MessengerQueueHealth`, notification destination repository; no new Prometheus PHP package (manual exposition)  
**Storage**: Cache counters (`cache.app`) for ACK/reject; live DB/queue reads for gauges  
**Testing**: PHPUnit functional (`MetricsEndpointTest`)  
**Target Platform**: Self-hosted FrankenPHP / Compose  
**Project Type**: web-service  
**Performance Goals**: Scrape &lt; 200ms under normal load  
**Constraints**: No secrets in labels; not publicly scrapable without token/admin  
**Scale/Scope**: Single-instance self-host (counters are per PHP process / shared cache)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- English docs/PHPDoc/UI: yes (PRODUCTION.md)  
- Prefer kits: N/A (no nowo-tech metrics kit)  
- Security: auth/token required — pass  
- No drive-by refactors — pass  

## Project Structure

### Documentation (this feature)

```text
specs/038-prometheus-metrics/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/metrics-exposition.md
└── tasks.md
```

### Source Code

```text
src/Ops/Metrics/
└── MetricsCollector.php          # counters + gauge assembly (`085`; was Shared)

src/Shared/Metrics/
├── PrometheusTextFormatter.php   # text/plain exposition
└── MetricsController.php         # GET /metrics (scrape chrome stays Shared)

config/packages/security.yaml     # PUBLIC_ACCESS + controller gate
config/parameters.yaml / .env.dist # BEACON_METRICS_TOKEN
docs/PRODUCTION.md
tests/Functional/Shared/MetricsEndpointTest.php
```

## Complexity Tracking

| Violation | Why needed | Simpler alternative rejected because |
|-----------|------------|--------------------------------------|
| — | — | — |
