# Implementation Plan: CI Code Coverage Report

**Branch**: `033-coverage-ci` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/033-coverage-ci/spec.md`

## Summary

Add a PHPUnit coverage job to GitHub Actions (PCOV + Clover/HTML artifacts) and document local `make test-coverage`. **Amendment (v1.19.0 / REQ-QA-002):** CI and Makefile default `COVERAGE_MIN=100` on the includable `src/` tree; Vitest hard-gates the TypeScript includable whitelist at 100%. Controllers / demo seed tooling remain excluded — see [docs/COVERAGE.md](../../docs/COVERAGE.md). Historical note: v0.16.0 shipped informational-only; F0.3 used soft `COVERAGE_MIN=35`.

## Technical Context

**Language/Version**: PHP 8.5 / PHPUnit 13 (existing suite)  
**Primary Dependencies**: `shivammathur/setup-php` with `coverage: pcov` in CI; Xdebug (`XDEBUG_MODE=coverage`) locally via Compose php image  
**Storage**: Artifacts under `var/coverage/` (clover) and `var/coverage-html/` (HTML); both under gitignored `/var/`  
**Testing**: Existing PHPUnit suite + expanded unit/integration coverage closing gaps to 100% includable statements  
**Target Platform**: GitHub Actions `ubuntu-latest` + local Docker Compose  
**Project Type**: web-service (CI/docs + coverage tests)  
**Performance Goals**: Coverage job may be slower than `qa`; keep main `qa` job on `coverage: none`  
**Constraints**: Hard floor `COVERAGE_MIN=100` on includable PHP; documented exclusions for controllers / install tooling  
**Scale/Scope**: Coverage job on PHP 8.5; clover statement %; Vitest V8 on assets whitelist

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- English docs / CONTRIBUTING / CHANGELOG / UPGRADING / ROADMAP — pass  
- Prefer kits — N/A (CI tooling)  
- No drive-by product refactors — pass (coverage-oriented micro-refactors only where needed for branchability)  
- Out of scope: mutation testing; 100% on excluded controllers/seed CLI — pass  

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
