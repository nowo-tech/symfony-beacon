# Production image (optional)

This repo’s default [`compose.yaml`](../compose.yaml) is a **local/dev** stack (`frankenphp_dev` + bind-mount `./:/app`).  
The Dockerfile also defines a **`frankenphp_prod`** target for baked, no-dev deployments.

## What “prod” means here

| Piece | Dev (`compose.yaml`) | Prod image |
|---|---|---|
| Docker target | `frankenphp_dev` | `frankenphp_prod` |
| Source | Bind-mount `./:/app` | Copied into the image at build |
| Composer | Live `vendor/` on host mount | `composer install --no-dev` in image |
| PHP / Caddy | Xdebug available, `--watch` | Production `php.ini`, no watch |
| Secrets | Local `.env` (gitignored) | Inject at **runtime** (env / orchestrator) |

FrankenPHP HTTP modes (`FRANKENPHP_MODE=classic|worker`, `LOOP_MAX`, `RESET_KERNEL`) work the same in prod — see [`FRANKENPHP-CODING.md`](FRANKENPHP-CODING.md).

## Build

```bash
docker build --target frankenphp_prod -t symfony-frankenphp:prod .
```

CI already builds this target (`.github/workflows/ci.yml`).

`.dockerignore` excludes `.env*` so secrets from your laptop are **not** copied into the image. Runtime must supply at least:

- `APP_SECRET`
- `DATABASE_URL` (or Compose-equivalent MySQL vars)
- `MESSENGER_TRANSPORT_DSN` if you run async workers
- `SITE_SETUP_TOKEN` — unique secret for `/setup?token=…` (not the `.env.dist` local default)
- `SITE_BACKUP_PASSWORD_HASH` — bcrypt/argon hash for `/_site_backup` (not the `.env.dist` local default)
- Optional: `FRANKENPHP_MODE`, `FRANKENPHP_WORKER_NUM`, `FRANKENPHP_LOOP_MAX`, `FRANKENPHP_RESET_KERNEL`

`App\Setup\SiteBackupSecurityDefaultsGuard` **refuses HTTP (and most console) boots outside local `dev`/`test`** (including `prod`, `staging`, and any other `APP_ENV`) if `SITE_SETUP_TOKEN` is empty/local or `SITE_BACKUP_PASSWORD_HASH` is still the documented local hash. `compose.prod.yaml` also requires both variables via `${VAR:?…}`.

Image builds (`frankenphp_prod` `composer post-install-cmd` → `cache:clear`) skip the guard for `cache:clear` / `cache:warmup` / `assets:install` only, so the Docker bake can warm caches without embedding runtime SiteBackup secrets. The first real HTTP request still fails closed until operators inject unique secrets.

The prod image runs `pnpm install --frozen-lockfile` and `pnpm run build` so `public/build/` is baked in (no Vite HMR container in production).

## Run (example)

Minimal one-off HTTP process (MySQL must be reachable via `DATABASE_URL`):

```bash
docker run --rm -p 8080:80 -p 8443:443 \
  -e APP_ENV=prod \
  -e APP_SECRET="$(openssl rand -hex 16)" \
  -e DATABASE_URL="mysql://app:CHANGE_ME@host.docker.internal:3307/app?serverVersion=9.7&charset=utf8mb4" \
  -e MESSENGER_TRANSPORT_DSN="doctrine://default?auto_setup=0" \
  -e FRANKENPHP_MODE=worker \
  symfony-frankenphp:prod
```

Optional Compose overlay for a full stack without bind-mounts:

```bash
docker compose -f compose.prod.yaml --env-file .env up --build -d
```

See [`compose.prod.yaml`](../compose.prod.yaml). Prefer a real secrets manager in production; do not reuse the `!ChangeMe!` placeholders from `.env.dist`.

## Field encryption key (Halite)

Beacon encrypts API key secrets, notification webhook URLs, push subscription endpoints, and **instance Mailer + Mercure settings** (DSN, From, hub URLs, JWT secret) with [`nowo-tech/doctrine-encrypt-bundle`](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) (Halite).

| Approach | Notes |
|----------|--------|
| **Volume `php_secrets`** (default in [`compose.prod.yaml`](../compose.prod.yaml)) | Mounts `/app/var/secrets` so `.Halite.default.key` survives container recreates. Share the same volume with the `messenger` service. |
| **`APP_ENCRYPT_KEY`** | Optional: switch the encrypt profile to `secret_key_env_var` (see `config/packages/nowo_doctrine_encrypt.yaml` comments) and inject the key from your secret manager. |

Without a durable key, recreating the PHP container generates a new Halite key and **breaks decryption** of existing ciphertext.

Generate a key once (then back it up with other secrets):

```bash
docker compose -f compose.prod.yaml exec php bin/console doctrine:encrypt:generate-secret-key
```

## Messenger in production

Keep the **HTTP** container separate from the **`messenger:consume`** process (same as local Compose). Scale consumers independently; do not confuse them with `FRANKENPHP_MODE=worker`.

Example (two consumer replicas):

```bash
docker compose up -d --scale messenger=2
```

Monitor queue depth via `GET /health/ready` → `checks.messenger_async_pending` (Doctrine transport).

## Health probes

| Path | Auth | Purpose |
|------|------|---------|
| `GET /health/live` | Public | Process is up |
| `GET /health/ready` | Public | Database reachable + async queue depth |

Use `/health/live` for liveness and `/health/ready` for readiness in Kubernetes/Compose healthchecks.

## Prometheus metrics (`/metrics`)

