# Feature Specification: Deprecate query-string Envelope auth

**Feature Branch**: `049-deprecate-query-ingest-auth`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: `?beacon_key=&beacon_secret=` leaks secrets into logs/Referer; deprecate while remaining compatible.

## User Scenarios & Testing

### User Story 1 - Deprecation signals (Priority: P1)

**Acceptance Scenarios**:

1. **Given** query auth on Envelope POST, **When** the request is accepted, **Then** responses include `Deprecation` and `Warning` headers and the server logs a deprecation notice.
2. **Given** `X-Beacon-Auth` or envelope `dsn`, **When** ingest succeeds, **Then** no query-auth deprecation is emitted.

## Requirements

- Document preferred auth in DSN.md / API.md; keep query acceptance until a future removal spec.
