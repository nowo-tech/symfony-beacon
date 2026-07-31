# Feature Specification: OTLP metrics ingest (HTTP JSON)

**Feature Branch**: `074-otlp-metrics`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.26  
**Issue**: [#38](https://github.com/nowo-tech/symfony-beacon/issues/38)

**Input**: Accept OpenTelemetry Protocol (OTLP) **metrics** export over HTTP JSON alongside Envelope, OTLP logs, and OTLP traces, mapping failure-like data points into the async Issue/Event pipeline.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ship failure metrics to Beacon (Priority: P1)

As an operator running OpenTelemetry collectors, I POST failure-like metric data points to Beacon so error counters appear as Issues without converting clients to Envelope first.

**Independent Test**: With a valid project API key, `POST /api/{id}/otlp/v1/metrics` with one `http.server.errors` data point creates an Issue.

**Acceptance Scenarios**:

1. **Given** a valid `X-Beacon-Auth` for `{projectId}`, **When** I POST an ExportMetricsServiceRequest JSON with an error-named metric or error attributes, **Then** HTTP 200 and an Issue/Event exist after async processing.
2. **Given** only healthy metrics (e.g. duration histogram without error attrs), **When** I POST a valid body, **Then** HTTP 200 and no events are queued.
3. **Given** missing/invalid auth or query-string credentials, **When** I POST, **Then** 401/403 and nothing is persisted.

### User Story 2 - Same governance as Envelope/logs/traces (Priority: P1)

As an operator, metrics ingest honors ingest suspend, rate limit, daily/monthly quota, and max body size.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Route `POST /api/{projectId}/otlp/v1/metrics` |
| FR-002 | Auth via `X-Beacon-Auth` only (same key+secret binding as Envelope) |
| FR-003 | Map failure-like data points to Beacon event payloads; cap 200/request |
| FR-004 | Reuse `ProcessEnvelopeMessage` worker |
| FR-005 | Document in API.md / DSN.md / OpenAPI |

## Out of Scope

- OTLP gRPC, protobuf binary
- Time-series storage / dashboards / full Performance waterfall
- BeaconBundle OTLP metrics exporter

## Assumptions

- Clients speak OTLP/HTTP JSON (`resourceMetrics` camelCase; snake_case accepted as alias).
- Failure filter mirrors ERROR-only for traces so Issues stay signal-rich.
