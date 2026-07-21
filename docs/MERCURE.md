# Mercure setup (live member alerts)

Beacon can show **live toasts** to signed-in members when a **new issue** is created on a project they belong to. That path uses [Mercure](https://mercure.rocks/) (Server-Sent Events). It is **optional** and **off by default**.

Background / locked-screen alerts use **Web Push** (VAPID) instead — see [NOTIFICATIONS.md](NOTIFICATIONS.md#web-push-pwa). Mercure and Web Push are independent.

| Piece | Role |
|-------|------|
| Mercure **hub** | Accepts publish requests from PHP; fans out SSE to browsers |
| `MERCURE_JWT_SECRET` | Shared HMAC secret used to sign **publisher** and **subscriber** JWTs |
| **Administration → Mercure** | Master switch + optional URL/secret overrides stored in the database |
| Caddy `/.well-known/mercure` | Same-origin reverse proxy so the browser talks to the hub without CORS pain |

Official Symfony notes: [Mercure configuration](https://symfony.com/doc/current/mercure.html#configuration). Hub image: [`dunglas/mercure`](https://hub.docker.com/r/dunglas/mercure).

---

## Quick start (Docker Compose)

1. Ensure `.env` has Mercure variables (copied from `.env.dist`):

   ```env
   MERCURE_URL=http://mercure/.well-known/mercure
   MERCURE_PUBLIC_URL=${DEFAULT_URI}/.well-known/mercure
   MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
   ```

2. Replace the default JWT secret with a strong value (**≥ 32 characters**). The same string must reach:

   - PHP / Messenger (`MERCURE_JWT_SECRET`)
   - Hub (`MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY` in `compose.yaml` — both are wired to `${MERCURE_JWT_SECRET}`)

   Generate one:

   ```bash
   openssl rand -base64 48
   ```

3. Start (or recreate) the hub with the app stack:

   ```bash
   docker compose up -d mercure php
   # or a full stack: make up
   ```

4. Confirm Caddy proxies the hub (already in [`.docker/frankenphp/Caddyfile`](../.docker/frankenphp/Caddyfile)):

   ```caddy
   handle /.well-known/mercure* {
       reverse_proxy mercure:80
   }
   ```

5. Open **Administration → Mercure** (`/settings/mercure`):

   - Turn on **Enable Mercure live alerts**
   - Leave URL / public URL / JWT blank to use the env values, **or** fill them to override (JWT saved in the DB is encrypted)
   - Save — the status panel should show live alerts as **active**

6. Sign in as a project member, open a project Issues page (or any page that loads the realtime controller), create a new issue (ingest or UI), and confirm an in-app toast while the tab is open.

**Sample seed shortcut:** `app:seed-sample` / Setup → load sample data enables Mercure and copies blank URL / public URL / JWT from `MERCURE_*` env into instance settings (without overwriting values already saved). You can still turn Mercure off later under **Administration → Mercure**.

Mercure does **not** need to be a hard dependency of `php` / `messenger` for Envelope ingest; if the hub is down and Mercure is disabled, Beacon continues normally.

---

## Environment variables

| Variable | Used by | Meaning |
|----------|---------|---------|
| `MERCURE_URL` | PHP (publish) | Internal hub endpoint. In Compose: `http://mercure/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | Browser (subscribe) | URL the EventSource client opens. Prefer same-origin via Caddy: `${DEFAULT_URI}/.well-known/mercure` |
| `MERCURE_JWT_SECRET` | PHP + hub | Shared HMAC secret for JWTs (min **32** characters when set in the admin form) |

Compose also passes the secret into the hub as:

```yaml
MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
```

Keep publisher and subscriber keys **identical** to `MERCURE_JWT_SECRET` unless you intentionally run a custom split-key setup (not required for Beacon).

**Production (`compose.prod.yaml`):** `MERCURE_JWT_SECRET` and `DEFAULT_URI` are required (`:?` interpolation). Do not ship the `.env.dist` placeholder.

---

## JWT secret — what it is and how it works

Mercure authorizes **publish** and **subscribe** with JWTs signed with an HMAC key (the “JWT secret”).

1. When Beacon publishes a new-issue update, Symfony Mercure signs a **publisher** JWT with the resolved secret and POSTs to `MERCURE_URL` (or the admin override).
2. When a member’s browser needs to subscribe, Beacon issues a short-lived **subscriber** JWT (topics such as `/projects/{projectUuid}/issues`) via `GET /account/realtime/config` — only if Mercure is **enabled** and configured.
3. The hub verifies both tokens with `MERCURE_*_JWT_KEY`. If the secret in PHP does not match the hub keys, publish fails and/or EventSource never receives updates.

### Where the secret can live

Resolution order for URL and JWT (see `ConfiguredMercure`):

1. **Database** value from Administration → Mercure (JWT encrypted at rest), if set  
2. Else **environment** (`MERCURE_URL` / `MERCURE_PUBLIC_URL` / `MERCURE_JWT_SECRET`)

Admin form behaviour:

| Field | Behaviour |
|-------|-----------|
| Publish URL | Blank → `MERCURE_URL` (value stored **encrypted** when set) |
| Public URL | Blank → `MERCURE_PUBLIC_URL` (stored **encrypted** when set) |
| JWT secret | Blank → keep current DB secret, or fall back to `MERCURE_JWT_SECRET` (stored **encrypted**) |
| Clear stored JWT secret | Removes DB secret → env fallback until you save a new one |

All Mercure overrides in `instance_settings` (URLs + JWT) use Halite field encryption, same as the Mailer DSN / From.

**Rotation:** change the secret in `.env` **and** recreate the `mercure` container (so hub keys update), **or** save the new secret in Administration and update the hub env to match. Mismatched secrets cause silent or 401 failures.

---

## Administration UI checklist

Path: **Administration → Mercure** (`settings_mercure`).

1. Hub reachable from PHP (`MERCURE_URL` or Publish URL).
2. Browser URL reachable same-origin when possible (`MERCURE_PUBLIC_URL` or Public URL).
3. JWT secret present (DB or env) and matching the hub.
4. **Enable Mercure live alerts** checked and saved.
5. Status shows active + the resolved browser hub URL.

Web Push note on that screen is intentional: VAPID opt-in under **Account → Display** does not require Mercure.

---

## Topics and privacy

- Updates are **private** Mercure topics: `/projects/{projectUuid}/issues`.
- Only members who receive a subscriber JWT for topics they are allowed to see can subscribe.
- Publishing runs only when admin Mercure is enabled and URL + secret resolve.
- Disabling Mercure stops new EventSource configs and skips publish; it does not delete Web Push subscriptions.

---

## External / non-Compose hub

You can point Beacon at any Mercure-compatible hub:

1. Set `MERCURE_URL` to the publish endpoint the PHP container can reach.
2. Set `MERCURE_PUBLIC_URL` to the URL browsers can open (HTTPS in production). If the hub is on another origin, configure hub CORS (`cors_origins`) for your Beacon origin — Compose sets `cors_origins ${DEFAULT_URI}` for the bundled hub.
3. Set the same JWT secret on the hub publisher/subscriber keys and in Beacon (`MERCURE_JWT_SECRET` or admin form).
4. Optionally keep or remove the Compose `mercure` service; Caddy’s `/.well-known/mercure` proxy is only needed when browsers should use that same-origin path to the Compose hub.

---

## Verify

```bash
# Hub health (inside Compose network)
docker compose exec mercure curl -fsS http://127.0.0.1/healthz

# From the host (via Caddy), replace with your DEFAULT_URI
curl -sI "${DEFAULT_URI}/.well-known/mercure"
```

In the browser (signed in, Mercure enabled):

1. Open DevTools → Network → filter `mercure` / EventSource.
2. Confirm a connection to `/.well-known/mercure` (or your public URL) with a JWT authorization.
3. Trigger a **new** issue on an associated project and watch for the SSE event / toast.

`GET /account/realtime/config` should omit Mercure hub details when the feature is disabled or misconfigured.

---

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Admin status “inactive” | Toggle off, or missing URL/secret |
| Publish errors in logs | PHP cannot reach `MERCURE_URL`, or JWT ≠ hub keys |
| EventSource 401 | Subscriber JWT secret ≠ hub `MERCURE_SUBSCRIBER_JWT_KEY` |
| EventSource never connects | Mercure disabled; wrong `MERCURE_PUBLIC_URL`; Caddy proxy missing |
| CORS errors | Browser URL is cross-origin and hub `cors_origins` excludes Beacon |
| Toasts only when tab open | Expected — use [Web Push](NOTIFICATIONS.md#web-push-pwa) for background |
| Push checkbox missing | Separate: set `VAPID_*` (Mercure not required) |

After changing hub env vars:

```bash
docker compose up -d --force-recreate mercure
```

---

## Related

- Member alerts overview: [NOTIFICATIONS.md](NOTIFICATIONS.md#member-push-mercure--web-push)
- Production Compose: [`compose.prod.yaml`](../compose.prod.yaml)
- Local Caddy proxy: [`.docker/frankenphp/Caddyfile`](../.docker/frankenphp/Caddyfile)
- Env templates: [`.env.dist`](../.env.dist)
