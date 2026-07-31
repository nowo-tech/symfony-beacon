# Research: 038 Prometheus metrics

## Decision: Manual exposition vs client library

**Choice**: Manual Prometheus text format (no new Composer dependency).

**Rationale**: Exact-version pins; only a handful of series; avoids Flex recipe churn.

## Decision: Path

**Choice**: `GET /metrics` (not `/health/metrics`) so health probes stay separate from scrape noise.

## Decision: Auth model

**Choice**:
1. Authenticated `ROLE_ADMIN` session (browser), **or**
2. `Authorization: Bearer <BEACON_METRICS_TOKEN>` when `BEACON_METRICS_TOKEN` is non-empty (query `?token=` is not accepted).

Empty token + anonymous → 401. Document firewall/bind in PRODUCTION.md.

## Decision: Metric sources

| Series | Type | Source |
|--------|------|--------|
| `beacon_messenger_async_pending` | gauge | `MessengerQueueHealth` |
| `beacon_notification_destinations_failed` | gauge | destinations with failed last delivery |
| `beacon_ingest_ack_total` | counter | Cache increment on Envelope 200 |
| `beacon_ingest_reject_total{reason=...}` | counter | Cache increment on 401/403/429/400 |

Counters live in `cache.app` (shared across FrankenPHP workers when using Redis/fs shared; Doctrine cache may be per-process — document).

## Alternatives considered

- Full `promphp/prometheus_client_php` + Redis adapter — deferred until multi-pod cardinality needs grow.
- OpenTelemetry — out of scope (Later on ROADMAP).
