# Feature Specification: Notification Delivery History

**Feature Branch**: `030-delivery-history`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Persist and show the last N delivery attempts per notification destination (success/fail, timestamp, truncated error), not only the single last delivery fields from `021-project-health-ui`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See recent deliveries (Priority: P1)

As a project admin, I open Settings / Health and expand a destination to see the last N sends with outcome.

**Acceptance Scenarios**:

1. **Given** several successful and failed deliveries, **When** I view destination health, **Then** I see up to N chronologically ordered attempts.
2. **Given** no deliveries yet, **When** I view the destination, **Then** an empty state is shown.

### User Story 2 - Bound storage (Priority: P1)

As an operator, retention of delivery rows is bounded per destination so the table cannot grow without limit.

**Acceptance Scenarios**:

1. **Given** more than N deliveries, **When** a new attempt is recorded, **Then** older rows beyond N are pruned (or equivalent rolling window).

## Requirements *(mandatory)*

- **FR-001**: Record each delivery attempt (or sampled equivalent) with timestamp, success flag, and optional error snippet.
- **FR-002**: UI shows last N attempts (documented default, e.g. 20) per destination for project admin+.
- **FR-003**: Keep existing “last delivery” summary fields in sync or derive them from the newest history row.
- **FR-004**: Send-test creates a visible history entry.

## Success Criteria

- **SC-001**: Functional test asserts history length and newest-first ordering after multiple deliveries.
- **SC-002**: Prune behaviour covered by unit or functional test.

## Out of scope

- Full searchable log warehouse / ELK.
- Payload body archival of every notification.
