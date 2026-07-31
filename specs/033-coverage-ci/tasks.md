# Tasks: CI Code Coverage Report

**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)  
**Prerequisites**: plan + research complete

## Phase 0: Spec & plan

- [x] T000 Draft `spec.md`
- [x] T001 Write `plan.md` / `research.md` / `data-model.md` / `contracts/` / `quickstart.md`
- [x] T002 Expand this `tasks.md`

## Phase 1: Foundational tooling (US1 + US2 shared)

- [x] T003 [P] Add `.scripts/check-coverage-threshold.sh` (Clover statements %; `COVERAGE_MIN` optional)
- [x] T004 Extend `Makefile` `test-coverage` to emit clover + HTML; run threshold script when `COVERAGE_MIN` set; document in `help`

## Phase 2: User Story 1 — Coverage artifact on CI (P1)

**Goal**: CI produces inspectable coverage report.  
**Independent test**: Coverage job green with artifact upload; missing clover fails clearly.

- [x] T005 Add `coverage` job to `.github/workflows/ci.yml` (PCOV, MySQL, PHPUnit clover/HTML, upload-artifact)
- [x] T006 Document CI failure modes in job comments / CONTRIBUTING (fail on PHPUnit or missing clover; warn-only soft gate when unset)

## Phase 3: User Story 2 — Optional soft threshold (P2)

**Goal**: Modest minimum % fails only when configured.  
**Independent test**: Empty `COVERAGE_MIN` → informational; set low/high values → pass/fail messaging.

- [x] T007 Wire `COVERAGE_MIN` (default empty) into coverage job + script; never hard-code 100%

## Phase 4: Polish & docs

- [x] T008 [P] Update `docs/CONTRIBUTING.md` with `make test-coverage` and soft gate
- [x] T009 [P] Update `docs/CHANGELOG.md` [Unreleased], `docs/UPGRADING.md`, `docs/ROADMAP.md` (5.9 / 6.7 → Done when shipped); mark `spec.md` Implemented
- [x] T010 Mark checklist `checklists/requirements.md` plan/implement items complete

## Dependencies

- T003–T004 before T005–T007  
- T005 before verifying T006  
- Docs (T008–T010) after tooling + CI  

## Implementation strategy

MVP = T003–T006 + T008 (informational coverage). Soft gate (T007) is a few lines once the script exists.
