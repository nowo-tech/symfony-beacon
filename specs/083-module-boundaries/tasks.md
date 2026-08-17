# Tasks: Module boundary hardening

**Feature**: `083-module-boundaries`

## Phase A — P0 Shared freeze + Identity↔Project

- [x] T001 Move `IssueStatus`/`IssuePriority`/`IssueLevel` → `App\Issues\Enum`; `ProjectRole` → `App\Project\Enum`; update imports
- [x] T002 Shared growth rules in `docs/CONTRIBUTING.md` + ARCHITECTURE / constitution
- [x] T003 Move `AdminProjectController` Identity → `Project\Controller`; keep route names/URLs

## Phase B — P1 Issues + Ingest

- [x] T004 Extract list/search methods into `IssueSearchRepository`; thin `IssueRepository` lookups
- [x] T005 Move OTLP controllers/services under `App\Ingest\Otlp\`

## Phase C — P2 Messenger + hygiene + tests

- [x] T006 Separate Messenger transports: `async_ingest` vs `async`; Compose consume both
- [x] T007 Renumber `specs/079-ops-env-to-db` → `084-ops-env-to-db` + cross-refs
- [x] T008 Add Analytics period + Performance N+1 filter tests
- [x] T009 Mark spec Status Implemented; update ROADMAP 6.33 Done when focused CI green

## Phase D: Convergence (remaining vs FR-006)

Appended after implement (2026-08-06). Query/list split is Done; HTTP/mutation surface of `IssueController` remains a hotspot.

- [x] T010 Extract issue detail mutation actions into `IssueDetailController` (routes unchanged)
- [x] T011 Keep list/filters/saved views on `IssueController` (~list-only entry point)
- [x] T012 PHPUnit Issues / Analytics / Performance green after split
- [x] T013 Move platform/sample demo seeders to `Setup\Demo` (+ seed commands to `Setup\Command`)
- [x] T014 Expand Analytics series unit tests + Performance list coverage
- [x] T015 Document Identity must not reabsorb AdminProject (`docs/CONTRIBUTING.md`)

## Phase E: Residual P3 (re-audit follow-up)

- [x] T016 Split `AdminProjectController` → core + `AdminProjectAccessController` (members/groups)
- [x] T017 Split `ProjectController` → settings core + `ProjectApiKeyController` + `ProjectDangerZoneController`
- [x] T018 Analytics series release/level filter test + Performance pagination beyond last page
- [x] T019 CI/Make `check-module-boundaries` (AdminProject stays in Project)
- [x] T020 BP-004: script asserts `SqlLikeEscaper` + Project `EXTRA_LAZY`; ENGINEERING-AUDIT inventories; CONTRIBUTING §21
