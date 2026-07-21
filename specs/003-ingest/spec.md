# Feature Specification: Ingest

**Feature Branch**: `003-ingest`  
**Created**: 2026-07-19  
**Status**: Completed (as-built; **required secret** when API key has secret — 2026-07-21)  

## Summary

Envelope-compatible ingest accepts events/transactions, authenticates via project API keys, acknowledges quickly, and processes asynchronously via Messenger. Event items group into issues (see `004-issues`); transaction items feed performance (see `006-performance`) and analytics counters.

DSN format (see `docs/DSN.md`):

```text
https://<public_key>:<secret_key>@<host>:<port>/<project_id>
```

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Authenticated Envelope POST (Priority: P1)

As an SDK / BeaconBundle client, I POST to `POST /api/{project_id}/envelope/` with Envelope auth and receive a fast ACK.

**Acceptance Scenarios**:

1. **Given** a valid project API key via Envelope auth header, query string, or envelope `dsn` including **public and secret** when the key stores a secret, **When** I POST a well-formed envelope, **Then** the HTTP layer acknowledges success promptly.
2. **Given** an invalid or missing public key, **When** I POST, **Then** the request is rejected without processing (`401` / `403` as appropriate).
3. **Given** an API key that has a stored secret, **When** I POST with only `beacon_key` (no `beacon_secret` / DSN secret), **Then** the request is rejected with **HTTP 403**.
4. **Given** Docker local clients, **When** DSN points at host port **9081** / `host.docker.internal`, **Then** ingest is reachable (documented in README).

### User Story 2 - Async processing (Priority: P1)

As the server, heavy work runs on Messenger (`ProcessEnvelopeMessage` / handler), not on the request thread.

**Acceptance Scenarios**:

1. **Given** an accepted envelope, **When** the worker runs, **Then** event/transaction items are persisted and grouped.
2. **Given** a **resolved** matching issue, **When** a new event item is processed, **Then** the issue becomes **unresolved** and a system `issue_history` status entry is recorded.
3. **Given** an **ignored** matching issue, **When** a new event item is processed, **Then** the issue becomes **unresolved** (same reopen rule as resolved) and history records it.

### User Story 3 - Promoted event fields (Priority: P2)

As ingest, I store full `payload` and promote common columns (see `010-rich-event-context` / `docs/EVENT-CONTEXT.md`).

**Acceptance Scenarios**:

1. **Given** environment/release/runtime/user in the payload, **When** processed, **Then** promoted columns are filled when present.
2. **Given** fractional timestamps, **When** processed, **Then** `event_timestamp` uses `DATETIME(6)` precision.

## Requirements *(mandatory)*

- **FR-001**: Primary path `POST /api/{project_id}/envelope/` with Envelope-compatible auth.
- **FR-002**: Fast ACK; persistence/grouping via Messenger.
- **FR-003**: Event items update issues via fingerprint similarity; reopen **resolved** and **ignored** → **unresolved** (see `004-issues` / `009-project-notifications`).
- **FR-004**: Transaction items create performance records and may increment N+1 daily stats.
- **FR-005**: Full payload retained; promoted columns do not replace JSON storage.
- **FR-006**: When a `ProjectApiKey` has a non-empty secret, ingest MUST require a matching secret (`beacon_secret` / DSN userinfo / query). Public-key-only requests MUST NOT be accepted for such keys.
- **FR-007**: Cross-tenant isolation: the public key MUST belong to the `{project_id}` in the URL.

## Success Criteria

- **SC-001**: PHPUnit covers auth rejection (missing key, missing secret when required), async processing happy path, and resolved/ignored reopen.
- **SC-002**: Constitution ingest latency principle remains satisfied (ACK before heavy work).
