# Feature Specification: OTLP logs ingest (HTTP JSON)

**Feature Branch**: `067-otlp-ingest`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.19

**Input**: Accept OpenTelemetry Protocol (OTLP) log export over HTTP JSON alongside existing Envelope ingest, reusing project DSN credentials and the async Issue/Event pipeline.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ship OTLP ERROR logs to Beacon (Priority: P1)

As an operator running OpenTelemetry collectors or SDKs, I POST OTLP log records to Beacon so WARN+/ERROR logs appear as Issues without converting clients to Envelope NDJSON first.

**Independent Test**: With a valid project API key, `POST /api/{id}/otlp/v1/logs` with one ERROR LogRecord creates an Issue titled from the log body.

**Acceptance Scenarios**:

1. **Given** a valid `X-Beacon-Auth` for `{projectId}`, **When** I POST an ExportLogsServiceRequest JSON with severityNumber ≥ 13, **Then** HTTP 200 and an Issue/Event exist after async processing.
2. **Given** only DEBUG/INFO records, **When** I POST a valid body, **Then** HTTP 200 and no events are queued (filtered).
3. **Given** missing/invalid auth, **When** I POST, **Then** 401/403 and nothing is persisted.

### User Story 2 - Same governance as Envelope (Priority: P1)

As an operator, OTLP ingest honors ingest suspend, rate limit, daily/monthly quota, and max body size.

**Acceptance Scenarios**:

1. Oversized body → **413**.
2. Rate/quota exceeded → **429** with `Retry-After`.
3. Ingest disabled → **403**.

### User Story 3 - No secret leakage (Priority: P1)

As a security reviewer, OTLP never accepts query-string credentials and never puts DSN secrets into Messenger payloads.

**Acceptance Scenarios**:

1. Query `beacon_key`/`beacon_secret` → **401**.
2. Queued Envelope derived from OTLP has no `dsn` header field.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Route `POST /api/{projectId}/otlp/v1/logs` |
| FR-002 | Auth via `X-Beacon-Auth` only (same key+secret binding as Envelope) |
| FR-003 | Map OTLP JSON LogRecords (severity ≥ WARN) to Beacon event payloads; cap 200 records/request |
| FR-004 | Reuse `ProcessEnvelopeMessage` worker for persistence/grouping/notifications |
| FR-005 | Document in API.md / DSN.md / OpenAPI; no gRPC in this slice |

## Out of Scope (this spec)

- OTLP gRPC / protobuf binary
- BeaconBundle OTLP exporter

## As-built follow-ups

- OTLP traces HTTP JSON: **`070-otlp-traces`** (Phase 6.22 Done).
- OTLP metrics HTTP JSON: **`074-otlp-metrics`** (Phase 6.26 Done).
- gRPC / protobuf / Bundle exporter / Performance TSDB: ROADMAP **Later**.

## Assumptions

- Clients speak OTLP/HTTP JSON (`resourceLogs` camelCase; snake_case accepted as alias).
- Envelope ingest remains the primary SDK path; OTLP is an adapter.
