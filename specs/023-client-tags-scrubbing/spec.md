# Feature Specification: Client Tags and Scrubbing

**Feature Branch**: `023-client-tags-scrubbing`  
**Created**: 2026-07-21  
**Status**: In progress (Bundle-primary; companion UI/docs in Beacon)

**Input**: Primary work in the Beacon client Bundle: public tags API and `before_send` scrubbing hooks; Beacon server provides companion UI. Repositories: Bundle + Beacon.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Set public tags from the client (Priority: P1)

As an application developer using the Beacon Bundle, I attach public tags to events/transactions via a documented API so issues are filterable in Beacon.

**Why this priority**: Tags are the main client-side enrichment for search/filter.

**Independent Test**: In a sample app, set tags; capture envelope; assert tags present in payload and visible after ingest in Beacon.

**Acceptance Scenarios**:

1. **Given** the Bundle is configured, **When** I set public tags via the public API, **Then** subsequent events include those tags.
2. **Given** tag key/value limits, **When** I exceed them, **Then** the Bundle documents truncation/rejection behaviour and does not crash the host app.
3. **Given** tags on events, **When** ingested by Beacon, **Then** they appear on issue/event UI and are usable by tag filters when search supports them.

---

### User Story 2 - Scrub payloads with before_send (Priority: P1)

As an application developer, I register a `before_send` callback to mutate or drop events (e.g. strip PII) before they leave the process.

**Why this priority**: Privacy and compliance require client-side scrubbing.

**Independent Test**: Register callback that removes a field; assert outbound envelope lacks it; callback returning null/false drops the event.

**Acceptance Scenarios**:

1. **Given** a `before_send` hook, **When** an event is captured, **Then** the hook runs and can modify the payload.
2. **Given** the hook aborts send, **When** capture occurs, **Then** no envelope is transmitted.
3. **Given** the hook throws, **When** capture occurs, **Then** the Bundle fails soft (event dropped or unmodified per documented policy) without crashing the host request.

---

### User Story 3 - Companion UI in Beacon (Priority: P2)

As a project member, I see guidance or project settings hints about client tags and scrubbing (docs link / example) so server and client stay aligned.

**Why this priority**: Companion UX; Bundle remains source of truth for behaviour.

**Independent Test**: Open project integration/settings docs panel; assert English guidance and link to Bundle docs.

**Acceptance Scenarios**:

1. **Given** a project, **When** I open the companion UI section, **Then** I see how tags and `before_send` relate to ingested data.
2. **Given** events with scrubbed fields, **When** I view event detail, **Then** absent fields are simply missing (no error).

### Edge Cases

- Reserved tag keys used by the system are documented; user overrides follow documented precedence.
- Binary/large tag values are rejected or coerced to strings.
- `before_send` must not be invoked recursively when the hook itself logs.
- Multi-repo release: Bundle version and Beacon version compatibility is documented.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Beacon Bundle MUST expose a public API to set/get public tags for outbound events.
- **FR-002**: Beacon Bundle MUST support a `before_send` hook to modify or drop events before transport.
- **FR-003**: Bundle MUST document tag limits, hook semantics, and failure behaviour in English.
- **FR-004**: Beacon server MUST ingest and display tags supplied by the Bundle without requiring server-side tag configuration for basic use.
- **FR-005**: Beacon MUST provide companion UI/docs entry points for tags and scrubbing (not a reimplementation of the hook).
- **FR-006**: Work spans Bundle and Beacon repositories; changes MUST be versioned so operators know compatible pairs.

### Key Entities

- **Public tag**: Key/value attached to client events.
- **before_send hook**: Callback invoked pre-transport.
- **Companion guidance**: Beacon UI/docs referencing Bundle capabilities.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Sample app sets three tags and they appear on an ingested event in Beacon in under 5 minutes following docs.
- **SC-002**: `before_send` that strips a password field yields zero occurrences of that field in captured envelopes in tests.
- **SC-003**: Aborting in `before_send` results in zero network send attempts in unit/integration tests.
- **SC-004**: Companion Beacon UI is reachable from project integration settings in one navigation step.

## Assumptions

- Primary implementation ownership is the `nowo-tech/beacon-bundle` (or current Bundle package); Beacon is companion.
- Server-side PII scrubbing policies are out of scope except displaying what clients send.
- Prefer official Bundle APIs over ad-hoc app middleware.
- Cookie/legal: scrubbing helps GDPR posture; operators still need privacy policy pages when hosting Beacon.
