# Tasks: SQL / database error context on issue detail

**Input**: Design documents from `/specs/107-sql-error-context/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/query-facts.md

**Tests**: Required (constitution VIII).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [x] T001 Spec directory `specs/107-sql-error-context/` + plan artifacts
- [x] T002 Update spec Q7/FR-015 for BeaconBundle 1.8.0 (in-scope)

## Phase 2: Foundational

- [x] T003 Add `Issue::CULPRIT_MAX_LENGTH` (255) + `#[ORM\Column(length: 255)]` + `setCulprit` truncate in `src/Issues/Entity/Issue.php`
- [x] T004 [P] Migration `migrations/Version20260826120000.php` — MySQL `ALTER TABLE issue CHANGE culprit VARCHAR(255)`; skip length change on SQLite
- [x] T005 [P] `IssuePanelIds::QUERY = 'query'` in `all()`; default still expanded
- [x] T006 Unit test culprit 255 + panel id in `tests/Unit/Issues/Entity/IssueEntityExtraTest.php` and `IssuePanelIdsTest.php`

**Checkpoint**: Foundation ready

## Phase 3: User Story 1 — Query panel (P1) 🎯 MVP

**Goal**: SQLSTATE / SQL visible on issue Main and event detail without Raw JSON.

- [x] T007 [US1] `src/Issues/Dto/QueryFacts.php` + `src/Issues/Service/QueryFactsExtractor.php` (precedence, chain, Laravel `(SQL:)`, SQLSTATE, `(1040, '…')`, truncation 8192)
- [x] T008 [P] [US1] `tests/Unit/Issues/Service/QueryFactsExtractorTest.php`
- [x] T009 [US1] `src/Issues/Twig/IssueEventTwigExtension.php` — `beacon_query_facts`
- [x] T010 [US1] Query panel + highlights in `templates/issue/_event_payload.html.twig`; hero subtitle in `templates/issue/show.html.twig`
- [x] T011 [P] [US1] English + locale strings `issues.query*` in `translations/messages.*.yaml`
- [x] T012 [US1] Extend `IssueErrorSurfacesFunctionalTest` — Query present / absent

**Checkpoint**: US1 demoable

## Phase 4: User Story 2 — In-app stack (P1)

**Goal**: Open innermost in-app frame; per-exception stacks.

- [x] T013 [US2] `src/Issues/Service/IssueStackPresenter.php` + Twig `beacon_stack_frames`
- [x] T014 [P] [US2] `tests/Unit/Issues/Service/IssueStackPresenterTest.php`
- [x] T015 [US2] `_event_payload.html.twig` — loop exception values with own frames; `open` on preferred frame

**Checkpoint**: US2 demoable

## Phase 5: User Story 3 — Culprit + AI export (P2)

- [x] T016 [US3] AI export optional `query` in `AiIssueExportFormatter.php` (keep v1) + unit/functional asserts
- [x] T017 [US3] Sample seeder first issue QueryException-like payload + `IssueSampleSeederTest`
- [x] T018 [P] [US3] `docs/product/EVENT-CONTEXT.md`, `docs/product/AI-EXPORT.md`, CHANGELOG/UPGRADING/ROADMAP notes

**Checkpoint**: US3 demoable

## Phase 6: BeaconBundle 1.8.0

- [x] T019 `src/Context/DatabaseExceptionContext.php` (PDO + method_exists DBAL + message parse; scrub SQL)
- [x] T020 EnvelopeBuilder merges `contexts.db` when non-empty
- [x] T021 Unit tests DatabaseExceptionContext + EnvelopeBuilder PDO
- [x] T022 Bundle docs CHANGELOG **1.8.0**, CONFIGURATION/USAGE note
- [x] T023 Run Bundle tests; prepare local release commit (no push)

## Phase 7: Polish

- [x] T024 PHPUnit Beacon filters for new tests; `make qa` subset if Docker is up
- [x] T025 Mark spec status Implemented (Unreleased) when green

## Dependencies

US1 → US2 can parallel after T009. US3 after extractor. Bundle independent of UI except contract shape.

## Implementation strategy

MVP = T003–T012 + T013–T015 (Query + stack). Then export/seed. Bundle 1.8.0 in parallel after extractor contract is frozen.
