# Tasks: Install & Seed Layers

**Input**: Design documents from `specs/055-install-seed-layers/`  
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/cli-seeds.md](./contracts/cli-seeds.md), [quickstart.md](./quickstart.md)

**Tests**: Included (FR-010 / constitution VIII).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1–US4 from spec.md

---

## Phase 1: Setup

**Purpose**: Align Make/docs placeholders and command stubs without changing behavior yet

- [x] T001 Add Makefile help lines and stub targets `seed-platform` / `seed-sample` (may temporarily call existing seeders) in `Makefile`
- [x] T002 [P] Add `docs/INSTALL.md` outline linking the three layers (fill commands in US4)
- [x] T003 [P] Mark spec status **Planned** in `specs/055-install-seed-layers/spec.md`

---

## Phase 2: Foundational

**Purpose**: Extract shared platform seeding entry point used by US1 and Make

- [x] T004 Create `App\Shared\Command\SeedPlatformCommand` (`app:seed-platform`) calling `DashboardMenuDemoSeeder` + `BreadcrumbDemoSeeder` only — file `src/Shared/Command/SeedPlatformCommand.php`
- [x] T005 Wire command autoconfiguration (Symfony attribute) and verify `bin/console list app:seed`
- [x] T006 Point `make seed-platform` at `app:seed-platform`; change `make bootstrap` to migrate + `seed-platform` only (not demo)

**Checkpoint**: Platform command runs on empty migrated DB without creating users

---

## Phase 3: User Story 1 — Platform seed (P1) 🎯 MVP

**Goal**: Idempotent navigation catalogs for install/upgrade  
**Independent test**: migrate → `app:seed-platform` twice → menus present, no duplicate routes; no demo user

- [x] T007 [US1] Ensure menu/breadcrumb seeders upsert (existing behavior) and surface clear success/note messages from `SeedPlatformCommand`
- [x] T008 [US1] PHPUnit `tests/Shared/SeedPlatformCommandTest.php`: run twice; assert menu/breadcrumb presence; assert zero `User` with demo email created
- [x] T009 [US1] Fail gracefully with actionable message if kit tables missing (migration not applied)

---

## Phase 4: User Story 2 — Demo seed slim (P1)

**Goal**: Identity + demo project + DSN only  
**Independent test**: after platform, `app:seed-demo` twice; `.demo-client.env` written; no analytics/perf seed side effects

- [x] T010 [US2] Remove calls to breadcrumb/menu/performance/analytics seeders from `src/Identity/Command/SeedDemoCommand.php`
- [x] T011 [US2] Add `--with-platform` option that optionally runs platform seed logic (or dispatches `seed-platform`)
- [x] T012 [US2] Update command description/PHPDoc to match contracts
- [x] T013 [US2] `make seed` runs `seed-platform` then `app:seed-demo`
- [x] T014 [US2] PHPUnit `tests/Identity/SeedDemoCommandTest.php`: create-once user/project; no `DailyProjectStat` / N+1 tx from demo alone

---

## Phase 5: User Story 3 — Sample profiles (P2)

**Goal**: Profiled telemetry + purge  
**Independent test**: `--profile=dev` then `--purge` on demo project

- [x] T015 [US3] Create `src/Issues/Service/IssueSampleSeeder.php` with batched issue/event generation for profile counts (see research.md)
- [x] T016 [US3] Create `src/Shared/Command/SeedSampleCommand.php` (`app:seed-sample`) with `--profile`, `--project`, `--purge`, `--force`
- [x] T017 [US3] Integrate `AnalyticsDemoSeeder` / `PerformanceDemoSeeder` into sample (extend windows/counts for load/huge as needed)
- [x] T018 [US3] Implement purge: delete issues/events/perf/stats for target project only
- [x] T019 [US3] Refuse `huge` without `--force`
- [x] T020 [US3] `make seed-sample` with `PROFILE ?= dev`
- [x] T021 [US3] PHPUnit `tests/Shared/SeedSampleCommandTest.php`: dev seed counts in range; purge clears telemetry; second project untouched

---

## Phase 6: User Story 4 — Documentation (P1)

**Goal**: Operator recipes match CLI  
**Independent test**: README/UPGRADING/Makefile help mention platform seed for upgrades

- [x] T022 [P] [US4] Complete `docs/INSTALL.md` from quickstart.md
- [x] T023 [P] [US4] Update `README.md` quick start (`bootstrap` → optional `make seed` / `seed-sample`)
- [x] T024 [P] [US4] Update `docs/UPGRADING.md` (0.12.2 → next): migrate + `app:seed-platform`; note demo/sample split
- [x] T025 [P] [US4] Update `docs/CHANGELOG.md` [Unreleased], `docs/CONTRIBUTING.md` doc map, `docs/DSN.md` seed references
- [x] T026 [US4] Grep/replace stale “re-run seed-demo for menus” wording in UPGRADING historical notes only where it would mislead (add forward-looking note, do not rewrite ancient sections wholesale)

---

## Phase 7: Polish

- [x] T027 Run PHPUnit seed tests + relevant smoke; fix CS if needed
- [x] T028 Mark `specs/055-install-seed-layers/spec.md` status **Implemented** when done; tick ROADMAP 6.4a when shipped
- [x] T029 Validate `quickstart.md` commands manually or via CI-equivalent compose exec

---

## Dependencies

```text
Phase 1 → Phase 2 → US1 (Phase 3) → US2 (Phase 4) → US3 (Phase 5)
US4 (Phase 6) can start after Phase 2 (docs stubs) and finish after US2/US3 command names are final
Phase 7 after US1–US4
```

## Parallel examples

- After T006: T008 tests can be written alongside T007
- After T010: T015–T016 (sample) can proceed in parallel with T014 (demo tests)
- T022–T025 are parallel once CLI names are stable

## Implementation strategy

1. **MVP**: Phase 2 + US1 + bootstrap/Make change + US2 slim demo + US4 doc minimum  
2. **Next**: US3 sample `dev` + purge  
3. **Then**: `load`/`huge` scaling + force guard  
4. Wizard UI: separate future spec
