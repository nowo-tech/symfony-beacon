# Feature Specification: Issue Search

**Feature Branch**: `016-issue-search`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Full-text issue search; filters for tag, URL, user (assignee/actor), and release; occurrence sorts for 24h / 7d / 30d must remain database-backed (SQL-only), not application-side.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Full-text search (Priority: P1)

As a project member, I search issues by free text (title/message) and get relevant matches quickly.

**Why this priority**: Text search is the primary discovery path beyond structured filters.

**Independent Test**: Seed issues with distinct titles; query a token; assert matching subset and empty-state for misses.

**Acceptance Scenarios**:

1. **Given** issues with different titles, **When** I search a distinctive phrase, **Then** matching issues appear and non-matches do not.
2. **Given** a query with no matches, **When** I search, **Then** I see a clear empty state.
3. **Given** an active search, **When** I clear it, **Then** the full unfiltered (or other-filter-only) list returns.

---

### User Story 2 - Structured filters (Priority: P1)

As a project member, I narrow issues by tag, request URL, user (assignee), and release in combination with search.

**Why this priority**: Structured filters make search actionable for large projects.

**Independent Test**: Apply each filter alone and combined; assert URL sync and result correctness.

**Acceptance Scenarios**:

1. **Given** events tagged `browser=chrome`, **When** I filter by that tag, **Then** only issues carrying the tag (via events or denormalized tags) appear.
2. **Given** events with distinct request URLs, **When** I filter by URL substring, **Then** matching issues appear.
3. **Given** assignees, **When** I filter by user, **Then** only issues assigned to that user appear (`unassigned` remains supported if already present).
4. **Given** release denormalization, **When** I filter by release, **Then** results align with release filter semantics from Releases.

---

### User Story 3 - Occurrence window sorts (Priority: P1)

As a project member, I sort the issues list by events in the last 24 hours, 7 days, or 30 days, and ordering is computed in the database.

**Why this priority**: Correct, scalable ordering for busy projects; explicit product constraint.

**Independent Test**: Seed known occurrence counts per window; sort each column; assert order matches DB counts (not a post-fetch reorder of a partial page).

**Acceptance Scenarios**:

1. **Given** issues with different 24h counts, **When** I sort by 24h descending, **Then** order matches stored window counts across pages.
2. **Given** sort by 7d or 30d, **When** I page results, **Then** global order remains consistent (no page-local resort).
3. **Given** equal window counts, **When** I sort, **Then** a stable secondary order applies (e.g. last seen).

### Edge Cases

- Search queries with special characters are treated as literals (or safely escaped), never errors.
- Tag filter with unknown key returns empty, not an error.
- URL filter is case-insensitive for host/path matching where reasonable.
- Combining full-text + multiple filters uses AND semantics.
- Very large result sets remain paginated; sorts stay correct across pages.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Issues list MUST support full-text search over issue title/message (and documented related fields).
- **FR-002**: Issues list MUST support filters for tag, URL, user/assignee, and release.
- **FR-003**: Filters and search MUST be combinable and reflected in the URL.
- **FR-004**: Sort by occurrence windows 24h, 7d, and 30d MUST be performed in the database (SQL-only), not by sorting a fetched page in application code.
- **FR-005**: Pagination MUST preserve global sort order for those window columns.
- **FR-006**: Empty and invalid filter inputs MUST yield empty or validation feedback without server errors.

### Key Entities

- **Issue search query**: Free-text string plus structured filter map.
- **Occurrence windows**: Existing 24h / 7d / 30d counts used for sort.
- **Tag / URL / Release / User filters**: Constraints applied to the issue query.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Typical project search returns the first page of results in under 2 seconds for up to 10k issues in acceptance environments.
- **SC-002**: Acceptance tests prove 24h/7d/30d sorts match fixture ordering across at least two pages.
- **SC-003**: Combined search + two filters yields only issues satisfying all constraints.
- **SC-004**: 95% of members in usability checks find a known issue via search within 30 seconds.

## Assumptions

- Builds on issues list from `004-issues` and release fields from `014-releases` (release filter may ship behind Releases).
- v1 implementation may use `LIKE` on title/culprit; **MySQL FULLTEXT** (or equivalent) is deferred to `029-issue-fulltext`.
- Tag filter matches event/issue tags already stored by ingest.
- No external search appliance (Elasticsearch, etc.) is required for v1.

## Out of scope (deferred)

- Production FULLTEXT indexes and ranking → `029-issue-fulltext`.
