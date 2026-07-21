# Implementation Plan: Client Spans

**Branch**: `024-client-spans`  
**Date**: 2026-07-21  
**Status**: Bundle-primary (Beacon Performance UI already renders transaction spans)

## Summary

Opt-in Doctrine SQL and HttpClient request instrumentation lives in **BeaconBundle**. Spans attach to the next `captureTransaction()` (including `auto_http_transaction`); breadcrumbs dual-write for events. Beacon documents config and relies on existing Performance detail + N+1 detection for `db.*` ops.

## Ownership

| Area | Repo |
|------|------|
| `SpanBuffer`, drain on `captureTransaction` | BeaconBundle |
| `instrumentation.doctrine` DBAL middleware | BeaconBundle |
| `instrumentation.http_client` decorator | BeaconBundle |
| Config docs + unit tests | BeaconBundle |
| EVENT-CONTEXT / DSN cross-links | symfony-beacon |
| Performance span list / N+1 | Existing Beacon `006-performance` |

## Technical approach (Bundle)

- `instrumentation.doctrine` / `instrumentation.http_client` default **false**.
- SQL descriptions scrubbed/truncated (`SqlNormalizer`).
- HttpClient skips `/envelope/` and `beacon-bundle/` User-Agent to avoid self-traces.
- Safe when doctrine/dbal is absent (middleware not registered).

## Companion (Beacon)

- Docs only for this slice (Performance UI already lists spans).

## Out of scope

- Manual custom span builder API beyond `captureTransaction($spans)`
- Async/non-blocking transport (Phase 3.5)
