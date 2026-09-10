# Implementation Plan: E2E cold-start circuit

**Feature**: `110-e2e-cold-start-circuit`  
**Status**: Done (Make / Playwright / CI / catalog / host hardening)

## Approach

1. Parameterize `DC_E2E` (`E2E_COMPOSE_PROJECT`, `E2E_COMPOSE_EXTRA`, env file) with recursive Make expansion.
2. Add `compose.e2e.cold.yaml` (cold `env_file` + isolated `var/site-backup` volume).
3. Make: `wipe-e2e-cold` / `up-e2e-cold` / `test-e2e-cold` / `down-e2e-cold` (classic FrankenPHP).
4. Playwright project `cold` + `e2e/cold/circuit.spec.ts` + `setup-cold.ts` (API advance loop; durable-done 302 OK).
5. Catalog + CI job `e2e-cold` + README / ROADMAP 6.62.
6. Host: `max_execution_time=900`, `SetupAdvanceTimeLimitSubscriber`, idempotent index migration.

## Out of scope v1

OPS-14 restore, SETUP-02 incomplete fixture (document as follow-up).
