# Feature Specification: Issue Workflow

**Feature Branch**: `015-issue-workflow`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Issue comments; priority (`low` | `medium` | `high` | `critical`); mark-as-duplicate (link + ignored); merge later; saved views for issue lists.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Comment on an issue (Priority: P1)

As a project member, I add comments on an issue so the team can discuss triage without leaving Beacon.

**Independent Test**: Post a comment; reload detail; assert author, body, and timestamp.

**Acceptance Scenarios**:

1. **Given** an issue, **When** I submit a non-empty comment, **Then** it appears on the issue timeline with my identity and time.
2. **Given** an empty comment, **When** I submit, **Then** validation prevents save.
3. **Given** a non-member, **When** they attempt to comment, **Then** the action is rejected.

---

### User Story 2 - Set priority (Priority: P1)

As a project member, I set issue priority to `low`, `medium`, `high`, or `critical` and filter/sort by it.

**Independent Test**: Change priority; assert persistence and list filter.

**Acceptance Scenarios**:

1. **Given** an unresolved issue, **When** I set priority to `critical`, **Then** the badge updates and list filters can include it.
2. **Given** an invalid priority value, **When** I submit, **Then** the change is rejected.
3. **Given** a new issue with no priority set, **When** it is created, **Then** default priority is `medium`.

---

### User Story 3 - Mark as duplicate (Priority: P1)

As a project member, I mark an issue as a duplicate of another: it links to the canonical issue and becomes ignored.

**Independent Test**: Mark A duplicate of B; assert link, ignored status, and B remains open.

**Acceptance Scenarios**:

1. **Given** issues A and B in the same project, **When** I mark A as duplicate of B, **Then** A is ignored, stores a link to B, and shows the relationship in UI.
2. **Given** A is marked duplicate of B, **When** I open A, **Then** I can navigate to B in one click.
3. **Given** I attempt to mark an issue as duplicate of itself, **When** I submit, **Then** the action is rejected.

---

### User Story 4 - Saved views (Priority: P2)

As a project member, I save named filter/sort combinations and reopen them later.

**Independent Test**: Save a view; reload; open it; assert URL/list state matches.

**Acceptance Scenarios**:

1. **Given** active filters, **When** I save a named view, **Then** it appears in my project's saved views list.
2. **Given** a saved view, **When** I open it, **Then** the issues list applies the stored filters and sort.
3. **Given** a saved view I own, **When** I delete it, **Then** it no longer appears.

---

### User Story 5 - Merge later (Priority: P2)

As a project member, I queue two issues for a future merge without merging event streams yet.

**Independent Test**: Flag A and B for merge-later; assert relationship is listed and reversible.

**Acceptance Scenarios**:

1. **Given** two issues, **When** I queue them for merge later, **Then** both show a pending-merge relationship.
2. **Given** a pending merge, **When** I cancel it, **Then** the relationship is removed and issues are otherwise unchanged.

### Edge Cases

- Duplicate of an already-ignored or resolved issue is allowed; canonical unchanged.
- Circular duplicate chains (A→B when B→A) are rejected.
- Overlong comments are rejected with a clear message; obsolete saved-view keys are ignored.
- Merge-later does not move events or change fingerprints.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Project members MUST be able to create, list, and read comments on issues in their project.
- **FR-002**: Issues MUST support priority `low` | `medium` | `high` | `critical` with default `medium`.
- **FR-003**: Members MUST be able to set priority and filter the issues list by priority.
- **FR-004**: Members MUST be able to mark an issue as duplicate of another same-project issue (link + ignored).
- **FR-005**: System MUST prevent self-duplicates and obvious circular duplicate links.
- **FR-006**: Members MUST be able to create, open, and delete named saved views of list filters/sort.
- **FR-007**: Members MUST be able to queue and cancel a merge-later relationship without merging event data.
- **FR-008**: Priority, duplicate, and comment actions MUST be attributable to the acting member where history exists.

### Key Entities

- **IssueComment**: Author, body, timestamps, issue reference.
- **Issue priority**: Enum on Issue.
- **Duplicate link**: Source → canonical; source ignored.
- **SavedView**: Name, owner/project scope, serialized filters/sort.
- **MergeLater**: Pending pair (non-destructive).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Members can post a comment and see it on reload in under 10 seconds.
- **SC-002**: Setting priority and filtering works for all four levels in acceptance tests.
- **SC-003**: Duplicate flow leaves source ignored and linked with 100% correctness on fixtures.
- **SC-004**: Saved views restore the same filter/sort state they were saved with.
- **SC-005**: Merge-later never alters event counts or fingerprints in this release.

## Assumptions

- Comments are plain text (Markdown optional later).
- Saved views are per-user within a project (not shared team-wide in v1).
- Full issue merge (fingerprints/events) is out of scope; only "merge later" intent.
- Builds on `004-issues` status/assignee/history behaviour.
