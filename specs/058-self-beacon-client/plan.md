# Plan: 058-self-beacon-client

**Branch**: `058-self-beacon-client` | **Date**: 2026-07-29 | **Spec**: [spec.md](./spec.md)

## Summary

Add `nowo-tech/beacon-bundle` to the Beacon server for dogfooding. Seed demo keys are stable; `app:seed-demo` writes loopback `BEACON_DSN` when empty; `make ready` = bootstrap + seed; `make dogfood` = seed-demo only for re-wiring the client DSN; `DropSelfIngestBeforeSend` prevents Envelope feedback loops. Package installs from Packagist.

## Implementation

1. Composer require (Packagist) + `config/packages/nowo_beacon.yaml` + `.env.dist` vars.
2. `ProjectApiKey::generate(..., ?secret)`; `DEMO_SECRET_KEY`; seed patches `.env`.
3. Makefile `ready` + `dogfood`; docs INSTALL/DSN/README/ARCHITECTURE/constitution.
4. Tests for before_send + seed DSN write.
