# Implementation Plan: CI Code Coverage Report

**Branch**: `033-coverage-ci` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/033-coverage-ci/spec.md`

## Summary

Add an informational PHPUnit coverage job to GitHub Actions (PCOV + Clover/HTML artifacts) and document local `make test-coverage`. Soft minimum % is opt-in via `COVERAGE_MIN` (unset by default so releases are never blocked by a 100% or aggressive gate). Never enforce 100% coverage.

## Technical Context

**Language/Version**: PHP 8.5 / PHPUnit 13 (existing suite)  
**Primary Dependencies**: `shivammathur/setup-php` with `coverage: pcov` in CI; Xdebug (`XDEBUG_MODE=coverage`) locally via Compose php image  
**Storage**: Artifacts under `var/coverage/` (clover) and `var/coverage-html/` (HTML); both under gitignored `/var/`  
**Testing**: Existing PHPUnit suite; no new product tests required — validate via CI job + threshold helper script  
**Target Platform**: GitHub Actions `ubuntu-latest` + local Docker Compose  
**Project Type**: web-service (CI/docs slice only)  
**Performance Goals**: Coverage job may be slower than `qa`; keep main `qa` job on `coverage: none`  
**Constraints**: Soft gate never defaults to 100%; FR-003 — no threshold until maintainers set `COVERAGE_MIN`  
**Scale/Scope**: Single coverage job on PHP 8.5 matrix; clover statement % only

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- English docs / CONTRIBUTING / CHANGELOG / UPGRADING / ROADMAP — pass  
- Prefer kits — N/A (CI tooling)  
- No drive-by product refactors — pass  
- Out of scope: mutation testing, 100% gate — pass  

## Project Structure

### Documentation (this feature)

```text
specs/033-coverage-ci/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/coverage-report.md
└── tasks.md
```

### Source Code

```text
.github/workflows/ci.yml          # coverage job (PCOV, artifacts, optional soft gate)
.scripts/check-coverage-threshold.sh
Makefile                          # test-coverage + help; optional COVERAGE_MIN
docs/CONTRIBUTING.md
docs/CHANGELOG.md
docs/ROADMAP.md
docs/UPGRADING.md
```

## Complexity Tracking

| Violation | Why needed | Simpler alternative rejected because |
|-----------|------------|--------------------------------------|
| — | — | — |
