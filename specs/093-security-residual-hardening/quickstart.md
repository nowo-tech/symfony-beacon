# Quickstart: Verify `093` remediations

## Prerequisites

- Docker stack up (`make up` / project usual ready path)
- PHPUnit via project Makefile targets

## Smoke checks

```bash
# Module boundaries still green
.scripts/check-module-boundaries.sh

# Targeted suites (adjust names if implement renames)
make test-unit FILTER=EnvelopeAuth
make test-functional FILTER=HookIp
make test-functional FILTER=SecurityPosture
```

## Manual Ops posture

1. Sign in as `ROLE_ADMIN`.
2. Open Ops Overview — with secure defaults, no posture warning.
3. Enable “allow private URLs” (or disable metrics require-token) → warning lists the item.
4. Restore secure defaults → warning gone.

## Manual ingest

```bash
# Query-only credentials must fail (expect 401/403)
# Header auth must still ACK 200 — use existing demo DSN / X-Beacon-Auth
```

## Docs

Confirm SECURITY.md / PRODUCTION.md / UPGRADING.md mention query-auth removal, hook 429, metrics banner, Setup token prefer-header.
