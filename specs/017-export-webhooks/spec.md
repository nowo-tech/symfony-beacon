# Feature Specification: Export and Lifecycle Webhooks

**Feature Branch**: `017-export-webhooks`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Export issues (and related rows) as CSV and JSON; emit lifecycle webhooks for issue events such as resolved, assigned, and similar status/assignment changes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Export issues as CSV/JSON (Priority: P1)

As a project member, I export the current issue list (respecting active filters) to CSV or JSON for offline analysis or tickets.

**Why this priority**: Export unblocks reporting without building every chart in-product.

**Independent Test**: Apply filters; download CSV and JSON; assert row counts and key columns.

**Acceptance Scenarios**:

1. **Given** a filtered issues list, **When** I export as CSV, **Then** the file contains one row per matching issue with documented columns.
2. **Given** the same filter, **When** I export as JSON, **Then** the payload lists the same issues with equivalent fields.
3. **Given** no matching issues, **When** I export, **Then** I receive a valid empty file/array, not an error.

---

### User Story 2 - Lifecycle webhooks on issue changes (Priority: P1)

As a project admin, I configure an HTTP webhook endpoint that receives events when issues are resolved, assigned, reopened, ignored, or similarly transitioned.

**Why this priority**: Integrates Beacon into existing ops chat/ticketing without polling.

**Independent Test**: Register endpoint (or test double); change issue status/assignee; assert delivery payload type and issue id.

**Acceptance Scenarios**:

1. **Given** a configured webhook for `issue.resolved`, **When** a member marks an issue resolved, **Then** the endpoint receives an event of that type with issue and project identifiers.
2. **Given** a configured webhook for `issue.assigned`, **When** assignee changes, **Then** a delivery includes previous and new assignee when available.
3. **Given** an endpoint that returns 5xx, **When** delivery fails, **Then** the failure is recorded and retried per project notification/retry policy (or documented backoff).

---

### User Story 3 - Choose which lifecycle events to send (Priority: P2)

As a project admin, I enable only the lifecycle event types I care about.

**Why this priority**: Reduces noise; secondary to shipping core deliveries.

**Independent Test**: Enable only `issue.resolved`; trigger assign and resolve; assert only resolve is delivered.

**Acceptance Scenarios**:

1. **Given** only `issue.resolved` enabled, **When** I assign an issue, **Then** no assign webhook is sent.
2. **Given** multiple types enabled, **When** corresponding actions occur, **Then** each enabled type can fire independently.

### Edge Cases

- Export of very large result sets may be capped or streamed; user sees a clear limit message if capped.
- Webhook URLs must pass existing SSRF / allowlist guards used by project notifications.
- Rapid successive status flips should not drop distinct events (ordering preserved best-effort).
- Export does not include secrets or raw full event payloads unless explicitly documented.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Project members MUST be able to export filtered issues as CSV and as JSON.
- **FR-002**: Export MUST respect the same authorization as viewing the issues list.
- **FR-003**: System MUST support lifecycle webhook event types including at least `issue.resolved` and `issue.assigned`, plus documented siblings (e.g. reopened, ignored) as implemented.
- **FR-004**: Project admins MUST be able to register webhook endpoints and select enabled lifecycle types.
- **FR-005**: Deliveries MUST include event type, timestamp, project id, and issue id (plus change-specific fields).
- **FR-006**: Failed deliveries MUST be observable (status/log) and subject to retry rules consistent with existing outbound notification infrastructure where applicable.
- **FR-007**: Webhook configuration MUST reuse security controls against unsafe destinations (SSRF protection).

### Key Entities

- **Issue export**: Filtered snapshot in CSV or JSON.
- **Lifecycle webhook subscription**: URL, secret (optional), enabled event types.
- **Webhook delivery**: Attempt record with status and payload metadata.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Members can complete a CSV export of up to 1,000 filtered issues in under 30 seconds in acceptance environments.
- **SC-002**: JSON and CSV exports for the same filter contain the same issue ids.
- **SC-003**: Enabling `issue.resolved` yields a successful test delivery when an issue is resolved against a reachable endpoint.
- **SC-004**: Disabling a type results in zero deliveries for that type in acceptance tests.

## Assumptions

- Reuses patterns from `009-project-notifications` for HTTP delivery, secrets, and SSRF guards where possible.
- Native PagerDuty is out of scope (see `020-notification-digest`).
- Export columns cover list-visible issue fields; full event dump is out of scope for v1.
- Lifecycle events are driven by member/system status and assignee changes already recorded in issue history.
