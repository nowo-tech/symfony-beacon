# Feature Specification: Release Health

**Feature Branch**: `028-release-health`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: A project panel that shows release health using existing denormalized release fields (`014-releases`): new issues per release, comparison across releases/environments, without requiring operators to reconstruct it from the issue list alone.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See new issues for a release (Priority: P1)

As a project member, I open a Release health view, pick a release, and see how many issues were first seen in that release (and links into the filtered issue list).

**Acceptance Scenarios**:

1. **Given** issues with `firstRelease` set, **When** I select that release, **Then** I see a count and list/summary of “new in release” issues.
2. **Given** a release with no first-seen issues, **When** I select it, **Then** I see an empty state (not an error).

### User Story 2 - Compare two releases or environments (Priority: P2)

As a project member, I compare issue sets between two releases or reuse environment compare deep-links from `014`.

**Acceptance Scenarios**:

1. **Given** two releases with overlapping fingerprints, **When** I compare them, **Then** I see only-A / only-B / both style summaries or links.
2. **Given** environment compare already exists on the issue list, **When** Release health ships, **Then** it links or embeds that capability without regressing list filters.

### Edge Cases

- Releases known only from events (not yet on issues) may appear via distinct last/first release values.
- Very long release strings truncated consistently with issue list UI.

## Requirements *(mandatory)*

- **FR-001**: Provide a project-scoped Release health surface (nav entry or Analytics sibling).
- **FR-002**: Show “new in release” counts derived from `firstRelease` (and link to `?release=` filtered issues).
- **FR-003**: List distinct releases available for the project (from issues and/or events).
- **FR-004**: Members can view; mutate not required.

## Success Criteria

- **SC-001**: Seeded releases show correct new-issue counts in functional tests.
- **SC-002**: Deep link from Release health to filtered issues preserves query semantics from `014`.

## Assumptions

- Builds on `014-releases` denormalization; no new ingest protocol.
- Optional `project_release` dimension table remains deferred unless listing becomes hot.

## Out of scope

- Deploy markers from CI/CD webhooks.
- Source maps / release artifacts upload.
