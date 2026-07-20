# Feature Specification: Performance

**Feature Branch**: `006-performance`  
**Created**: 2026-07-19  
**Status**: Completed (as-built through v0.6.0)  

**Input**: Store transaction/span telemetry from Envelope ingest and surface N+1 candidates in the project UI.

## Summary

Transaction envelopes create `Transaction` + `Span` records. On ingest, `NPlusOneDetector` flags groups of similar db-like spans (≥ **5** repeats). The performance UI lists transactions, supports `?nplus1=1`, and marks candidate spans on detail. N+1 groups are **not** persisted as entities—only counts and span flags.

Daily N+1 counts also feed analytics (`DailyProjectStat`).

## Clarifications (as-built)

- **Detection ops**: Spans whose `op` starts with `db` / `sql`, contains `query`, or is `http.client`.
- **Similarity**: Normalized span `description` (query pattern) within the same op.
- **Threshold**: At least **5** repeats in one transaction to form a group.
- **Persistence**: Candidate span ids / flags on spans; group list is computed at detect time for UI, not a separate table.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse transactions (Priority: P1)

As a project member, I can open `/projects/{id}/performance` and see recent transactions with duration and N+1 group count.

**Independent Test**: Ingest a transaction envelope; open performance list; assert the transaction appears.

**Acceptance Scenarios**:

1. **Given** stored transactions, **When** I open Performance, **Then** I see a list with identifying fields and N+1 indicators when present.
2. **Given** `?nplus1=1`, **When** the list loads, **Then** only transactions with at least one N+1 group are shown.

### User Story 2 - Inspect spans and N+1 (Priority: P1)

As a developer, I open a transaction and see spans; N+1 candidate spans are highlighted.

**Independent Test**: Build spans with ≥5 similar db ops; open detail; assert group count and candidate marks.

**Acceptance Scenarios**:

1. **Given** a transaction with N+1 groups, **When** I open detail, **Then** the UI shows the group count and marks `n_plus_one_candidate` spans.
2. **Given** fewer than 5 similar db-like spans, **When** detection runs, **Then** no N+1 group is reported.

### User Story 3 - Ingest path (Priority: P1)

As ingest, transaction items create transactions/spans and run N+1 detection before persistence flags are set.

**Independent Test**: Process a transaction envelope in Messenger; assert entities and daily N+1 increment when groups exist.

**Acceptance Scenarios**:

1. **Given** a valid transaction item, **When** processed, **Then** transaction and spans are stored.
2. **Given** N+1 groups detected, **When** processed, **Then** daily project stats N+1 counter increments.

## Requirements *(mandatory)*

- **FR-001**: Persist transactions and child spans from Envelope transaction items.
- **FR-002**: Run `NPlusOneDetector` with MIN_REPEATS = 5 on db-like ops.
- **FR-003**: Expose `/projects/{id}/performance` list + detail; support `nplus1=1` filter.
- **FR-004**: Mark candidate spans for UI highlighting; do not require a separate N+1 entity table.
- **FR-005**: Contribute N+1 counts to daily analytics when groups are found.

## Success Criteria *(mandatory)*

- **SC-001**: Operators can filter to N+1-only transactions and see which spans participated.
- **SC-002**: PHPUnit covers detector threshold/normalization behaviour.
