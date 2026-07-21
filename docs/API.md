# HTTP API overview

Beacon exposes a small public/operator API surface. Interactive OpenAPI lives in the app at **`/api/doc`** (Nelmio ApiDoc).

Related: [DSN.md](DSN.md) (client auth), [ARCHITECTURE.md](ARCHITECTURE.md) (ingest flow), [NOTIFICATIONS.md](NOTIFICATIONS.md) (outbound webhooks).

## Envelope ingest

```http
POST /api/{project_id}/envelope/
Content-Type: application/x-beacon-envelope
```

| Mechanism | Status |
|-----------|--------|
| `X-Beacon-Auth: beacon_key=…; beacon_secret=…` | **Preferred** |
| Envelope header `"dsn": "https://public:secret@host/project"` | Supported |
| Query `?beacon_key=&beacon_secret=` | **Deprecated** (still accepted; `Deprecation` + `Warning` headers) |

Ingest **always requires** a non-empty secret. The public key must belong to `{project_id}`. Successful requests return a fast **200 ACK**; processing continues on Messenger.

Governance: per-project **suspend ingest** and **daily quota** are enforced on ACK and re-checked in the worker (`051`).

See [DSN.md](DSN.md) for full DSN examples and Docker client notes.

## Health

| Endpoint | Purpose |
|----------|---------|
| `GET /health/live` | Liveness |
| `GET /health/ready` | Readiness (DB / queue signals). On failure, body uses a generic `error: unavailable` — no exception text (`050`). |

Bind these carefully in production ([PRODUCTION.md](PRODUCTION.md)).

## Operator OpenAPI

- UI: `/api/doc` (Swagger / OpenAPI panel in the app shell; spec `013-api-docs-panel`)
- Restricting `/api/doc` to `ROLE_ADMIN` is Planned (`054-api-doc-admin-only`)

There is **no** public read API for issues yet (Planned: `042-read-api-tokens`). Automation today: CSV/JSON export from the Issues UI, notification webhooks, and Envelope ingest.

## Auth for Twig UI

Session auth via AuthKit (`/en/login`, …). Magic login (`/login/magic`) requires an encrypted instance Mailer DSN under **Administration → Mailer**. Share links grant time-limited viewer access (project-wide or issue-scoped).
