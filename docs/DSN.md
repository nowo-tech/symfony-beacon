# Connecting SDKs (DSN)

symfony-beacon accepts the **Envelope** wire protocol.

## DSN format

```text
https://<public_key>:<secret_key>@<host>:<port>/<project_uuid>
```

Example (local HTTPS UI):

```text
https://9cb5e28adc3ed7a40052e2a17e327220:abcdef0123456789@localhost:9447/019fea2d-507b-7890-8b33-ca488db6f696
```

Ingest **always requires** `beacon_secret` (or the secret segment of the DSN). Keys created by Beacon always include a secret; public-key-only auth is rejected with HTTP 403.

The **public key** is an opaque credential identifier (safe to show in Settings). The DSN path is the project **UUID**. Legacy numeric project ids in the path are still accepted for older clients.

Docker clients (BeaconBundle FrankenPHP demo) on the **local** stack (`compose.yaml`) may use **HTTP ingest** on port `9084` via `host.docker.internal` — the development Caddyfile serves `/api/*` on HTTP for those hosts; browsers keep using HTTPS `:9447`. **Production** (`compose.prod.yaml` / `Caddyfile.prod`) does **not** accept cleartext ingest: use an `https://…` DSN only.

```text
http://PUBLIC_KEY:SECRET_KEY@host.docker.internal:9084/<project_uuid>
```

Create keys from the project settings page (owner/admin) or via `bin/console app:seed-demo` / `make seed` / `make ready` (after `make seed-platform` or `make bootstrap`).

### Server dogfooding (this instance)

This repository requires [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle). Empty `BEACON_DSN` disables reporting.

After `make seed` / `make ready`, when `BEACON_DSN` is empty, demo seed writes a **loopback** DSN into `.env`:

```text
http://PUBLIC_KEY:SECRET_KEY@127.0.0.1/{project_uuid}
```

Restart PHP (`make restart`) so the Kernel reloads env. `before_send` drops events whose request path contains `/envelope/` to avoid ingest feedback loops. See `config/packages/nowo_beacon.yaml`.

### Local demo sync (external BeaconBundle)

`make seed` writes `.demo-client.env` with a Docker-ready client `BEACON_DSN` (`http://…@host.docker.internal:9084/{uuid}`).

In the sibling repo `BeaconBundle/demo/symfony8`:

```bash
make sync-beacon   # or make up (syncs before starting)
```

Override the Beacon checkout path with `BEACON_REPO=/path/to/symfony-beacon`.

## Preferred client (BeaconBundle)

Install [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) and set `BEACON_DSN` to this server (any host/port):

```env
BEACON_DSN=https://PUBLIC:SECRET@localhost:9447/<project_uuid>
```

The bundle authenticates with `X-Beacon-Auth` (`beacon_key` + `beacon_secret`) and embeds the full DSN in the envelope header. Content-Type is `application/x-beacon-envelope`.

Ingest endpoint:

```http
POST /api/{project_uuid}/envelope/
Content-Type: application/x-beacon-envelope
```

### OTLP logs (HTTP JSON adapter)

Collectors / SDKs that speak OTLP can POST logs (same project credentials):

```http
POST /api/{project_uuid}/otlp/v1/logs
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=PUBLIC, beacon_secret=SECRET
```

WARN+ LogRecords become Beacon Issues/Events (see [API.md](API.md#otlp-logs-ingest-v1), spec `067-otlp-ingest`). Prefer Envelope via BeaconBundle for first-party Symfony apps.

### OTLP traces (HTTP JSON adapter)

```http
POST /api/{project_id}/otlp/v1/traces
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=PUBLIC, beacon_secret=SECRET
```

ERROR spans become Beacon Issues/Events (see [API.md](API.md#otlp-traces-ingest-v1), spec `070-otlp-traces`).

### OTLP metrics (HTTP JSON adapter)

```http
POST /api/{project_id}/otlp/v1/metrics
Content-Type: application/json
X-Beacon-Auth: Beacon beacon_key=PUBLIC, beacon_secret=SECRET
```

Failure-like metric data points become Beacon Issues/Events (see [API.md](API.md#otlp-metrics-ingest-v1), spec `074-otlp-metrics`).

## Auth

Preferred Envelope mechanisms (mapped to project API keys):

- `X-Beacon-Auth` header with `beacon_key` + **required** `beacon_secret` (recommended)
- Envelope header `"dsn": "https://public:secret@…"`

**Deprecated:** query string `?beacon_key=…&beacon_secret=…` — secrets appear in proxy/access logs and Referer. **Rejected by default** (Ops defaults → reject query auth → HTTP 401). Disable only while migrating; responses then include `Deprecation: true` and a `Warning` header. Prefer header or envelope DSN.

The public key must belong to the `{project_id}` in the URL.

### Ingest sequence (overview)

Full architecture diagrams (module map, grouping, N+1, UI access) live in [ARCHITECTURE.md](ARCHITECTURE.md#flows-mermaid).

```mermaid
sequenceDiagram
  participant SDK as Client SDK
  participant API as Beacon Envelope API
  participant Bus as Messenger
  participant DB as MySQL

  SDK->>API: POST /api/{project_id}/envelope/ + auth
  API->>API: Validate key + parse envelope
  API->>Bus: ProcessEnvelopeMessage
  API-->>SDK: 200 ACK
  Bus->>DB: Persist issue/event or transaction
```

## Async processing

The HTTP endpoint validates the key and envelope, dispatches `ProcessEnvelopeMessage`, and returns `200` quickly. The Compose `messenger` service persists issues/events/transactions.

## Client capabilities (BeaconBundle)

| Capability | Envelope | Beacon UI |
|------------|----------|-----------|
| Events (`captureMessage` / `captureException`) | item `type: event` | Issues |
| User context (`send.user`) | payload `user` | Event detail |
| Breadcrumbs (`addBreadcrumb`) | payload `breadcrumbs.values` | Event detail |
| Tags (`setTag` / `setTags`) | payload `tags` | Event detail → Tags (client tags) |
| `before_send` scrubbing | Mutates/drops payload pre-send | N/A (client-side) |
| Performance (`captureTransaction`) | item `type: transaction` | Performance |
| Doctrine / HttpClient spans (`instrumentation.*`) | transaction `spans` + breadcrumbs | Performance + event breadcrumbs |
| Contexts (PHP / Symfony / OS) | payload `contexts` | Event detail |

Details: [EVENT-CONTEXT.md](product/EVENT-CONTEXT.md#tags-and-before_send-beaconbundle), Bundle [USAGE.md](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/USAGE.md) / [CONFIGURATION.md](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/CONFIGURATION.md).

From a FrankenPHP demo container, prefer HTTP to the published host port, e.g. `http://PUBLIC:SECRET@host.docker.internal:9084/1`.
