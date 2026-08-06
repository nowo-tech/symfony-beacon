# Implementation Plan: Module boundary hardening

**Branch**: `083-module-boundaries` | **Date**: 2026-08-06 | **Spec**: [spec.md](./spec.md)  
**Status**: Implemented (as-built)

## Summary

Executed architecture-audit prioritized recommendations (P0→P2) without product UX changes.

## As-built technical outcomes

1. Domain enums: `App\Issues\Enum\*`, `App\Project\Enum\ProjectRole` (imports updated across `src/` / `tests/`).
2. `AdminProjectController` lives in `App\Project\Controller` (URLs unchanged).
3. `IssueSearchRepository` owns list/filter/search; `IssueRepository` owns entity lookups + similarity.
4. OTLP under `src/Ingest/Otlp/` + `config/routes.yaml` → `controllers_ingest_otlp`.
5. Messenger: `async_ingest` vs `async` transports; health counts both queue names. Process-level consumers (`messenger` / `messenger-notify`) landed in `085`.
6. Spec catalog: `084-ops-env-to-db`; Analytics/Performance tests added.

## Deferred

- None remaining for the residual re-audit backlog (IssueController split, Setup demo seeders, Analytics/Performance tests, Identity AdminProject guardrail). Further convergence → `085-architecture-convergence`.

## Constitution check

Aligns with Principle II (modular Symfony, no DDD rewrite) and VI (fast ACK ingest). Constitution amended to **1.4.1** (module list + Shared note); **1.4.2** adds `Ops` via `085`.
