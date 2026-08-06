# Implementation Plan: Architecture convergence

**Branch**: `085-architecture-convergence` | **Date**: 2026-08-06 | **Spec**: [spec.md](./spec.md)  
**Status**: Implemented (as-built)

## Technical approach

| Change | Approach |
|--------|----------|
| Envelope writers | Move persist logic into Issues/Performance services; handler registers after-flush notify callbacks from write results |
| Ops module | Relocate OpsOverview, Retention*, MetricsCollector; keep MetricsController + Prometheus formatter chrome in Shared for scrape UX |
| EventTimestampParser | Move to `Issues\Service` (used only by event write path) |
| Messenger | Two Compose services; transports unchanged |
| Formatters | Channel classes under `Notifications\Formatter\`; facade keeps public API |
| CI | Shell checks for regressions |
| M7 + fixtures | `UserUiPreferences` embeddable; demo seed data in `Setup\Demo/fixtures/*.json` |

## Testing

- Existing Ingest Envelope / NotificationOutbound / Ops / Retention tests
- New Project admin tests under `tests/Functional/Project/`
