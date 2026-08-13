# HTTP API overview

Beacon exposes a small public/operator API surface. Interactive OpenAPI lives in the app at **`/admin/api/doc`** (Nelmio ApiDoc, Administration shell).

Related: [DSN.md](DSN.md) (client auth), [ARCHITECTURE.md](ARCHITECTURE.md) (ingest flow), [NOTIFICATIONS.md](product/NOTIFICATIONS.md) (outbound webhooks).

## Envelope ingest

```http
POST /api/{project_id}/envelope/
Content-Type: application/x-beacon-envelope
```

| Mechanism | Status |
|-----------|--------|
| `X-Beacon-Auth: beacon_key=…; beacon_secret=…` | **Preferred** |
| Envelope header `"dsn": "https://public:secret@host/project"` | Supported |
| Query `?beacon_key=&beacon_secret=` | **Removed** — always HTTP 401; use `X-Beacon-Auth` or envelope DSN |

Ingest **always requires** a non-empty secret. The public key must belong to `{project_id}`. Successful requests return a fast **200 ACK**; processing continues on Messenger.

Governance: per-project **suspend ingest** and **daily/monthly quota** are enforced on ACK and re-checked in the worker (`051`).

See [DSN.md](DSN.md) for full DSN examples and Docker client notes.

## OTLP logs ingest (v1)

```http
POST /api/{project_id}/otlp/v1/logs
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…
```

Accepts an OTLP **ExportLogsServiceRequest** JSON body (`resourceLogs` → `scopeLogs` → `logRecords`). WARN+ records (severityNumber ≥ 13) are mapped to Beacon events and processed by the same Messenger worker as Envelope (cap **200** records per request). DEBUG/INFO are dropped. Query-string auth is **not** accepted. Body size limit matches Ops defaults envelope max bytes (default 2 MiB). Shared gate/map/dispatch: `OtlpIngestPipeline` (`086`). Spec: `067-otlp-ingest`.

## OTLP traces ingest (v1)

```http
POST /api/{project_id}/otlp/v1/traces
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…
```

Accepts an OTLP **ExportTraceServiceRequest** JSON body (`resourceSpans` → `scopeSpans` → `spans`). **ERROR** spans (status code ERROR and/or exception attributes) map to Beacon events via the same worker (cap **200** spans per request). OK/UNSET spans without exceptions are dropped. Query-string auth is **not** accepted. Shared gate/map/dispatch: `OtlpIngestPipeline` (`086`). Spec: `070-otlp-traces`.

## OTLP metrics ingest (v1)

```http
POST /api/{project_id}/otlp/v1/metrics
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=…, beacon_secret=…
```

Accepts an OTLP **ExportMetricsServiceRequest** JSON body (`resourceMetrics` → `scopeMetrics` → `metrics`). Failure-like **data points** (metric name matching `.error` / `.errors` / `_errors`, or attributes `error.type` / `exception.*` / `otel.status_code=ERROR`) map to Beacon events via the same worker (cap **200** data points per request). Healthy points are dropped. Query-string auth is **not** accepted. Shared gate/map/dispatch: `OtlpIngestPipeline` (`086`). Spec: `074-otlp-metrics`.

Out of scope for OTLP v1 adapters: gRPC, protobuf Content-Type, time-series storage / Performance dashboards.

| Endpoint | Purpose |
|----------|---------|
| `GET /health/live` | Liveness |
| `GET /health/ready` | Readiness (database). On failure, body uses a generic `error: unavailable` — no exception text (`050`). Messenger backlog is on authenticated `/metrics`, not this probe. |

Bind these carefully in production ([PRODUCTION.md](PRODUCTION.md)).

## Operator OpenAPI

- UI: `/admin/api/doc` (Swagger / OpenAPI in the Administration shell; specs `013-api-docs-panel`, `054-api-doc-admin-only`)
- Requires **`ROLE_ADMIN`**

## Read API (Bearer tokens)

Project Settings can mint **read tokens** (`brt_…`, SHA-256 at rest). Endpoints under `/api/projects/{uuid}/…` (list/get issues) authenticate in-controller (`security: false` firewall). Spec: `042-read-api-tokens`.

| Mechanism | Notes |
|-----------|--------|
| `Authorization: Bearer brt_…` | Required |
| IP rate limit | `BEACON_READ_API_RATE_LIMIT` (default 120/min; `0` disables) → HTTP **429** + `Retry-After: 60` (`096`) |
| Maintenance | Read API is **not** excluded — returns **503** when maintenance is on (`095`) |

Not a public board: no anonymous access; write/mutate API is out of scope.

## Auth for Twig UI

Session auth via AuthKit (`/login`). Magic login (`/login/magic`) requires an encrypted instance Mailer DSN under **Administration → Mailer**. Share links grant time-limited viewer access (project-wide or issue-scoped). AuthKit **QR phone login is disabled** until SMS OTP can set `phoneVerifiedAt` (`096`).
