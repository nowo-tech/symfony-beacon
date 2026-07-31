# Feature Specification: Safe Self-Hosting Ops

**Feature Branch**: `012-safe-self-hosting`  
**Created**: 2026-07-20  
**Status**: Completed (as-built; login throttle **database** storage — 2026-07-21; retention aggregate SQL — 2026-07-22)  
**Roadmap**: Phase 2 (`docs/ROADMAP.md`)

## Summary

Operators can bound telemetry growth (retention), protect Envelope ingest from storms (rate limits), probe liveness/readiness (health endpoints), and reduce login brute-force risk (login throttling with **shared** counters).

## Requirements

- **FR-001**: Configurable retention by max age (days) and optional max events per project; purge via console command. After purge, issue aggregate recompute (`event_count` / first/last seen) MUST use SQL `COUNT` / `MIN` / `MAX` (not full event hydration).
- **FR-002**: Per-project ingest rate limit (requests / minute); `429` when exceeded; `0` disables. Envelope body size MUST also be capped (`BEACON_ENVELOPE_MAX_BYTES`, see `003-ingest`).
- **FR-003**: Public `GET /health/live` and `GET /health/ready` (DB + optional Messenger queue depth).
- **FR-004**: Login throttling on AuthKit form login via [`nowo-tech/login-throttle-bundle`](https://packagist.org/packages/nowo-tech/login-throttle-bundle`). Default **`storage: database`** (`login_attempts` table) so attempt counters are shared across FrankenPHP workers and multi-pod deployments. Keep `security.yaml` in sync with `nowo:login-throttle:configure-security`.
- **FR-005**: Document backups and scaling notes in `docs/PRODUCTION.md`.
- **FR-006**: Messenger ingest workers SHOULD run with `--memory-limit=256M` (or higher) in default Compose.

## Out of scope

WAF, multi-region HA, SSO.
