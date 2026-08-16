# Coverage (REQ-QA-002)

Measured **2026-08-16** against includable `src/` (PHPUnit Clover statements) and Vitest V8 (TypeScript includable set).

## Current measured floors

| Surface | Metric | Measured | CI / local gate |
|---------|--------|----------|-----------------|
| PHP (`src/`, Controllers + install tooling excluded) | Statements (Clover) | **100.00% (13953/13953)** | `COVERAGE_MIN=100` |
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

Hard-gated at 100% under jsdom: `theme-boot`, shell Stimulus controllers (clipboard/collapse/combobox/confirm-dialog/csrf/human-key/issue-panels-reset/menu-nested/navigate-select/password-*/tabs/toast), thinking-orbs `presets` / `theme` / `engine/profiles`.

## TypeScript exclusions (not in includable set)

| Path | Justification |
|------|----------------|
| Vendor Stimulus re-exports (`page_loader`, `confirm_submit`) | Peer controllers from UiKit; no host runtime to unit-test. |
| `analytics_chart`, `issue_realtime`, `product_tour`, `qr_login`, `thinking_orb`, `datatable`, `temporary_reveal` | Browser / Chart / Mercure / tour / canvas — Playwright e2e. |
| Controllers with empty V8 instrumentation (`clipboard_copy`, `confirm_dialog`, `tabs`, `toast_stack` twins when 0/0) | Kept out of the gate when V8 reports 0 statements; behavior covered by colocated unit tests + e2e. |
| `assets/lib/thinking-orbs/engine/{core,lattice,…}` | Canvas/WebGL draw loop. |

## Status note

Beacon historically shipped soft `COVERAGE_MIN=35`. REQ-QA-002 is now closed with hard **100%** on includable PHP `src/` and hard **100%** on the TypeScript includable set. Controllers remain e2e-owned via the documented PHPUnit source exclusions.
