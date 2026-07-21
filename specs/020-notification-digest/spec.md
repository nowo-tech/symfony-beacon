# Feature Specification: Notification Digest and Quiet Hours

**Feature Branch**: `020-notification-digest`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Quiet hours and digest delivery for project notifications; explicitly no native PagerDuty integration.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure quiet hours (Priority: P1)

As a project admin, I define quiet hours so non-critical notifications are held and not delivered immediately.

**Why this priority**: Reduces night-time noise for self-hosted teams.

**Independent Test**: Set quiet hours; trigger a notifiable event inside the window; assert immediate delivery suppressed (or deferred per rules).

**Acceptance Scenarios**:

1. **Given** quiet hours 22:00–07:00 in the project timezone, **When** a digest-eligible event occurs at 23:00, **Then** it is not sent immediately to quiet-hour channels.
2. **Given** quiet hours configured, **When** an event occurs outside the window, **Then** immediate delivery behaves as today.
3. **Given** invalid time ranges, **When** I save, **Then** validation prevents save.

---

### User Story 2 - Receive a digest (Priority: P1)

As a project member/admin, I receive a scheduled digest summarizing held or batched notifications.

**Why this priority**: Quiet hours need a catch-up path so signals are not lost.

**Independent Test**: Hold multiple notifications; run digest; assert summary content and channel delivery.

**Acceptance Scenarios**:

1. **Given** several held items, **When** the digest runs, **Then** recipients get one summary listing issue titles/counts (not N separate spam messages).
2. **Given** nothing held, **When** digest runs, **Then** no empty spam message is sent (or a documented opt-in empty digest is off by default).
3. **Given** digest settings disabled, **When** quiet hours end, **Then** either flush-immediately or skip—behaviour is documented and consistent.

---

### User Story 3 - Choose digest cadence (Priority: P2)

As a project admin, I choose digest frequency (e.g. hourly during quiet hours, or daily morning).

**Why this priority**: Teams differ; secondary to basic quiet hours + digest.

**Independent Test**: Set daily morning digest; assert run time aligns with configured timezone.

**Acceptance Scenarios**:

1. **Given** a daily digest at 08:00 project time, **When** the scheduler fires, **Then** delivery occurs in that local morning window.
2. **Given** cadence change, **When** I save, **Then** the next run uses the new cadence.

### Edge Cases

- Timezone changes mid-window: next evaluation uses the new timezone.
- Critical/high priority may optionally bypass quiet hours if configured; default is all digest-eligible types respect quiet hours.
- Channel outage during digest: failures follow existing notification retry/logging.
- No native PagerDuty connector is offered; HTTP webhooks remain the escape hatch.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Project notification settings MUST support quiet hours (start, end, timezone).
- **FR-002**: During quiet hours, configured notification types MUST be held or batched instead of immediate send (per settings).
- **FR-003**: System MUST deliver digests summarizing held notifications on a configured cadence.
- **FR-004**: Digests MUST be disable-able; quiet hours alone MUST NOT silently drop events without a documented flush/digest path.
- **FR-005**: Product MUST NOT ship a native PagerDuty integration in this feature.
- **FR-006**: Existing channel types (Slack, HTTP, etc. from project notifications) SHOULD remain usable as digest destinations where applicable.

### Key Entities

- **Quiet hours policy**: Window + timezone + optional bypass rules.
- **Digest policy**: Cadence, destination channels, enabled flag.
- **Held notification item**: Deferred payload awaiting digest/flush.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With quiet hours on, immediate sends for covered types drop to zero during the window in acceptance tests.
- **SC-002**: A digest after three held items produces exactly one outbound summary message per destination.
- **SC-003**: Admins can configure quiet hours and digest in under 3 minutes.
- **SC-004**: Documentation and UI state that PagerDuty is not natively supported; HTTP webhook remains available.

## Assumptions

- Extends `009-project-notifications` rather than replacing channel configuration.
- Default timezone is the project or server timezone already used elsewhere.
- "Critical bypass" is optional and off by default unless clarified in plan.
- Lifecycle webhooks (`017-export-webhooks`) are separate from human notification digests.
