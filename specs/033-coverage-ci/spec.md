# Feature Specification: CI Code Coverage Report

**Feature Branch**: `033-coverage-ci`  
**Created**: 2026-07-21  
**Status**: Draft  

**Input**: Add a non-blocking (initially) code coverage report job to CI for PHPUnit, without enforcing an aggressive 100% gate. Optional soft threshold only after a baseline exists (`022` already runs functional tests).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Coverage artifact on CI (Priority: P1)

As a maintainer, each main/PR pipeline can produce a coverage report (clover/HTML) for inspection.

**Acceptance Scenarios**:

1. **Given** CI with Xdebug or PCOV available, **When** the coverage job runs, **Then** a report artifact is uploaded or summary is visible.
2. **Given** coverage job fails to generate a report, **When** the pipeline finishes, **Then** failure mode is documented (fail job vs warn).

### User Story 2 - Optional soft threshold (Priority: P2)

As a maintainer, after a baseline exists, a modest minimum % may fail the job—never “100% required”.

**Acceptance Scenarios**:

1. **Given** a configured soft threshold, **When** coverage drops below it, **Then** CI fails with a clear message.
2. **Given** no threshold configured, **When** coverage runs, **Then** the job is informational only.

## Requirements *(mandatory)*

- **FR-001**: Document how to run coverage locally (`composer` / `make` target).
- **FR-002**: CI workflow generates coverage without requiring 100%.
- **FR-003**: Default must not block releases until a baseline is agreed.

## Success Criteria

- **SC-001**: CONTRIBUTING mentions the coverage command.
- **SC-002**: At least one CI run on main produces a coverage artifact or summary.

## Out of scope

- Mutation testing.
- Enforcing 100% line coverage.
