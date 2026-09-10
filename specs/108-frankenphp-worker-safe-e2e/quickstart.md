# Quickstart: FrankenPHP worker-safe E2E (`108`)

## Isolated stack (recommended)

```bash
make up-e2e
make ready-e2e-lite          # migrate + seed-e2e (no sample)
curl -kfsS https://localhost:9460/health/live | jq .runtime
make test-e2e-worker-safe    # forces WORKER_NUM=1 + e2e/worker
```

Defaults on the isolated stack: `FRANKENPHP_MODE=worker`, `FRANKENPHP_WORKER_NUM=4`, `FRANKENPHP_RESET_KERNEL=false`.

## Classic contrast

```bash
make test-e2e-worker-safe-classic   # probe under classic; isolation tests skip
```

## Product E2E (unchanged)

```bash
make ready-e2e               # includes seed-sample
make test-e2e-isolated       # Playwright multi-worker (local 4 / CI 2)
```

## PHPUnit (probe)

```bash
docker compose exec -T php vendor/bin/phpunit \
  tests/Unit/Shared/Health \
  tests/Unit/Shared/HealthControllerLiveTest.php \
  tests/Functional/Shared/HealthEndpointsTest.php
```

See `docs/ops/FRANKENPHP-CODING.md` and `e2e/README.md`.
