# Implementation Plan: E2E cold-start circuit

**Feature**: `110-e2e-cold-start-circuit`  
**Status**: Done (Make / Playwright / CI / catalog)

## Approach

1. Parameterize `DC_E2E` (`E2E_COMPOSE_PROJECT`, compose file list, env file).
2. Add `compose.e2e.cold.yaml` (isolated `var/site-backup` volume).
3. Make: `wipe-e2e-cold` / `up-e2e-cold` / `test-e2e-cold` / `down-e2e-cold`.
4. Playwright project `cold` + `e2e/cold/circuit.spec.ts` (API advance loop).
5. Catalog + CI + README.

## Out of scope v1

OPS-14 restore, SETUP-02 incomplete fixture (document as follow-up).
