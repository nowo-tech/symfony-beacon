# Feature Specification: OTLP traces ingest (HTTP JSON)

**Feature Branch**: `070-otlp-traces`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.22  
**Issue**: [#28](https://github.com/nowo-tech/symfony-beacon/issues/28)

**Input**: Accept OpenTelemetry Protocol (OTLP) **trace** export over HTTP JSON alongside Envelope and OTLP logs, mapping ERROR spans into the async Issue/Event pipeline.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ship OTLP ERROR spans to Beacon (Priority: P1)

As an operator running OpenTelemetry collectors, I POST ERROR spans to Beacon so failed operations appear as Issues without converting clients to Envelope first.

**Independent Test**: With a valid project API key, `POST /api/{id}/otlp/v1/traces` with one ERROR span creates an Issue titled from the exception/status message.

**Acceptance Scenarios**:

1. **Given** a valid `X-Beacon-Auth` for `{projectId}`, **When** I POST an ExportTraceServiceRequest JSON with status code ERROR, **Then** HTTP 200 and an Issue/Event exist after async processing.
2. **Given** only OK/UNSET spans without exceptions, **When** I POST a valid body, **Then** HTTP 200 and no events are queued.
3. **Given** missing/invalid auth or query-string credentials, **When** I POST, **Then** 401/403 and nothing is persisted.

### User Story 2 - Same governance as Envelope/logs (Priority: P1)

As an operator, traces ingest honors ingest suspend, rate limit, daily/monthly quota, and max body size.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Route `POST /api/{projectId}/otlp/v1/traces` |
| FR-002 | Auth via `X-Beacon-Auth` only (same key+secret binding as Envelope) |
| FR-003 | Map ERROR spans (+ exception attributes) to Beacon event payloads; cap 200 spans/request |
| FR-004 | Reuse `ProcessEnvelopeMessage` worker |
| FR-005 | Document in API.md / DSN.md / OpenAPI |

## Out of Scope

- OTLP gRPC, `/v1/metrics`, protobuf binary
- Full Performance waterfall / transaction ingest from all spans
- BeaconBundle OTLP exporter

## Assumptions

- Clients speak OTLP/HTTP JSON (`resourceSpans` camelCase; snake_case accepted as alias).
- ERROR-only filtering mirrors WARN+ for logs so Issues stay signal-rich.
