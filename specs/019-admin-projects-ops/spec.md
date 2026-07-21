# Feature Specification: Admin Projects Ops

**Feature Branch**: `019-admin-projects-ops`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Platform admin statistics across projects; suspend ingest per project; admin audit trail; view-as-member for support.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin project statistics (Priority: P1)

As a platform admin, I see aggregate stats (projects, events, issues, errors) to understand fleet health.

**Why this priority**: Operators of multi-project Beacon need a control-plane overview.

**Independent Test**: Seed known counts; open admin stats; assert totals match fixtures.

**Acceptance Scenarios**:

1. **Given** multiple projects with events, **When** I open admin stats, **Then** I see per-project and/or aggregate counts for key metrics.
2. **Given** I am not a platform admin, **When** I request admin stats, **Then** access is denied.

---

### User Story 2 - Suspend ingest (Priority: P1)

As a platform admin, I suspend ingest for a misbehaving or non-paying project without deleting data.

**Why this priority**: Emergency control for abuse and incident response.

**Independent Test**: Suspend project; send envelope; assert rejection; unsuspend; assert success.

**Acceptance Scenarios**:

1. **Given** an active project, **When** I suspend ingest, **Then** new envelopes are rejected with a clear error.
2. **Given** a suspended project, **When** I unsuspend, **Then** ingest works again.
3. **Given** suspension, **When** members browse the UI, **Then** they can still read existing data (unless separately restricted).

---

### User Story 3 - Admin audit trail (Priority: P2)

As a platform admin, I review an audit log of privileged actions (suspend, view-as, governance overrides).

**Why this priority**: Accountability for support actions.

**Independent Test**: Perform suspend; open audit; assert entry with actor, action, target, time.

**Acceptance Scenarios**:

1. **Given** I suspend a project, **When** I open admin audit, **Then** a corresponding entry exists.
2. **Given** audit entries, **When** I filter by project or actor, **Then** results narrow correctly.

---

### User Story 4 - View as member (Priority: P2)

As a platform admin, I temporarily view a project as a specific member would, for support debugging.

**Why this priority**: Reduces back-and-forth; must be audited.

**Independent Test**: Enter view-as; assert UI scoped like that member; exit; assert admin context restored; audit recorded.

**Acceptance Scenarios**:

1. **Given** a project member, **When** I start view-as-member, **Then** I see that project's member-facing UI with a clear impersonation banner.
2. **Given** view-as active, **When** I exit, **Then** I return to admin context.
3. **Given** view-as was used, **When** I check audit, **Then** start and end (or session) are recorded.

### Edge Cases

- Suspended project API keys still authenticate as "known" but ingest is refused with suspend-specific messaging.
- View-as cannot escalate beyond the target member's permissions.
- Concurrent suspend by two admins is idempotent.
- Audit log retention follows platform policy (not infinite by default).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Platform admins MUST access a stats overview of projects and usage metrics.
- **FR-002**: Platform admins MUST be able to suspend and unsuspend ingest per project.
- **FR-003**: Suspended projects MUST reject ingest while preserving stored data for read access.
- **FR-004**: Privileged admin actions MUST be written to an admin audit trail.
- **FR-005**: Platform admins MUST be able to view-as a project member with an obvious UI indicator and audited session.
- **FR-006**: Non-admins MUST NOT access admin ops endpoints or UI.

### Key Entities

- **Admin stats snapshot**: Aggregate and per-project metrics.
- **Project ingest suspension**: Flag/state with actor and timestamp.
- **Admin audit entry**: Actor, action, target, metadata, time.
- **View-as session**: Admin, target member, project scope, start/end.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins locate fleet stats and identify a high-volume project in under 1 minute in acceptance UX checks.
- **SC-002**: Suspend blocks ingest with 100% rejection in tests; unsuspend restores success.
- **SC-003**: Every suspend and view-as start appears in audit within the same request cycle (visible on next audit page load).
- **SC-004**: View-as never grants permissions the target member lacks (verified by negative tests).

## Assumptions

- "Platform admin" maps to an existing elevated role (e.g. `ROLE_ADMIN`); exact role name is an implementation detail.
- Prefer `nowo-tech/audit-kit-bundle` or existing audit patterns for trail storage where suitable.
- View-as is support-only; not a permanent membership grant.
- Legal/privacy: operators should disclose impersonation in their policies when offering hosted Beacon.
