# Coverage (REQ-QA-002)

Measured **2026-08-20** against includable `src/` (PHPUnit Clover statements) and Vitest V8 (TypeScript includable set).

## Current measured floors

| Surface | Metric | Measured | CI / local gate |
|---------|--------|----------|-----------------|
| PHP (`src/`, Controllers + install tooling excluded) | Statements (Clover) | **100.00% (14284/14284)** | `COVERAGE_MIN=100` |
| TypeScript (includable `assets/` whitelist) | Lines / statements (V8) | **100% / 100%** | Vitest `coverage.thresholds` lines/statements **100** (hard fail) |

Regenerate PHP:

```bash
make ensure-up
COVERAGE_MIN=100 make test-coverage
# → var/coverage/clover.xml + var/coverage-html/
```

Regenerate TS:

```bash
make test-unit-js-coverage
# → var/coverage-js/
```

## PHP source exclusions (`phpunit.dist.xml`)

Justified exclusions under `<source><exclude>` (not counted toward 100%):

| Path | Justification |
|------|----------------|
| `src/*/Controller/**`, `src/Shared/*/Controller/**`, `src/Api/Read/Controller/**`, `src/Ingest/Otlp/Controller/**` | HTTP controllers exercised by Playwright e2e (REQ-QA-003) and Functional HTTP suites. PHPUnit hard gate focuses on domain services, forms, entities, repositories, subscribers, commands. |
| `src/Setup/Demo/**` | Demo/sample seeders and fixture loaders for install tooling (`make seed` / `make seed-sample`). |
| `src/Identity/Command/SeedDemoCommand.php`, `src/Identity/Service/DemoIdentitySeeder.php` | Demo identity install path. |
| `src/Analytics/Service/AnalyticsDemoSeeder.php`, `src/Performance/Service/PerformanceDemoSeeder.php` | Module demo seeders. |
| `src/Ops/Command/BeaconTestCommand.php` | Operator dogfood CLI (`beacon:test`). |
| `src/Ops/Service/BeaconDogfoodDiagnostics.php`, `BeaconDogfoodDiagnosticReport.php` | Dogfood diagnostics tooling. |
| `src/Issues/Command/BackfillEventPromotionsCommand.php` | One-shot backfill command. |
| `src/Shared/Sms/Command/SmsSendCommand.php` | Ops SMS send CLI. |
| `src/Setup/Command/SeedSampleCommand.php`, `src/Issues/Service/IssueSampleSeeder.php` | Sample data install (`make seed-sample`). |

## TypeScript includable set (`vitest.config.ts` → `coverage.include`)

Hard-gated at 100% under jsdom:

- `theme-boot`
- Shell Stimulus: `collapse_panel`, `combobox`, `csrf_protection`, `human_key_label`, `issue_panels_reset`, `menu_nested_collapse`, `navigate_select`, `password_confirm_mirror`, `password_toggle`, `temporary_reveal`
- `assets/lib/morphicons/index.ts` (theme / content-width / sidebar / password morph helpers)
- thinking-orbs `presets` / `theme` / `engine/profiles`

## TypeScript exclusions (not in includable set)

| Path | Justification |
|------|----------------|
| Vendor Stimulus re-exports (`page_loader`, `confirm_submit`, `clipboard_copy`, `confirm_dialog`, `tabs`, `toast_stack`) | Peer controllers from UiKit; V8 often reports 0/0 statements. Colocated unit tests + Playwright cover behavior. |
| `analytics_chart`, `issue_realtime`, `product_tour`, `qr_login`, `thinking_orb`, `datatable` | Browser / Chart / Mercure / tour / canvas — Playwright e2e. |
| `assets/lib/thinking-orbs/engine/{core,lattice,…}`, `assets/lib/thinking-orbs/index.ts` | Canvas/WebGL draw loop / barrel. |
| `assets/app.ts` | Browser bootstrap (morphicon wiring + Turbo); product chrome covered by UC-ACC-26 E2E + morphicons unit gate. |

## Status note

Beacon historically shipped soft `COVERAGE_MIN=35`. REQ-QA-002 is now closed with hard **100%** on includable PHP `src/` and hard **100%** on the TypeScript includable set (including morphicons + temporary-reveal). Controllers remain e2e-owned via the documented PHPUnit source exclusions. Product surface tracking: [`docs/product/E2E-USE-CASES.md`](product/E2E-USE-CASES.md).
