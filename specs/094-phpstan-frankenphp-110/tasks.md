# Tasks: PHPStan FrankenPHP 1.1.0 production gate

**Input**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md)  
**Feature**: `094-phpstan-frankenphp-110`  
**Status**: Complete (implemented 2026-08-13)

## Phase 1: Setup

- [x] T001 Create `specs/094-phpstan-frankenphp-110/` + point `.specify/feature.json`
- [x] T002 [P] Read package UPGRADING/CHANGELOG 1.1.0 identifiers

## Phase 2: Dependency + config

- [x] T003 Pin `nowo-tech/phpstan-frankenphp` **1.1.0** in `composer.json` / lock
- [x] T004 Switch `phpstan.neon.dist` to `extension.neon` + `rules.neon`
- [x] T005 Add path-scoped `frankenphp.worker.noUmask` ignore for `tests/bootstrap.php`

## Phase 3: Verify + polish

- [x] T006 `make phpstan` green (`src/` clean under new rules)
- [x] T007 Remove obsolete unmatched `@phpstan-ignore` if PHPStan reports `ignore.unmatchedIdentifier`
- [x] T008 [P] Update `docs/ops/FRANKENPHP-CODING.md` PHPStan include wording
- [x] T009 [P] CHANGELOG Unreleased + correct v1.7.0 false **1.1.0** pin
- [x] T010 ROADMAP Phase **6.43** row

## Phase 4: QA

- [x] T011 `make qa-fix` (cs / twig-cs / phpstan / rector / PHPUnit)
- [x] T012 Vitest unit suite green
- [x] T013 Note e2e: ingest 401 env flake unrelated to this feature
