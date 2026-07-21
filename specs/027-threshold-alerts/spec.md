# Feature Specification: Threshold Alerts

**Feature Branch**: `027-threshold-alerts`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Alert when error/event volume exceeds a configurable threshold in a short window (e.g. more than N errors in 15 minutes), in addition to existing `issue.new` / `issue.regression` notifications. Reuse digests, Messenger delivery, and project health signals.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure a volume threshold (Priority: P1)

As a project admin, I define a rule such as “more than N errors in M minutes” so the team is notified of sudden spikes without waiting for a new fingerprint.

**Acceptance Scenarios**:

1. **Given** I am a project owner/admin, **When** I configure a threshold (count, window, optional env/release scope), **Then** it is stored per project and shown in Settings.
2. **Given** an invalid threshold (count &lt; 1 or window outside allowed bounds), **When** I save, **Then** validation fails with a clear message.

### User Story 2 - Fire when the window is exceeded (Priority: P1)

As an on-call operator, I receive a notification when the threshold is crossed, using existing destinations and categories.

**Acceptance Scenarios**:

1. **Given** an enabled threshold and matching destinations, **When** ingest pushes volume over the limit inside the window, **Then** a threshold notification is attempted (once per cooldown).
2. **Given** volume stays under the limit, **When** time passes, **Then** no threshold notification is sent.
3. **Given** a recent threshold alert for the same rule, **When** volume remains high within cooldown, **Then** duplicate spam is suppressed.

### User Story 3 - Visibility in health / digests (Priority: P2)

As a project admin, I can see that a threshold fired (last fire time / failure) alongside destination health, and optional digest summaries can include threshold events.

**Acceptance Scenarios**:

1. **Given** a threshold fired, **When** I open project health or notification settings, **Then** last threshold outcome is visible or linked.
2. **Given** digests enabled, **When** a threshold event was buffered or flushed, **Then** documentation describes how it appears in the summary.

### Edge Cases

- Project with ingest suspended: no threshold evaluation or no notify (document one behaviour).
- Clock skew / worker delay: evaluation uses server time consistently.
- Multiple destinations: each matching destination is notified independently (same as other categories).

## Requirements *(mandatory)*

- **FR-001**: Projects MUST support at least one configurable volume threshold (count + window minutes).
- **FR-002**: Crossing the threshold MUST dispatch via the existing notification pipeline (new category e.g. `issue.threshold` or `volume.threshold`).
- **FR-003**: A cooldown MUST prevent alert storms for the same rule.
- **FR-004**: Optional filters for environment and/or release SHOULD be supported when those dimensions exist.
- **FR-005**: Member-facing Settings UI for create/edit/enable/disable (owner/admin).

## Success Criteria

- **SC-001**: Functional test can seed volume and assert a threshold notification is queued once.
- **SC-002**: Cooldown prevents a second notify within the configured silence period.
- **SC-003**: NOTIFICATIONS.md documents the category and payload fields.

## Assumptions

- Reuses Messenger async delivery, SSRF guards, quiet hours/digest where applicable.
- Not a full anomaly-detection product—simple rolling counters only.

## Out of scope

- Native PagerDuty.
- ML-based anomaly detection.
- Instance-wide cross-project thresholds.
