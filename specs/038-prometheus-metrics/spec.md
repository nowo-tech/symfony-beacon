# Feature Specification: Prometheus Metrics Scrape

**Feature Branch**: `038-prometheus-metrics`
**Created**: 2026-07-31
**Status**: Implemented (Unreleased)

**Input**: Expose a Prometheus scrape endpoint (`/metrics` or `/health/metrics`) for ingest ACK rate, Messenger depth, and notification failures. Endpoint MUST be auth-gated or network-restricted for self-host.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scrape metrics (P1)

As an operator, I scrape Prometheus metrics from a documented path.

**Acceptance Scenarios**:

1. **Given metrics enabled, When Prometheus scrapes the path, Then counters/gauges for ingest ACK, Messenger depth, and notification failures are present.**
2. **Given no auth/network restriction configured in prod docs, When reviewing INSTALL/PRODUCTION, Then operators are warned not to expose `/metrics` publicly.**

## Requirements *(mandatory)*

- **FR-001**: Document path and security model (ROLE_ADMIN, token, or bind/firewall).
- **FR-002**: Export ingest ACK/reject related counters (reuse existing stats where possible).
- **FR-003**: Export Messenger async pending depth.
- **FR-004**: Export notification delivery failure counters (no secrets in labels).

## Success Criteria

- **SC-001**: Metrics format is valid Prometheus text exposition.
- **SC-002**: PRODUCTION.md documents exposure constraints.

## Out of scope

- Full APM / distributed tracing.
- Ops overview UI (`035`) — may share underlying counters.
