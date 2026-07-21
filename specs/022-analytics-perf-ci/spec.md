# Feature Specification: Analytics and Performance CI Coverage

**Feature Branch**: `022-analytics-perf-ci`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Add functional tests for Analytics and Performance features and ensure they run in continuous integration.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Analytics functional coverage (Priority: P1)

As a maintainer, I rely on automated functional tests that exercise Analytics pages and key aggregations so regressions are caught before release.

**Why this priority**: Analytics is user-visible and easy to break with query changes.

**Independent Test**: Run the Analytics functional suite against fixtures; assert HTTP 200 and key markers/metrics for a seeded project.

**Acceptance Scenarios**:

1. **Given** a project with daily stats fixtures, **When** Analytics functional tests run, **Then** the Analytics UI (or documented endpoints) respond successfully and show expected aggregates.
2. **Given** a member session, **When** tests request Analytics, **Then** authorization behaves as production (members allowed, anonymous denied).
3. **Given** empty stats, **When** tests open Analytics, **Then** empty/zero states are asserted (no 500s).

---

### User Story 2 - Performance functional coverage (Priority: P1)

As a maintainer, functional tests cover Performance list/detail and N+1 indicators using ingested or seeded transactions.

**Why this priority**: Performance UI and detectors regress silently without browser/HTTP-level tests.

**Independent Test**: Seed or ingest a transaction with N+1-like spans; assert list/detail markers in functional tests.

**Acceptance Scenarios**:

1. **Given** stored transactions, **When** Performance list tests run, **Then** the page loads and lists expected transactions.
2. **Given** an N+1 candidate fixture, **When** detail tests run, **Then** candidate indicators are present.
3. **Given** `nplus1` filter behaviour, **When** covered by tests, **Then** filtered results match expectations.

---

### User Story 3 - CI gate (Priority: P1)

As a maintainer, Analytics and Performance functional tests run on every pull request / main CI pipeline and must pass.

**Why this priority**: Coverage without CI is not a quality gate.

**Independent Test**: Confirm CI config invokes the new test groups/suite; red build on deliberate failure.

**Acceptance Scenarios**:

1. **Given** the default CI pipeline, **When** a PR runs, **Then** Analytics and Performance functional tests execute.
2. **Given** a failing assertion in those tests, **When** CI runs, **Then** the pipeline fails.
3. **Given** documentation for contributors, **When** they run the documented local command, **Then** the same suites run locally.

### Edge Cases

- Flaky time-dependent stats: tests freeze clock or use fixed fixture dates.
- Parallel CI workers: tests isolate project fixtures to avoid cross-talk.
- Missing optional services in CI: suites skip only with explicit, documented markers—not silent pass.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Repository MUST include functional tests covering Analytics primary member flows.
- **FR-002**: Repository MUST include functional tests covering Performance list/detail and N+1 signalling.
- **FR-003**: CI MUST execute those tests on pull requests and protected branches.
- **FR-004**: Tests MUST assert authorization boundaries (anonymous vs member) for both areas.
- **FR-005**: Contributor docs or Makefile targets MUST document how to run the suites locally.
- **FR-006**: Tests MUST be deterministic (fixed fixtures / clocks) suitable for CI.

### Key Entities

- **Analytics fixture set**: Projects and daily stats used by tests.
- **Performance fixture set**: Transactions/spans including N+1 candidates.
- **CI job**: Pipeline step that runs the suites.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Analytics and Performance functional suites are green on main CI for a clean tree.
- **SC-002**: Deliberately breaking an Analytics assertion fails CI within one pipeline run.
- **SC-003**: Local documented command runs both suites without manual environment guesswork.
- **SC-004**: Suites complete within the project's existing CI time budget (no more than a modest documented increase).

## Assumptions

- Builds on completed Analytics (`005-analytics`) and Performance (`006-performance`) product behaviour.
- Uses the project's existing PHPUnit / functional testing harness and CI provider.
- Does not expand product UI scope; test-only / quality feature.
- “CI coverage” here means **functional suites run in CI**, not a code-coverage % gate.
- Browser e2e (Playwright, etc.) is optional and out of scope unless already standard in repo.

## Out of scope (deferred)

- PHPUnit code-coverage report / soft threshold in CI → `033-coverage-ci`.
