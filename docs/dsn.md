# Connecting SDKs (DSN)

symfony-beacon accepts the **Envelope** wire protocol.

## DSN format

```text
https://<public_key>@<host>:<port>/<project_id>
```

Example (local Docker):

```text
https://9cb5e28adc3ed7a40052e2a17e327220@localhost:9444/1
```

Create keys from the project settings page (owner/admin) or via `bin/console app:seed-demo`.

## PHP SDK

```php
\Sentry\init([
    'dsn' => 'https://PUBLIC@localhost:9444/1',
    // For local self-signed TLS you may need transport options / HTTP instead of HTTPS.
]);
```

Ingest endpoint used by the SDK:

```http
POST /api/{project_id}/envelope/
X-Sentry-Auth: Sentry sentry_version=7, sentry_key=PUBLIC, sentry_client=…
Content-Type: application/x-sentry-envelope
```

## Auth

Supported:

- `X-Sentry-Auth` header (`sentry_key`, optional `sentry_secret`)
- Query string `?sentry_key=…`
- Envelope header `"dsn": "https://…"`

The public key must belong to the `{project_id}` in the URL.

## Async processing

The HTTP endpoint validates the key and envelope, dispatches `ProcessEnvelopeMessage`, and returns `200` quickly. The Compose `messenger` service persists issues/events/transactions.

## Symfony bundle

A dedicated `symfony-beacon-bundle` will live in a **separate repository**. Until then, use the official PHP SDK package `sentry/sentry` pointed at this server.
