# Feature Specification: Monthly Event Quota

**Feature Branch**: `032-monthly-quota`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Add optional per-project **monthly** event quota alongside the existing daily quota (`018-project-governance`), with inherit-from-env behaviour and approaching-limit warnings.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure monthly quota (Priority: P1)

As a project admin (or instance admin), I set a monthly event cap (or leave empty to inherit env).

**Acceptance Scenarios**:

1. **Given** a monthly quota of M, **When** the month’s accepted events reach M, **Then** further ingest is rejected with a clear 429 (or documented status).
2. **Given** empty project override, **When** env monthly default is set, **Then** that default applies.

### User Story 2 - Approaching limit (Priority: P2)

As a project admin, I see a warning near 80% of the monthly quota (same spirit as daily).

## Requirements *(mandatory)*

- **FR-001**: `Project` supports nullable monthly quota override; env `BEACON_EVENT_QUOTA_MONTHLY` (0 = unlimited).
- **FR-002**: Ingest enforces monthly then/or daily rules as documented (both can apply).
- **FR-003**: Settings + Admin governance UIs expose the field.
- **FR-004**: Month boundary timezone documented (prefer UTC).

## Success Criteria

- **SC-001**: Tests cover under-limit accept and over-limit reject for monthly cap.
- **SC-002**: UPGRADING documents the new env var and migration.

## Out of scope

- Billing integrations / Stripe metering.
