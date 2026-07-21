# Feature Specification: Releases

**Feature Branch**: `014-releases`  
**Created**: 2026-07-21  
**Status**: In progress  

**Input**: Denormalize release context on Issues (`lastRelease`, `lastEnvironment`, `firstRelease`); filter issues by release; show a "New in release" badge; compare issue sets across environments.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See release context on issues (Priority: P1)

As a project member, I open the issues list or issue detail and see which release first introduced the issue and which release/environment last saw it, without opening every event.

**Why this priority**: Release context is the primary triage signal for "what broke in this deploy".

**Independent Test**: Ingest events with release/environment tags; open issues list and detail; assert denormalized fields display.

**Acceptance Scenarios**:

1. **Given** an issue with events across releases, **When** I open issue detail, **Then** I see first release, last release, and last environment.
2. **Given** a new event on an existing issue with a newer release, **When** ingest completes, **Then** last release (and last environment when present) update on the issue.
3. **Given** the first event for an issue, **When** ingest completes, **Then** first release is set and does not change on later events.

---

### User Story 2 - Filter issues by release (Priority: P1)

As a project member, I filter the issues list to a specific release to focus on regressions from that deploy.

**Why this priority**: Filtering is required to act on release-scoped triage.

**Independent Test**: Apply `release=` (or equivalent) filter; assert only matching issues appear and URL state persists.

**Acceptance Scenarios**:

1. **Given** issues spanning multiple releases, **When** I filter by a release version, **Then** only issues whose last or first release matches the chosen filter semantics are shown.
2. **Given** an empty or unknown release filter, **When** I apply it, **Then** the list is empty or shows a clear empty state (not an error).

---

### User Story 3 - New in release badge (Priority: P2)

As a project member, I quickly spot issues whose first occurrence was in a selected (or latest) release via a visible badge.

**Why this priority**: Speeds regression review after deploy; depends on first-release denormalization.

**Independent Test**: Seed issues first-seen in release A vs B; assert badge only on first-seen-in-A when A is in focus.

**Acceptance Scenarios**:

1. **Given** an issue whose first release equals the focused release, **When** the list or detail renders, **Then** a "New in release" badge is shown.
2. **Given** an issue that only recurred in the focused release, **When** the list renders, **Then** the badge is not shown.

---

### User Story 4 - Compare environments (Priority: P2)

As a project member, I compare which issues appear in one environment versus another (e.g. production vs staging) for the same project.

**Why this priority**: Valuable for promotion checks; secondary to single-release triage.

**Independent Test**: Seed issues with distinct last environments; open compare view; assert set differences.

**Acceptance Scenarios**:

1. **Given** two environments with overlapping issues, **When** I compare them, **Then** I see issues only in A, only in B, and in both.
2. **Given** an environment with no issues, **When** I compare, **Then** the empty side is clearly indicated.

### Edge Cases

- Events without a release tag leave first/last release unchanged (or unset if never seen).
- Release strings that differ only by whitespace or case are treated consistently (normalized display key).
- Extremely long release names are truncated in list UI without breaking layout.
- Concurrent ingest updating the same issue still converges to correct first/last release.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST maintain denormalized `firstRelease`, `lastRelease`, and `lastEnvironment` on each Issue from ingested event context.
- **FR-002**: `firstRelease` MUST be set once from the earliest event that carries a release and MUST NOT be overwritten by later events.
- **FR-003**: `lastRelease` and `lastEnvironment` MUST update when a newer event provides those values.
- **FR-004**: Issues list MUST support filtering by release.
- **FR-005**: UI MUST show a "New in release" badge when an issue's first release matches the focused release context.
- **FR-006**: System MUST provide an environment comparison view (issues only-A / only-B / both).
- **FR-007**: Project members MUST be able to read release fields; only ingest (or equivalent system path) MAY write denormalized release fields.

### Key Entities

- **Issue**: Gains first/last release and last environment denormalized fields.
- **Release focus**: Selected release used for filtering and "New in release" badge.
- **Environment pair**: Two named environments used in comparison.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After ingest of an event with release/environment, issue detail reflects updated last release/environment within one page refresh.
- **SC-002**: Members can filter the issues list to a single release and complete triage of that slice in under 2 minutes for a 50-issue result set.
- **SC-003**: "New in release" badge accuracy is 100% against first-release field for the focused release in acceptance tests.
- **SC-004**: Environment compare correctly classifies sample fixtures into only-A / only-B / both with no false overlaps.

## Assumptions

- Release and environment values arrive via existing event tags/context (no separate release registry required for v1).
- "Focused release" defaults to the most recently seen project release when not explicitly selected.
- Filter matches against last release by default; first-release-only filter may be added later.
- Compare is limited to two environments per view.
- Builds on completed Issues (`004-issues`) and ingest (`003-ingest`).
