# Implementation Plan: Install & Seed Layers

**Branch**: `055-install-seed-layers`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/055-install-seed-layers/spec.md`

## Summary

Split the overloaded `app:seed-demo` into three CLI layers: **platform** (idempotent menus/breadcrumbs for install + upgrade), **demo** (local admin + demo project + `.demo-client.env`), and **sample** (profiled telemetry for QA/load with purge). Update Make targets and operator docs so upgrades run `migrate` + `app:seed-platform` instead of re-running demo seed for navigation.

## Technical Context

| Area | Decision |
|------|----------|
| **Language/Version** | PHP 8.5 / Symfony 8.1 |
| **Primary Dependencies** | Existing seeders (`DashboardMenuDemoSeeder`, `BreadcrumbDemoSeeder`, `AnalyticsDemoSeeder`, `PerformanceDemoSeeder`); Doctrine ORM; Symfony Console |
| **Storage** | MySQL via current entities; **no new tables for MVP** (sample purge scoped to target project telemetry) |
| **Testing** | PHPUnit functional/command tests under `tests/` |
| **Target Platform** | Docker Compose `php` service (`make …`) |
| **Project Type** | Modular Symfony CLI + docs |
| **Performance Goals** | Profile `dev` &lt; 5 minutes on a developer machine; `load`/`huge` batch inserts with `EntityManager::clear()` / flush chunks |
| **Constraints** | Platform seed must never create users/projects/samples; English docs; worker-safe (commands are CLI, not HTTP) |
| **Scale/Scope** | Three commands + Makefile + docs; cold-start UI in `056` (SiteBackupBundle) |

## Constitution Check

| Gate | Status |
|------|--------|
| Spec-first under `specs/` | Pass — `055-install-seed-layers` |
| Prefer nowo-tech kits | Pass — reuses dashboard-menu / breadcrumb kit data via existing seeders |
| English docs / PHPDoc | Pass — README, UPGRADING, CHANGELOG, INSTALL note |
| Docker-first / no host PHP assumption | Pass — `make` + `docker compose exec` |
| Efficient ingest unchanged | Pass — samples written via ORM/services, not Envelope (acceptable for fixtures) |
| Tests required | Pass — FR-010 |

Post-design: no constitution violations.

## Project Structure

### Documentation (this feature)

```text
specs/055-install-seed-layers/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── cli-seeds.md
└── tasks.md
```

### Source Code (repository root)

```text
src/Shared/Command/SeedPlatformCommand.php          # app:seed-platform
src/Identity/Command/SeedDemoCommand.php            # slimmed app:seed-demo
src/Shared/Command/SeedSampleCommand.php            # app:seed-sample
src/Shared/Menu/DashboardMenuDemoSeeder.php         # keep; called by platform
src/Shared/Breadcrumb/BreadcrumbDemoSeeder.php      # keep; called by platform
src/Analytics/Service/AnalyticsDemoSeeder.php       # called by sample (dev+)
src/Performance/Service/PerformanceDemoSeeder.php   # called by sample (dev+)
src/Issues/Service/IssueSampleSeeder.php            # NEW — profiled issues/events
Makefile                                            # bootstrap / seed-platform / seed / seed-sample
docs/INSTALL.md                                     # NEW short install layers guide (or section)
README.md, docs/UPGRADING.md, docs/CHANGELOG.md, docs/CONTRIBUTING.md
tests/Shared/SeedPlatformCommandTest.php
tests/Identity/SeedDemoCommandTest.php
tests/Shared/SeedSampleCommandTest.php
```

**Structure Decision**: Keep menu/breadcrumb seeders in `Shared`; add platform + sample commands under `Shared\Command`; leave demo identity in `Identity\Command`. Optional later rename `*DemoSeeder` → `*PlatformSeeder` without behavior change (tasks may alias).

## Complexity Tracking

No constitution violations requiring justification.
