# Feature Specification: Rich Event Context

**Feature Branch**: `010-rich-event-context`

**Created**: 2026-07-20

**Status**: Completed (shipped in v0.4.0; stack source context UI through v0.5.0 / BeaconBundle v1.3.0+)

**Input**: User description: "When errors are stored, keep precise date/time (down to seconds and fractions), environment, code version, logged-in user, PHP version, Symfony version, stack trace, and related context. Apply this in Symfony Beacon and in the client BeaconBundle. In the bundle, what is sent must be configurable (opt-in / opt-out)."

## Clarifications

### Session 2026-07-20

- **Q1 (storage model)**: Full event JSON remains the source of truth in `event.payload`. Beacon also promotes commonly used fields to columns for UI/filter use (`environment`, `release`, plus runtime/user summaries).
- **Q2 (timestamp precision)**: Client sends Unix time with fractional seconds (`microtime(true)`) and an ISO-8601 UTC datetime with microseconds. Server persists `event_timestamp` / `received_at` with microsecond precision (`DATETIME(6)`).
- **Q3 (PII defaults)**: Logged-in user context is **opt-in** (`send.user: false` by default). Stack, environment, release, runtime (PHP), framework (Symfony), OS, server name, and request URI/method default to **on**.
- **Q4 (legal)**: Enabling `send.user` may transmit personal data; operators remain responsible for privacy policy / GDPR alignment and cookie/legal pages on the Beacon host.

### As-built notes

- **Server vs client**: Beacon (this repo) owns ingest promotion + UI. Configurable `nowo_beacon.send.*` and frame source context generation live in **BeaconBundle** (separate repository). Acceptance for US2 is verified against the bundle release notes / CONFIGURATION.md, not only this codebase.
- **Stack source context**: When frames include `pre_context` / `context_line` / `post_context`, Issues/Event UI renders them (see `docs/event-context.md`).
- **Promoted columns**: `environment`, `release_version`, `platform`, `php_version`, `symfony_version`, `user_identifier`, `event_timestamp`, `received_at`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Inspect rich context on an event (Priority: P1)

As a developer investigating an error in Beacon, I can open an event and see when it occurred (precise timestamp), environment, release/version, PHP version, Symfony version, optional user identity, and stack frames without digging only through raw JSON.

**Independent Test**: Ingest an envelope that includes contexts/user/exception; open the event detail page; assert structured fields are visible and timestamps show fractional seconds when present.

**Acceptance Scenarios**:

1. **Given** a stored event with fractional `timestamp`, **When** I open event detail, **Then** I see occurrence and received times with at least second precision (and microseconds when stored).
2. **Given** payload `environment` and `release`, **When** I open event detail, **Then** both are shown.
3. **Given** `contexts.runtime` / `contexts.framework` (or promoted columns), **When** I open event detail, **Then** PHP and Symfony versions are shown when present.
4. **Given** `user` in the payload, **When** I open event detail, **Then** a non-secret user summary (id/username/email as provided) is shown.
5. **Given** `exception.values[].stacktrace.frames`, **When** I open event detail, **Then** frames are listed with optional source context; raw JSON remains available.

### User Story 2 - Client sends configurable context (Priority: P1)

As an application operator using `nowo-tech/beacon-bundle`, I can choose which categories of context are attached to outbound events so I can balance diagnostics vs privacy/payload size.

**Independent Test**: Configure `nowo_beacon.send.*` flags in the **client** app; capture an exception; assert omitted categories are absent from the Envelope payload and enabled ones are present. (Implemented and tested in BeaconBundle.)

**Acceptance Scenarios**:

1. **Given** default send config, **When** an exception is captured, **Then** the payload includes environment, stacktrace, runtime (PHP), framework (Symfony when available), OS, server name, and precise timestamp fields.
2. **Given** `send.user: false` (default), **When** a request has an authenticated user, **Then** no `user` object is sent.
3. **Given** `send.user: true`, **When** a request has an authenticated user, **Then** a minimal `user` object (identifier and optional username/email when available) is sent.
4. **Given** `send.stacktrace: false`, **When** an exception is captured, **Then** exception type/value may still be sent but stack frames / culprit derived from frames are omitted.
5. **Given** `send.request: false`, **When** the automatic exception listener runs, **Then** request URI/method are not added to `extra`.
6. **Given** individual flags disabled for release / server_name / runtime / framework / os / environment, **When** an event is built, **Then** those fields are omitted.

### User Story 3 - Ingest preserves context (Priority: P1)

As Beacon ingest, I store the full payload and promote key fields so UI and future filters stay efficient.

**Independent Test**: Process an envelope with contexts and user; assert payload JSON is unchanged in storage and promoted columns match extracted values; timestamp preserves fractional seconds.

**Acceptance Scenarios**:

1. **Given** a valid event item, **When** ingest runs, **Then** `payload` is persisted as received.
2. **Given** numeric fractional `timestamp`, **When** ingest runs, **Then** `event_timestamp` preserves microsecond precision.
3. **Given** runtime/framework/user fields in the payload, **When** ingest runs, **Then** promoted summary columns are filled when present.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: BeaconBundle MUST attach precise occurrence time (fractional Unix + ISO-8601 UTC with microseconds).
- **FR-002**: BeaconBundle MUST support a `send` configuration map controlling environment, release, server_name, stacktrace, request, user, runtime, framework, and os.
- **FR-003**: BeaconBundle MUST default `send.user` to false; other listed categories default to true.
- **FR-004**: When enabled, runtime context MUST include PHP version; framework context MUST include Symfony kernel version when the class is available.
- **FR-005**: Beacon server MUST parse fractional timestamps into `DATETIME(6)` columns.
- **FR-006**: Beacon event/issue UI MUST present structured context (time, environment, release, runtime, framework, user, stack with optional source context) plus raw payload.
- **FR-007**: Full envelope payload remains stored; promoted columns MUST NOT drop data from `payload`.
- **FR-008**: When frame source context fields are present, the UI MUST render them under each stack frame.

### Non-Functional / Privacy

- **NFR-001**: Document that `send.user` transmits personal data and should align with the operator’s privacy policy.
- **NFR-002**: Docs and UI copy remain English (`lang="en"`); PHPDoc in English.

## Success Criteria *(mandatory)*

- **SC-001**: An integrator can disable any single send category via YAML without code changes (BeaconBundle).
- **SC-002**: Event detail shows precise time and versions for a default-configured client event.
- **SC-003**: Unit/functional tests cover microsecond ingest parsing on the server; send-flag omission is covered in BeaconBundle.
