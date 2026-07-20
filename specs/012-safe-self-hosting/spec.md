# Feature Specification: Safe Self-Hosting Ops

**Feature Branch**: `012-safe-self-hosting`  
**Created**: 2026-07-20  
**Status**: Completed (as-built with Phase 2 of [docs/ROADMAP.md](../../docs/ROADMAP.md))
**Roadmap**: Phase 2 (`docs/ROADMAP.md`)

## Summary

Operators can bound telemetry growth (retention), protect Envelope ingest from storms (rate limits), probe liveness/readiness (health endpoints), and reduce login brute-force risk (login throttling).

## Requirements

- **FR-001**: Configurable retention by max age (days) and optional max events per project; purge via console command.
- **FR-002**: Per-project ingest rate limit (requests / minute); `429` when exceeded; `0` disables.
- **FR-003**: Public `GET /health/live` and `GET /health/ready` (DB + optional Messenger queue depth).
- **FR-004**: Login throttling on AuthKit form login (Nowo login-throttle or Symfony equivalent).
- **FR-005**: Document backups and scaling notes in `docs/production.md`.

## Out of scope

WAF, multi-region HA, SSO.
