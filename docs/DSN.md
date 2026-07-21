# Connecting SDKs (DSN)

symfony-beacon accepts the **Envelope** wire protocol.

## DSN format

```text
https://<public_key>:<secret_key>@<host>:<port>/<project_id>
```

Example (local HTTPS UI):

```text
https://9cb5e28adc3ed7a40052e2a17e327220:abcdef0123456789@localhost:9444/1
```

Ingest **always requires** `beacon_secret` (or the secret segment of the DSN). Keys created by Beacon always include a secret; public-key-only auth is rejected with HTTP 403.

The **public key** is an opaque project identifier (safe to show in Settings / DSN examples). It is **not** a credential — protect the secret.

Docker clients (BeaconBundle FrankenPHP demo) should prefer **HTTP ingest** on port `9081` via `host.docker.internal` — Caddy serves `/api/*` on HTTP for those hosts; browsers keep using HTTPS `:9444`.

```text
http://PUBLIC_KEY:SECRET_KEY@host.docker.internal:9081/1
```

Create keys from the project settings page (owner/admin) or via `bin/console app:seed-demo` / `make seed` (after `make seed-platform` or `make bootstrap`).

### Local demo sync (BeaconBundle)

`make seed` / `make bootstrap` writes `.demo-client.env` with a Docker-ready `BEACON_DSN` (`http://…@host.docker.internal:9081/{id}`).

In the sibling repo `BeaconBundle/demo/symfony8`:

```bash
make sync-beacon   # or make up (syncs before starting)
```

Override the Beacon checkout path with `BEACON_REPO=/path/to/symfony-beacon`.

## Preferred client (BeaconBundle)

Install [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) and set `BEACON_DSN` to this server (any host/port):

```env
BEACON_DSN=https://PUBLIC:SECRET@localhost:9444/1
```

The bundle authenticates with `X-Beacon-Auth` (`beacon_key` + `beacon_secret`) and embeds the full DSN in the envelope header. Content-Type is `application/x-beacon-envelope`.

Ingest endpoint:

```http
POST /api/{project_id}/envelope/
Content-Type: application/x-beacon-envelope
```

## Auth

Preferred Envelope mechanisms (mapped to project API keys):

- `X-Beacon-Auth` header with `beacon_key` + **required** `beacon_secret` (recommended)
- Envelope header `"dsn": "https://public:secret@…"`

**Deprecated:** query string `?beacon_key=…&beacon_secret=…` — secrets appear in proxy/access logs and Referer. Still accepted for compatibility; responses include `Deprecation: true` and a `Warning` header. Prefer header or envelope DSN.

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

Details: [EVENT-CONTEXT.md](EVENT-CONTEXT.md#tags-and-before_send-beaconbundle), Bundle [USAGE.md](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/USAGE.md) / [CONFIGURATION.md](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/CONFIGURATION.md).

From a FrankenPHP demo container, prefer HTTP to the published host port, e.g. `http://PUBLIC:SECRET@host.docker.internal:9081/1`.
