# Plan: 058-self-beacon-client

**Branch**: `058-self-beacon-client` | **Date**: 2026-07-29 | **Spec**: [spec.md](./spec.md)

## Summary

Add `nowo-tech/beacon-bundle` to the Beacon server for dogfooding. Seed demo keys are stable; `app:seed-demo` writes loopback `BEACON_DSN` when empty (prefer `.env.local`); `make ready` = bootstrap + seed; `make dogfood` = seed-demo only for re-wiring the client DSN; `DropSelfIngestBeforeSend` prevents Envelope feedback loops; `ignore_exceptions` excludes expected `AccessDenied*` 403s so ACL noise does not fill the dogfood project. Package installs from Packagist. Operators verify with `make beacon-test` (`app:beacon:test`) or multi-event `make beacon-suite` (`--suite`); seed reclaim host ownership of `.demo-client.env` for CI/Playwright.

## Implementation

1. Composer require (Packagist) + `config/packages/nowo_beacon.yaml` + `.env.dist` vars.
2. `ProjectApiKey::generate(..., ?secret)`; `DEMO_SECRET_KEY`; seed patches `.env.local` / `.env`.
3. Makefile `ready` + `dogfood` + `beacon-test` + `beacon-suite` + `reclaim-demo-client-env`; docs INSTALL/DSN/README/ARCHITECTURE/constitution.
4. Tests for before_send + seed DSN write + dogfood diagnostics + probe suite payloads/tags.
5. (2026-08-16) Pin beacon-bundle **1.7.3**; host wrapper diagnostics (ACK ≠ Web Push).
6. (2026-08-16) `nowo_beacon.ignore_exceptions`: `AccessDeniedException` + `AccessDeniedHttpException`; document in DSN.md / spec FR-010.
7. (2026-08-17) `BeaconDogfoodProbeSuite` + `app:beacon:test --suite` / `make beacon-suite`; explicit where-it-happened client tags (FR-015 / FR-016).
8. (2026-08-17) Spec implementation table: seven kinds, sync `EnvelopeTransport`, fingerprint `beacon-suite`+kind+token, `--check-only` preview (FR-015b). Docs: DSN.md operator matrix.
