# Research: CI Code Coverage Report

## Decision: PCOV in CI, Xdebug locally

**Decision**: GitHub Actions uses `coverage: pcov` via `setup-php`. Local `make test-coverage` keeps `XDEBUG_MODE=coverage` (Xdebug already installed in the FrankenPHP dev image).

**Rationale**: PCOV is faster and sufficient for line/statement coverage in CI. Local Docker already ships Xdebug for debugging; avoiding a second extension install keeps `make test-coverage` working today.

**Alternatives considered**:
- Xdebug in CI → slower suite, more flaky under matrix
- Require PCOV in Compose → image change without debugging benefit

## Decision: Separate `coverage` job

**Decision**: Add a `coverage` job alongside `qa`, not replace `qa` PHPUnit with coverage-enabled PHP.

**Rationale**: Spec wants an inspectable report without slowing the primary quality gate (CS / PHPStan / Rector / tests with `coverage: none`).

**Alternatives considered**:
- Single job with PCOV always on → slows every PR for CS/PHPStan steps that do not need coverage
- `needs: qa` then re-run tests → doubles wall time worst-case; acceptable but artifact job can stand alone with MySQL service

## Decision: Informational by default; soft gate via `COVERAGE_MIN`

**Decision (original)**: Upload Clover + HTML artifacts always. Enforce a minimum statement % only when env `COVERAGE_MIN` is non-empty (workflow env / repo variable). Default empty → job fails only if PHPUnit fails or Clover is missing.

**Rationale (original)**: FR-002 / FR-003 — do not block releases until a baseline is agreed. Soft threshold remains available after maintainers record a baseline.

**Amendment (v1.19.0 / REQ-QA-002)**: After the includable `src/` tree reached 100% statements, CI and Makefile default `COVERAGE_MIN=100`. Controllers / demo seed CLI stay excluded (documented in `docs/COVERAGE.md`). Local diagnosis may still use `COVERAGE_MIN=0`.

**Alternatives considered**:
- `continue-on-error: true` on coverage → hides real test failures in that job
- Hard-coded threshold in YAML without exclusions → premature / flaky on controllers
- Keep soft 35% forever → diverges from platform QA-002 once baseline is closed

## Decision: Clover statement metrics for the gate

**Decision**: `.scripts/check-coverage-threshold.sh` reads `project/metrics` coveredstatements / statements from Clover XML.

**Rationale**: Stable, machine-readable, works with PHPUnit `--coverage-clover`. Statement % is the usual soft gate; branch coverage left out of scope.

**Alternatives considered**:
- Codecov / Coveralls upload → extra third-party account; optional later
- Parse `--coverage-text` only → brittle
