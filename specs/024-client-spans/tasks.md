# Tasks: Client Spans

**Feature**: 024-client-spans  
**Primary**: BeaconBundle · **Companion**: symfony-beacon

## Bundle (primary)

- [x] T001 `SpanBuffer` (+ drain into `captureTransaction`)
- [x] T002 `instrumentation.doctrine` DBAL middleware + SQL scrubbing
- [x] T003 `instrumentation.http_client` decorator (skip Envelope self-traces)
- [x] T004 Unit tests (SpanBuffer, SqlNormalizer, TraceableHttpClient, config defaults)
- [x] T005 Document config keys in CONFIGURATION / USAGE / CHANGELOG

## Beacon (companion)

- [x] T006 EVENT-CONTEXT.md + DSN.md cross-links for spans instrumentation
- [x] T007 plan.md / tasks.md + CHANGELOG Unreleased bullet
- [x] T008 Confirm Performance UI already lists `db.sql.query` / `http.client` spans (no code change required)
