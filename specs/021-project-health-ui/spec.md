# Feature Specification: Project Health UI

**Feature Branch**: `021-project-health-ui`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Project health surface showing Messenger queue depth/lag, webhook delivery failures, and recent last deliveries.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See queue health (Priority: P1)

As a project admin (or platform operator viewing a project), I open a Health view and see whether the async processing queue is healthy (depth and freshness).

**Why this priority**: Stuck queues silently delay ingest processing and notifications.

**Independent Test**: Enqueue known messages; open Health; assert depth/lag indicators match fixtures or test doubles.

**Acceptance Scenarios**:

1. **Given** pending messages for the project or shared worker, **When** I open Health, **Then** I see queue depth and an indication of lag/age of the oldest pending item when available.
2. **Given** an empty healthy queue, **When** I open Health, **Then** status reads healthy / zero depth.
3. **Given** I am unauthorized, **When** I request Health, **Then** access is denied.

---

### User Story 2 - Inspect webhook failures (Priority: P1)

As a project admin, I see recent webhook failures with enough detail to fix the endpoint (status code, time, event type).

**Why this priority**: Failed outbound integrations are a top support cause.

**Independent Test**: Force a failing delivery; open Health; assert failure row appears.

**Acceptance Scenarios**:

1. **Given** a failed webhook delivery, **When** I open Health, **Then** I see the failure with timestamp, target, and error summary.
2. **Given** multiple failures, **When** I view the list, **Then** newest failures appear first (or are clearly sortable).

---

### User Story 3 - Review last deliveries (Priority: P2)

As a project admin, I browse recent successful and failed deliveries to confirm channels are alive.

**Why this priority**: Complements failures with positive confirmation.

**Independent Test**: Send a successful test notification; assert it appears under last deliveries.

**Acceptance Scenarios**:

1. **Given** recent deliveries, **When** I open last deliveries, **Then** I see status (success/fail), type, and time.
2. **Given** no deliveries yet, **When** I open the section, **Then** empty state explains that none exist.

### Edge Cases

- Shared global workers: UI clarifies project-scoped vs instance-wide metrics when both exist.
- Extremely high failure volume: list is capped (e.g. last N) with a note.
- Stale metrics: UI shows when data was last refreshed.
- Partial outages (one channel failing, others OK) are visible per destination.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Project Health UI MUST show messaging queue depth and lag/age indicators relevant to Beacon processing.
- **FR-002**: Health UI MUST list recent webhook/notification delivery failures with actionable metadata.
- **FR-003**: Health UI MUST list recent last deliveries (success and failure) up to a documented limit.
- **FR-004**: Access MUST be limited to project admins (and platform admins as applicable).
- **FR-005**: UI MUST present a clear healthy vs degraded summary suitable for a quick glance.
- **FR-006**: English UI copy; no Spanish in product strings for this feature.

### Key Entities

- **Queue health snapshot**: Depth, oldest age, healthy flag.
- **Delivery record**: Channel, event type, status, HTTP/error detail, time.
- **Health summary**: Aggregate status for the project.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins diagnose a stuck queue or failing webhook from Health alone within 2 minutes in acceptance scenarios.
- **SC-002**: Injected failure fixtures appear on Health within one refresh after recording.
- **SC-003**: Healthy empty state is unambiguous (no false "degraded" when depth is zero and no recent failures).
- **SC-004**: Unauthorized users cannot load Health metrics (negative access tests pass).

## Assumptions

- Messenger (async workers) is already used for ingest/notifications; this feature observes rather than replaces workers.
- v1 may show only the **last** delivery per destination; **last N attempts** history is deferred to `030-delivery-history`.
- Exact infrastructure metrics source (transport stats vs DB outbox) is an implementation choice.
- Legal/ops: Health does not expose raw secrets or full webhook bodies by default.

## Out of scope (deferred)

- Bounded last-N delivery attempt history per destination → `030-delivery-history`.
