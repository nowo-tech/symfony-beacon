# Quickstart: SQL error context

## Server UI

1. `make up` (or `ensure-up`) and migrate: `php bin/console doctrine:migrations:migrate -n` (`issue.culprit` → 255 on MySQL).
2. Seed sample: `make seed-sample` — first synthetic issue includes a QueryException-like payload.
3. Open that issue: Query panel shows SQLSTATE `42000`, code `1055`, and the SELECT; the in-app frame is expanded.
4. AI export: Copy for AI includes `## Query`.

## PHPUnit

```bash
make test FILTER=QueryFacts
make test FILTER=IssueErrorSurfaces
make test FILTER=IssueStackPresenter
make test FILTER=AiIssueExport
```

## BeaconBundle 1.8.0 (sibling)

```bash
cd ../../bundles/BeaconBundle
# after implementation
make test FILTER=DatabaseExceptionContext
make test FILTER=EnvelopeBuilder
```

Capture a `PDOException` / Doctrine driver exception: Envelope JSON item has `contexts.db`. Older Beacon servers ignore unknown context keys; this Beacon version renders them in Query.
