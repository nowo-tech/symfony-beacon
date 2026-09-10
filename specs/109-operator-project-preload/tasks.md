# Tasks: Operator project preload (`109`)

**Status**: All complete — shipped **v1.25.0** (2026-09-09); spec folder added 2026-09-10

## Phase 1: Portability + UUID

- [x] T001 UUID validation in `assignUuid` / normalize
- [x] T002 `uuid_mismatch` / `uuid_conflict` / `countValidatedProjects`

## Phase 2: Command + docs + tests

- [x] T003 `app:preload-projects` (dry-run + transaction + api-keys)
- [x] T004 INSTALL / PRODUCTION notes
- [x] T005 PHPUnit (`PreloadProjectsCommandTest`, portability, `PublicUuidAssignTest`)
- [x] T006 Spec kit folder + ROADMAP Phase 6.61 (retroactive)