`GET /metrics` exposes Prometheus text exposition (`beacon_messenger_async_pending`, `beacon_notification_destinations_failed`, `beacon_ingest_ack_total`, `beacon_ingest_reject_total`).

| Access | How |
|--------|-----|
| Admin UI | Logged-in `ROLE_ADMIN` session (only when a token is configured in prod — see below) |
| Scraper | Set `BEACON_METRICS_TOKEN` and send `Authorization: Bearer …` only (query `?token=` is rejected) |

| Env | Default | Meaning |
|-----|---------|---------|
| `BEACON_METRICS_TOKEN` | empty | Bearer scrape secret |
| `BEACON_METRICS_REQUIRE_TOKEN` | `0` in dist; **`1` in `APP_ENV=prod`** | When `1`, empty token → **503** until configured |

**Do not** expose `/metrics` on the public internet without a token and/or network ACL (private scrape network, reverse-proxy allowlist). The FrankenPHP `Caddyfile` includes a commented `remote_ip` snippet for private-only scrapes. Counters live in `cache.app` (shared only if your cache backend is shared across workers).

## Retention purge

Configure in `.env`:

| Variable | Meaning |
|----------|---------|
| `BEACON_RETENTION_DAYS` | Delete events/transactions/stats older than N days (`0` = off) |
| `BEACON_RETENTION_MAX_EVENTS_PER_PROJECT` | Cap stored events per project (`0` = off) |

Run daily (cron / systemd timer):

```bash
php bin/console app:retention:purge
# or
make console ARGS='app:retention:purge'
```

## Ingest rate limit

`BEACON_INGEST_RATE_LIMIT` = max Envelope POSTs per project per minute (`0` = unlimited). Exceeded requests get HTTP `429` with `Retry-After: 60`.

Daily / monthly event quotas also return `429` (`daily event quota exceeded` / `monthly event quota exceeded`).

### Query-string ingest auth

Prefer `X-Beacon-Auth` or envelope `dsn`. Query `beacon_key` / `beacon_secret` is deprecated.

| Env | Default | Meaning |
|-----|---------|---------|
| `BEACON_INGEST_REJECT_QUERY_AUTH` | **`1`** (all envs, including `.env.dist`) | When `1`, query auth returns **401** |

Set `BEACON_INGEST_REJECT_QUERY_AUTH=0` only while migrating clients (any environment).

## Security headers (Caddy)

The FrankenPHP `Caddyfile` sets baseline headers on the HTTPS site: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and **HSTS** (skipped for `localhost` / `127.0.0.1` so local self-signed HTTPS is not sticky-pinned). **`Content-Security-Policy`** is set in PHP (`ContentSecurityPolicySubscriber`) with `object-src 'none'` and **`script-src 'self'`** (no `'unsafe-inline'`), so the Web Debug Toolbar can merge its script/style **nonces** into the same header. In **`kernel.debug`**, CSP also allows `'unsafe-eval'` because the toolbar `eval()`s scripts from the `/_wdt` AJAX fragment (prod stays without `'unsafe-eval'` except Swagger UI). Theme boot uses Vite `theme-boot`; kit admin uses Vite `kit-admin` (self-hosted Bootstrap); confirm dialogs / password toggle / selects use Stimulus. Vendor kit page `window.*Config` scripts are rewritten to JSON islands (`KitInlineConfigScriptSubscriber`). `style-src` still allows `'unsafe-inline'` for operator appearance CSS overrides and small kit layout `<style>` blocks.

- **HSTS:** default `max-age=31536000; includeSubDomains` on non-loopback hosts. Override or extend via `CADDY_SERVER_EXTRA_DIRECTIVES` if you terminate TLS elsewhere (or need `preload`).
- Do not ship analytics cookies without cookie-consent kit UX.
- **Swagger UI** (`/api/doc`) still needs `script-src 'unsafe-eval'` (JSON Schema compile). The PHP CSP subscriber uses a path-specific policy (no `'unsafe-inline'`); Swagger assets are same-origin (`nelmio_api_doc.html_config.assets_mode: bundle`) and boot via Vite `swagger-ui-boot`.

`/api/doc` and `/api/doc.json` require **`ROLE_ADMIN`**.

Outbound webhooks: keep `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS=0` in production. Delivery pins DNS (`resolve`) after `OutboundUrlGuard` validation (anti rebinding).

## Login throttling

`nowo-tech/login-throttle-bundle` + Symfony `login_throttling` on the `main` firewall (default: 5 attempts / 15 minutes). **Default storage is `database`** so counters are shared across FrankenPHP workers and multi-pod deployments (`login_attempts` table). Tune `config/packages/nowo_login_throttle.yaml` and keep `security.yaml` in sync (`nowo:login-throttle:configure-security --force`).

## Backups

Minimum operator checklist:

1. **MySQL**: scheduled `mysqldump` (or volume snapshots) of the Compose `database` data directory / managed DB.
2. **Secrets**: backup `.env` / secret manager entries (`APP_SECRET`, DB passwords, webhook URLs) separately from the DB dump.
3. **Encrypt key**: include `var/secrets/.Halite.default.key` (or `APP_ENCRYPT_KEY`) with secret backups — see [Field encryption key](#field-encryption-key-halite).
4. **After restore**: run `doctrine:migrations:migrate`, restart `php` + `messenger`, confirm `/health/ready`.

## Out of scope (intentionally)

This boilerplate does **not** ship Kubernetes manifests, TLS termination in front of Caddy, or a managed database. Use the prod image as the application unit inside your own platform.
