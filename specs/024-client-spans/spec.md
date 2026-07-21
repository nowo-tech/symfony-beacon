# Feature Specification: Client Spans

**Feature Branch**: `024-client-spans`  
**Created**: 2026-07-21  
**Status**: In progress (Bundle-primary; Beacon Performance UI already renders spans)

**Input**: Bundle emits Doctrine and HttpClient spans; Beacon Performance UI shows those spans for transactions.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automatic Doctrine spans (Priority: P1)

As an application developer using the Bundle, database queries through Doctrine are recorded as spans on the active transaction without manual instrumentation.

**Why this priority**: DB spans unlock N+1 detection and Performance value with minimal app code.

**Independent Test**: Run a request that executes queries; assert outbound transaction contains db-like spans with useful descriptions.

**Acceptance Scenarios**:

1. **Given** Bundle instrumentation enabled, **When** a request runs Doctrine queries, **Then** spans with db-like operations appear on the transaction.
2. **Given** instrumentation disabled, **When** queries run, **Then** no Doctrine spans are added.
3. **Given** sensitive SQL, **When** spans are created, **Then** descriptions follow scrubbing/normalization rules documented by the Bundle (no raw secrets).

---

### User Story 2 - Automatic HttpClient spans (Priority: P1)

As an application developer, outbound HTTP calls via Symfony HttpClient are recorded as spans.

**Why this priority**: External call latency is a top performance signal alongside DB.

**Independent Test**: Perform an HttpClient request in a traced transaction; assert http.client (or equivalent) span with method/host.

**Acceptance Scenarios**:

1. **Given** instrumentation enabled, **When** HttpClient sends a request, **Then** a child span records method, host, and duration.
2. **Given** a failed HTTP call, **When** the span is recorded, **Then** error status is visible without breaking the parent transaction capture.
3. **Given** instrumentation disabled, **When** HTTP calls occur, **Then** no HTTP spans are emitted.

---

### User Story 3 - View spans in Beacon Performance UI (Priority: P1)

As a project member, I open a transaction in Beacon Performance and see Doctrine and HttpClient spans from the Bundle.

**Why this priority**: Client instrumentation only pays off if Beacon renders it.

**Independent Test**: Ingest a transaction envelope with db and http spans; open Performance detail; assert spans listed and N+1 rules still apply to db-like ops.

**Acceptance Scenarios**:

1. **Given** a transaction with Doctrine and HttpClient spans, **When** I open Performance detail, **Then** both span types are listed with op, description, and duration.
2. **Given** repeated similar db spans ≥ threshold, **When** detection runs, **Then** N+1 candidates still flag as in existing Performance behaviour.
3. **Given** only http spans, **When** I open detail, **Then** they render correctly without requiring db spans.

### Edge Cases

- Nested transactions / multiple concurrent requests: spans attach to the correct parent.
- Very high span counts: Bundle applies a documented cap; Beacon paginates or truncates display safely.
- HttpClient streaming/long requests: duration reflects total time; partial failures still close spans.
- Apps without Doctrine or HttpClient installed: Bundle does not fatally error; instrumentation no-ops.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Beacon Bundle MUST optionally instrument Doctrine queries as spans on the active transaction.
- **FR-002**: Beacon Bundle MUST optionally instrument Symfony HttpClient calls as spans.
- **FR-003**: Instrumentation MUST be toggleable and safe when dependencies are absent.
- **FR-004**: Span payloads MUST be compatible with Beacon Envelope transaction ingest.
- **FR-005**: Beacon Performance UI MUST display received Doctrine and HttpClient spans on transaction detail.
- **FR-006**: Existing N+1 detection MUST continue to recognize db-like spans produced by the Bundle.
- **FR-007**: Work spans Bundle + Beacon; versions MUST document compatibility.

### Key Entities

- **Doctrine span**: DB operation span (op/description/duration).
- **HttpClient span**: Outbound HTTP span (method/host/status/duration).
- **Transaction**: Parent performance record in Beacon.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Sample Symfony app with Bundle shows Doctrine spans in Beacon for a page that hits the database, following docs in under 10 minutes.
- **SC-002**: HttpClient call appears as a span with correct host in Performance detail in acceptance tests.
- **SC-003**: N+1 fixtures built from Bundle-style db spans still trigger candidate highlighting.
- **SC-004**: Disabling instrumentation yields zero auto spans in Bundle unit/integration tests.

## Assumptions

- Builds on Performance (`006-performance`) ingest and UI.
- Bundle is primary instrumentation owner; Beacon remains the viewer.
- Manual custom spans API may already exist or follow later; this feature focuses on Doctrine + HttpClient auto-spans.
- Prefer Bundle integration over app-specific middleware copies.
