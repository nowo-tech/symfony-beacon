# Feature Specification: CI Code Coverage Report

**Feature Branch**: `033-coverage-ci`  
**Created**: 2026-07-21  
**Status**: Implemented — **v1.19.0** closes REQ-QA-002 with a hard `COVERAGE_MIN=100` gate on the includable PHPUnit `src/` tree (and Vitest 100% on the TypeScript includable set). Earlier: **v0.16.0** informational job; F0.3 soft `COVERAGE_MIN=35`.

**Input**: PHPUnit coverage report job in CI (PCOV + Clover/HTML). Baseline soft gate first; once the includable tree reaches 100%, enforce that floor. Controllers and install/demo tooling stay excluded (e2e / seed owned) — see [docs/COVERAGE.md](../../docs/COVERAGE.md).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Coverage artifact on CI (Priority: P1)

As a maintainer, each main/PR pipeline can produce a coverage report (clover/HTML) for inspection.

**Acceptance Scenarios**:

1. **Given** CI with Xdebug or PCOV available, **When** the coverage job runs, **Then** a report artifact is uploaded or summary is visible.
2. **Given** coverage job fails to generate a report, **When** the pipeline finishes, **Then** failure mode is documented (fail job vs warn).

### User Story 2 - Hard floor on includable source (Priority: P1)

As a maintainer, after the includable `src/` tree reaches 100% statement coverage, CI fails if coverage drops below `COVERAGE_MIN` (default **100**). Local diagnosis may override (`COVERAGE_MIN=0`).

**Acceptance Scenarios**:

1. **Given** `COVERAGE_MIN=100` (CI / Makefile default), **When** statement % on includable source is below 100, **Then** CI fails with a clear message.
2. **Given** a local override `COVERAGE_MIN=0`, **When** coverage runs, **Then** the threshold script does not fail the run (diagnosis only).
3. **Given** documented PHPUnit `<source><exclude>` paths (controllers, demo seeders, dogfood CLI), **When** coverage is measured, **Then** those paths are not required for the 100% floor.

## Requirements *(mandatory)*

- **FR-001**: Document how to run coverage locally (`make test-coverage` / Vitest) and the exclusion inventory ([docs/COVERAGE.md](../../docs/COVERAGE.md)).
- **FR-002**: CI Coverage job uploads Clover + HTML and enforces `COVERAGE_MIN` (default **100** on includable PHP).
- **FR-003**: Controllers remain e2e/Functional-owned via PHPUnit source exclusions; Vitest hard-gates a documented TypeScript includable whitelist at 100%.

## Success Criteria

- **SC-001**: CONTRIBUTING and [docs/COVERAGE.md](../../docs/COVERAGE.md) document coverage commands and the hard gate.
- **SC-002**: CI Coverage job on main produces artifacts and fails when includable statement % &lt; 100.
- **SC-003**: Measured PHP Clover statements on includable `src/` are **100%**; Vitest includable set meets lines/statements **100**.

## Out of scope

- Mutation testing.
- Requiring 100% on HTTP controllers, canvas/WebGL engines, or demo/install seed commands (excluded by design).

## Amendment (Unreleased / `106`, 2026-08-25)

Includable PHP Clover **100%** restored after Ops/ingest/security additions (`MessengerQueueHealth`, `EventQuotaUsageStore`, `RetentionPurger` batches, `PrivateNetworkTarget`, legacy API-key command). PHPUnit `tests/` MUST stay PHPStan-clean at level 6 without `ignoreErrors` / baseline rows (`094` / `106` H10). Vitest whitelist 100% unchanged.
