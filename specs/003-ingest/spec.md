# Feature Specification: Ingest

**Feature Branch**: `003-ingest`  
**Created**: 2026-07-19  
**Status**: Completed (as-built; **required secret** when API key has secret — 2026-07-21; envelope size / credential scrub / batch flush — 2026-07-22)  

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
4. **Given** Docker local clients, **When** DSN points at host port **9084** / `host.docker.internal`, **Then** ingest is reachable (documented in README).

### User Story 2 - Async processing (Priority: P1)

As the server, heavy work runs on Messenger (`ProcessEnvelopeMessage` / handler), not on the request thread.

**Acceptance Scenarios**:

1. **Given** an accepted envelope, **When** the worker runs, **Then** event/transaction items are persisted and grouped with **one Doctrine flush per envelope** (notifications/thresholds deferred until after that flush).
2. **Given** a **resolved** matching issue, **When** a new event item is processed, **Then** the issue becomes **unresolved** and a system `issue_history` status entry is recorded.
3. **Given** an **ignored** matching issue, **When** a new event item is processed, **Then** the issue becomes **unresolved** (same reopen rule as resolved) and history records it.
4. **Given** sync Messenger (e.g. tests) shared with the HTTP EntityManager, **When** auth left a managed `ProjectApiKey`, **Then** the handler clears that EM graph before work so follow-up flushes cannot double-encrypt Halite secrets.

### User Story 3 - Promoted event fields (Priority: P2)

As ingest, I store full `payload` and promote common columns (see `010-rich-event-context` / `docs/product/EVENT-CONTEXT.md`).

**Acceptance Scenarios**:

1. **Given** environment/release/runtime/user in the payload, **When** processed, **Then** promoted columns are filled when present.
2. **Given** fractional timestamps, **When** processed, **Then** `event_timestamp` uses `DATETIME(6)` precision.

## Requirements *(mandatory)*

- **FR-001**: Primary path `POST /api/{project_id}/envelope/` with Envelope-compatible auth.
- **FR-002**: Fast ACK; persistence/grouping via Messenger; worker default memory limit at least **256M**.
- **FR-003**: Event items update issues via fingerprint similarity; reopen **resolved** and **ignored** → **unresolved** (see `004-issues` / `009-project-notifications`).
- **FR-004**: Transaction items create performance records and may increment N+1 daily stats.
- **FR-005**: Full payload retained for processing; promoted columns do not replace JSON storage. Before queueing, the HTTP layer MUST strip envelope-header `dsn` (may contain the secret) from the Messenger payload.
- **FR-006**: When a `ProjectApiKey` has a non-empty secret, ingest MUST require a matching secret (`beacon_secret` / DSN userinfo / query). Public-key-only requests MUST NOT be accepted for such keys.
- **FR-007**: Cross-tenant isolation: the public key MUST belong to the `{project_id}` in the URL.
- **FR-008**: Request bodies larger than `BEACON_ENVELOPE_MAX_BYTES` (default 2 MiB) MUST be rejected with **HTTP 413**.
- **FR-009**: Client-visible parse failures MUST use a generic `invalid envelope` message (no internal exception detail).
- **FR-010**: Handler persistence MUST use one Doctrine flush per envelope; notification/threshold side effects run after that flush.

## Success Criteria

- **SC-001**: PHPUnit covers auth rejection (missing key, missing secret when required), multi-request ingest with durable Halite test key, async processing happy path, and resolved/ignored reopen.
- **SC-002**: Constitution ingest latency principle remains satisfied (ACK before heavy work).
- **SC-003**: Queued `ProcessEnvelopeMessage` bodies MUST NOT retain envelope-header `dsn` after successful auth.

## Amendment (`IngestProjectAccessGate`, 2026-08-11)

- Envelope HTTP auth + governance/rate checks share `App\Ingest\Service\IngestProjectAccessGate` with OTLP (`authorizeCredentials` + `assertIngestAllowed`). Controllers keep body-size limits, auth parsing, and response shaping.
- Cross-links: `067-otlp-ingest`, `086-dry-refactor`.
