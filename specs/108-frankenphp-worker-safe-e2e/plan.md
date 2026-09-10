# Implementation Plan: FrankenPHP worker-safe E2E

**Feature**: `108-frankenphp-worker-safe-e2e`  
**Status**: Done (implemented on main)

## Approach

1. Add `App\Shared\Health\FrankenPhpRuntime` and extend `/health/live`.
2. Default isolated Compose/Make env to worker + `WORKER_NUM=4` + `RESET=false`; isolate FrankenPHP keys from dogfood overlay.
3. Enable Playwright `fullyParallel` with configurable workers; pass env through Make.
4. Add serial `e2e/worker/kernel-isolation.spec.ts` and Make targets that pin `WORKER_NUM=1` for leak detection.
5. Add CI job `e2e-worker-safe` on `ready-e2e-lite`.

## Out of scope

See `spec.md` Non-goals. Product catalog depth and dogfood classic default remain unchanged.
