# Plan: Ops Overview Dashboard (`035`)

**Branch**: `035-ops-overview` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

## Summary

Add `ROLE_ADMIN` Ops overview that aggregates existing Issue / Analytics / Notification / Messenger helpers. Entry from Admin hub. Optional `?project=` UUID filter. Cap lists. No new metrics tables.

## Technical Context

| Area | Decision |
|------|----------|
| Stack | PHP 8.5 / Symfony 8.1 / Twig |
| Auth | `ROLE_ADMIN` only |
| Data | `ProjectOpsStatsService`, `IssueRepository`, `DailyProjectStatRepository` / `EventRepository`, `NotificationDestination` + `NotificationDeliveryAttemptRepository`, `MessengerQueueHealth` |
| Spike rule (MVP) | Projects where `errors_last_1d > max(3, 2 × avg(errors_last_7d))` (tune in impl; keep test-stable) |
| Caps | Top 25 spikes, 25 failed deliveries, 50 open-issue project rows |
| Tests | PHPUnit functional under `tests/` |

## Constitution Check

| Gate | Status |
|------|--------|
| Spec-first | Pass |
| Prefer kits | N/A (ops surface is Beacon product) |
| English docs/UI | Pass |
| Efficient ingest | Pass — read-only aggregations |
| Tests | Pass — FR-008 |

## Implementation

1. `OpsOverviewService` (or controller-local query object) assembling DTO for Twig.
2. `AdminOpsOverviewController` + route `/admin/ops` (name `admin_ops_overview`).
3. Twig `templates/admin/ops/overview.html.twig`; hub card + menu seeder key.
4. Destination repository helpers for cross-project failed last-delivery / recent attempts.
5. i18n keys `admin.ops.*`; PHPUnit access + filter + empty/healthy fixtures.
6. CHANGELOG / ROADMAP / UPGRADING pointer.

## Risks

- N+1 if per-project loops — prefer `countByStatusForProjectIds` / batched stats.
- Webhook URL leakage — mask like Settings destinations.
