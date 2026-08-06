# Feature Specification: Architecture convergence (post-083)

**Feature Branch**: `085-architecture-convergence`  
**Created**: 2026-08-06  
**Status**: Implemented (2026-08-06)  

**Input**: Architecture re-audit proposals M1–M8 (2026-08-06): thin Envelope orchestration, extract Ops from Shared, process-level Messenger isolation, AI export controller, channel formatters, Project admin tests, CI boundary expansion. Optional follow-up: M7 User UI preferences embeddable + demo seeders → JSON fixtures (implemented).

## Summary

Close the residual structural debt left after `083-module-boundaries` without a rewrite: domain writers for ingest, honest `Ops` module ownership, separate Compose consumers, maintainable Issues/Notifications surfaces, and stronger CI guardrails.

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| M1 | Ingest | `IssueEnvelopeWriter` + `PerformanceEnvelopeWriter`; thin `ProcessEnvelopeHandler` |
| M2 | Ops | `App\Ops\{Service,Controller,Retention,Metrics}`; Shared no longer imports Issues write-path |
| M3 | Compose | `messenger` → `async_ingest` only; `messenger-notify` → `async` |
| M4 | Issues | `IssueAiExportController` (routes unchanged) |
| M5 | Notifications | `Formatter/*ChannelFormatter` + thin `NotificationOutboundFormatter` facade |
| M6 | Tests | `AdminProjectAccessTest`, `AdminProjectIngestTest`, `ProjectApiKeyRotateTest` |
| M8 | CI | Expanded `.scripts/check-module-boundaries.sh` |
| — | Docs | Constitution **1.4.2**, `ARCHITECTURE.md`, `PRODUCTION.md`, ROADMAP **6.34** |
| M7 | Identity | `UserUiPreferences` embeddable + facade on `User`; `AccountUiPreferencesAjaxController`; GDPR `resetForAnonymize` |
| — | Setup | Demo seeders load `src/Setup/Demo/fixtures/*.json` via `DemoFixtureLoader` |

## Non-goals

- Full DDD / hexagonal / SPA
- Splitting `IssueSearchRepository`
- Merging Messenger transports
- M7 User entity prefs split (done via embeddable)
- Leaving demo seed data as PHP arrays (done via JSON fixtures)

## Success criteria

1. `make check-module-boundaries` passes (AdminProject, OTLP, Shared write-path, Ops, Envelope writers).
2. Ingest Envelope PHPUnit suite green with writers.
3. Ops overview / retention / metrics endpoints still work under new namespaces.
4. Compose defines two Messenger consumer services.
5. Notification outbound formatter tests green after channel split.

## Related

- `083-module-boundaries` (predecessor)
- Roadmap **6.34**
- Proposals canvas: architecture-audit-proposals-2026-08-06
- Prior surfaces: `035-ops-overview`, `038-prometheus-metrics`, `059-ai-issue-export`, `009` / `068`–`073` (notifications)
