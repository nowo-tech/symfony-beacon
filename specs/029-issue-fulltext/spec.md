# Feature Specification: Issue Full-Text Search

**Feature Branch**: `029-issue-fulltext`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Replace (or upgrade) the issue list `q` filter from `LIKE` on title/culprit to real full-text search (MySQL FULLTEXT or equivalent), with a documented SQLite/`LIKE` fallback for tests.

**Depends on**: `016-issue-search` (filters and SQL sorts already shipped).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Search issues by words (Priority: P1)

As a project member, I type natural query terms and get relevant issues ranked or filtered by full-text match on title/culprit (and optionally a payload excerpt).

**Acceptance Scenarios**:

1. **Given** issues with distinctive titles, **When** I search a token present in one title, **Then** that issue appears and unrelated issues do not.
2. **Given** an empty `q`, **When** I list issues, **Then** behaviour matches today’s unfiltered list.
3. **Given** SQLite/test environment without FULLTEXT, **When** tests run, **Then** a documented `LIKE` fallback keeps suites green.

### User Story 2 - Combine with existing filters (Priority: P1)

As a project member, full-text `q` works together with status, level, environment, release, tag, URL, user, and priority filters, with SQL pagination still correct.

**Acceptance Scenarios**:

1. **Given** `q` plus `status` / `release`, **When** I page results, **Then** counts and pages remain consistent (no PHP full-set sort regression).

## Requirements *(mandatory)*

- **FR-001**: Production MySQL MUST use FULLTEXT (or equivalent) for the primary `q` path on title/culprit at minimum.
- **FR-002**: Test/SQLite path MUST fall back safely without fatal errors.
- **FR-003**: Pagination and SQL-only sorts from `016` MUST remain valid with full-text predicates.
- **FR-004**: Docs MUST state which columns are indexed and any stopword / minimum token length limits.

## Success Criteria

- **SC-001**: Functional or repository tests prove token match vs non-match.
- **SC-002**: Migration adds indexes without breaking existing installs (UPGRADING notes).

## Out of scope

- Elasticsearch / OpenSearch cluster.
- Semantic / vector search.
