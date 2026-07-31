# Feature Specification: Notification Circuit Breaker

**Feature Branch**: `039-notification-circuit-breaker`
**Created**: 2026-07-31
**Status**: Draft  

**Input**: Pause / back off a notification destination after N consecutive delivery failures; instance or project admin can resume. Complements `030` delivery history.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Auto-pause after failures (P1)

As an operator, a flapping webhook stops being hit after N consecutive failures.

**Acceptance Scenarios**:

1. **Given N consecutive failures for a destination, When the next notify would fire, Then the destination is paused/backed off and attempts are skipped or delayed.**
2. **Given a paused destination, When an admin resumes it, Then deliveries may proceed again.**

## Requirements *(mandatory)*

- **FR-001**: Configurable consecutive-failure threshold (env and/or project override).
- **FR-002**: Persist pause/backoff state on destination; surface in Health/Admin.
- **FR-003**: Admin resume action with CSRF.
- **FR-004**: Never log or render webhook secrets.

## Success Criteria

- **SC-001**: Tests cover trip and resume.
- **SC-002**: UI shows paused state clearly.

## Out of scope

- Third-party alerting SaaS.
- Auto-resume heuristics beyond documented backoff.
