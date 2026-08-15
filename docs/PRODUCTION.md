# Production image (optional)

This repo’s default [`compose.yaml`](../compose.yaml) is a **local/dev app** stack (`frankenphp_dev` + bind-mount `./:/app`).  
MySQL and Redis live in [`compose.infra.yaml`](../compose.infra.yaml) (see [SHARED-SERVER.md](ops/SHARED-SERVER.md)).  
The Dockerfile also defines a **`frankenphp_prod`** target for baked, no-dev deployments.

## What “prod” means here

| Piece | Dev (`compose.yaml`) | Prod image |
|---|---|---|
| Docker target | `frankenphp_dev` | `frankenphp_prod` |
| Source | Bind-mount `./:/app` | Copied into the image at build |
| Composer | Live `vendor/` on host mount | `composer install --no-dev` in image |
| PHP / Caddy | Xdebug available, `--watch` | Production `php.ini`, no watch |
| Secrets | Local `.env` (gitignored) | Inject at **runtime** (env / orchestrator) |

FrankenPHP HTTP modes (`FRANKENPHP_MODE=classic|worker`, `LOOP_MAX`, `RESET_KERNEL`) work the same in prod — see [`FRANKENPHP-CODING.md`](ops/FRANKENPHP-CODING.md).

## Build

```bash
docker build --target frankenphp_prod -t symfony-frankenphp:prod .
```

CI already builds this target (`.github/workflows/ci.yml`).

`.dockerignore` excludes `.env*` so secrets from your laptop are **not** copied into the image. Runtime must supply at least:

- `APP_SECRET`
- `DATABASE_URL` (or Compose-equivalent MySQL vars)
- `MESSENGER_TRANSPORT_DSN` if you run async workers (default Redis: `redis://redis-8.10.0:6379` — stream names come from `messenger.yaml`; do **not** append `/messages` on shared Redis. Drain Doctrine `messenger_messages` before switching)
- `REDIS_URL` (sessions, `cache.app` / rate limits, Messenger streams)
- `SITE_SETUP_TOKEN` — unique secret for `/setup?token=…` (never leave empty; never reuse historically known local values)
- `SITE_BACKUP_PASSWORD_HASH` — bcrypt/argon hash for `/_site_backup` (generate with `nowo:site-backup:hash-password`; never commit)
- Optional: `FRANKENPHP_MODE`, `FRANKENPHP_WORKER_NUM`, `FRANKENPHP_LOOP_MAX`, `FRANKENPHP_RESET_KERNEL`

`App\Setup\SiteBackupSecurityDefaultsGuard` **refuses HTTP (and most console) boots outside local `dev`/`test`** (including `prod`, `staging`, and any other `APP_ENV`) if `SITE_SETUP_TOKEN` is empty or a historically known local value, `SITE_BACKUP_PASSWORD_HASH` is empty or a historically known local hash, `APP_SECRET` is empty / still `ChangeMePleaseUseARealSecret` / shorter than 16 characters, or `MERCURE_JWT_SECRET` is set to the documented `.env.dist` placeholder / shorter than 32 characters when non-empty. Empty `MERCURE_JWT_SECRET` is allowed when Mercure is unused (admin DB override may still apply). `compose.prod.yaml` also requires secrets via `${VAR:?…}`.

Do **not** run `app:seed-demo` on production instances (blocked unless `--allow-non-local`, which never installs the documented stable DEMO_* API keys). Configure Prometheus scrape with a metrics Bearer token under Administration → Ops defaults (`metrics_require_token` defaults to on for new installs).

Image builds (`frankenphp_prod` `composer post-install-cmd` → `cache:clear`) skip the guard for `cache:clear` / `cache:warmup` / `assets:install` only, so the Docker bake can warm caches without embedding runtime SiteBackup secrets. The first real HTTP request still fails closed until operators inject unique secrets.

The prod image runs `pnpm install --frozen-lockfile` and `pnpm run build` so `public/build/` is baked in (no Vite HMR container in production).

## Run (example)

Minimal one-off HTTP process (MySQL must be reachable via `DATABASE_URL` on the Compose/Docker network — DB ports are **not** published on the host):

```bash
docker run --rm -p 8080:80 -p 8443:443 \
  --network server_network \
  -e APP_ENV=prod \
  -e APP_SECRET="$(openssl rand -hex 16)" \
  -e DATABASE_URL="mysql://app:CHANGE_ME@mysql-9.7-primary:3306/app?serverVersion=9.7&charset=utf8mb4" \
  -e MESSENGER_TRANSPORT_DSN="redis://redis-8.10.0:6379" \
  -e REDIS_URL="redis://redis-8.10.0:6379" \
  -e FRANKENPHP_MODE=worker \
  symfony-frankenphp:prod
```

Optional Compose overlay for a full app stack without bind-mounts (infra first):

```bash
make up-infra
make up-prod
# or: docker compose -f compose.prod.yaml --env-file .env.local up --build -d
```

See [`compose.prod.yaml`](../compose.prod.yaml). Prefer a real secrets manager in production; do not reuse the `!ChangeMe!` placeholders from `.env.dist`.

## Field encryption key (Halite)

Beacon encrypts notification webhook URLs, push subscription endpoints, **AuthKit OAuth client secrets + linked-account tokens** (AuthKit **≥ 1.15**), and **instance Mailer + Mercure settings** (DSN, From, hub URLs, JWT secret) with [`nowo-tech/doctrine-encrypt-bundle`](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) (Halite). **Project ingest API key secrets** are stored as **SHA-256 hashes** (`secret_hash`) from **v1.12.0** — not recoverable Halite ciphertext (legacy encrypted `secret_key` rows upgrade on successful ingest).

After upgrading AuthKit to **1.15+**, encrypt any existing plaintext social rows:

```bash
docker compose exec php bin/console doctrine:encrypt:database --force
```

(Plaintext without the `<ENC>` marker still loads; the next flush or the command above writes ciphertext.)

| Approach | Notes |
|----------|--------|
| **Volume `php_secrets`** (default in [`compose.prod.yaml`](../compose.prod.yaml)) | Mounts `/app/var/secrets` so `.Halite.default.key` survives container recreates. Share the same volume with the `messenger` service. |
| **`APP_ENCRYPT_KEY`** | Optional: switch the encrypt profile to `secret_key_env_var` (see `config/packages/nowo_doctrine_encrypt.yaml` comments) and inject the key from your secret manager. |

Without a durable key, recreating the PHP container generates a new Halite key and **breaks decryption** of existing ciphertext.

Generate a key once (then back it up with other secrets):

```bash
docker compose -f compose.prod.yaml exec php bin/console doctrine:encrypt:generate-secret-key
```

Admin changes to the instance Mailer DSN / From are recorded as `UserAction` `instance.mailer_updated` with **redacted** `scheme` and `host` only (never the DSN secret). Plaintext DSN input must use an allowlisted Mailer scheme (`smtp` / `smtps` and common provider schemes). **`sendmail` / `native` are rejected** — Symfony’s sendmail transport accepts a free-form `?command=` that would allow host process execution from the admin UI.

**HTTP ingest:** the production Caddyfile (`Caddyfile.prod`) redirects **all** HTTP traffic (including `/api/*`) to `DEFAULT_URI` HTTPS. Cleartext Envelope ingest is a **local/dev** convenience only (`compose.yaml` bind-mounts the development `Caddyfile`). Production clients must use an `https://…` DSN.

**Mailer in production:** configure a real SMTP or provider DSN under **Administration → Mailer**. The local **Mailpit** catcher (`make mailpit`, Compose profile `mail` in `compose.override.yaml`) is for development only and is **absent** from [`compose.prod.yaml`](../compose.prod.yaml) — see [MAILPIT.md](ops/MAILPIT.md).

## Messenger in production

Keep the **HTTP** container separate from Messenger consumers (same as local Compose). Two Compose services isolate ingest from outbound work:

| Service | Transport | Role |
|---------|-----------|------|
| `messenger` | `async_ingest` | Envelope persistence |
| `messenger-notify` | `async` | Notifications, HTTP-log, Web Push |

Scale each independently; do not confuse them with `FRANKENPHP_MODE=worker`.

Example (scale ingest workers):

```bash
docker compose up -d --scale messenger=2
# Optional: scale outbound independently
docker compose up -d --scale messenger-notify=2
```

Monitor queue depth via authenticated `GET /metrics` → `beacon_messenger_async_pending` (Doctrine transport). Do not rely on the public readiness probe for backlog; `/health/ready` checks the database only.

**Failed transport:** messages that exhaust retries land in the Doctrine `failed` queue (`queue_name=failed`). Envelope payloads may contain application PII even after DSN scrubbing — treat the failed table as sensitive, restrict DB access, and purge periodically:

```bash
docker compose exec php bin/console messenger:failed:show
# After review / replay:
docker compose exec php bin/console messenger:failed:remove --all   # or remove by id
```

Do not leave failed envelopes indefinitely on shared production databases.

## Health probes

| Path | Auth | Purpose |
|------|------|---------|
| `GET /health/live` | Public | Process is up |
| `GET /health/ready` | Public | Database reachable |

Use `/health/live` for liveness and `/health/ready` for readiness in Kubernetes/Compose healthchecks.

## Maintenance mode

Administration → **Maintenance** (`nowo-tech/maintenance-mode-bundle`) can return **503** for the public site during upgrades.

Configured exclusions keep AuthKit, health, setup, and **ingest** reachable:

| Path pattern | During maintenance |
|--------------|--------------------|
| `/api/{project}/envelope/` | Reachable (ingest continues) |
| `/api/{project}/otlp/…` | Reachable (OTLP ingest continues) |
| `/api/projects/…` (Read API) | **503** (not excluded) |
| `/admin/maintenance` | Always reachable for `ROLE_ADMIN` |

Do not assume a blanket `/api/` exclusion — that was removed in **v1.11.0** (`095`).

## Prometheus metrics (`/metrics`)

`GET /metrics` exposes Prometheus text exposition (`beacon_messenger_async_pending`, `beacon_notification_destinations_failed`, `beacon_ingest_ack_total`, `beacon_ingest_reject_total`).

| Access | How |
|--------|-----|
| Admin UI | Logged-in `ROLE_ADMIN` session |
| Scraper | Set a metrics scrape token under **Administration → Ops defaults** and send `Authorization: Bearer …` only (query `?token=` is rejected) |

Enable **Require metrics scrape token** in production (Ops defaults). When required and no token is stored, `/metrics` returns **503**.

**Do not** expose `/metrics` on the public internet without a token and/or network ACL (private scrape network, reverse-proxy allowlist). The FrankenPHP `Caddyfile` includes a commented `remote_ip` snippet for private-only scrapes. Counters live in `cache.app` (Redis when `REDIS_URL` is set — shared across workers/replicas).

## Retention purge

Configure retention days and the maximum stored events per project under **Administration → Ops defaults**. Both values use `0` to disable that retention rule; projects may override them in Project Settings.

Run daily (cron / systemd timer):

```bash
php bin/console app:retention:purge
# or
make console ARGS='app:retention:purge'
```

Also schedule HTTP audit-log retention (HttpLogBundle, default 30 days):

```bash
php bin/console nowo:http-log:purge
# or
make console ARGS='nowo:http-log:purge'
```

## Ingest rate limit

Set the maximum Envelope POSTs per project per minute under **Administration → Ops defaults** (`0` = unlimited). Projects may override it. Exceeded requests get HTTP `429` with `Retry-After: 60`.

Daily and monthly event quotas are configured on the same page (`0` = unlimited) and also return `429` (`daily event quota exceeded` / `monthly event quota exceeded`).

Pre-auth **IP** sliding windows (env, default 120/min; `0` disables):

| Env | Surface |
|-----|---------|
| `BEACON_INGEST_IP_RATE_LIMIT` | Envelope / OTLP before credential lookup |
| `BEACON_HOOK_IP_RATE_LIMIT` | Public Slack / Teams actions / inbound-email hooks |
| `BEACON_READ_API_RATE_LIMIT` | Bearer Read API `/api/projects/…` |

### Query-string ingest auth

Query `beacon_key` / `beacon_secret` is **removed**. Clients must use `X-Beacon-Auth` or envelope `dsn`. Requests that still send query credentials receive **401**.

## Security headers (Caddy)

The FrankenPHP `Caddyfile` sets baseline headers on the HTTPS site: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and **HSTS** (skipped for `localhost` / `127.0.0.1` so local self-signed HTTPS is not sticky-pinned). **`Content-Security-Policy`** is set in PHP (`ContentSecurityPolicySubscriber`) with `object-src 'none'` and **`script-src 'self'`** (no `'unsafe-inline'`), so the Web Debug Toolbar can merge its script/style **nonces** into the same header. In **`kernel.debug`**, CSP also allows `'unsafe-eval'` because the toolbar `eval()`s scripts from the `/_wdt` AJAX fragment (prod stays without `'unsafe-eval'` except Swagger UI). Theme boot uses blocking IIFE from `assets/theme-boot.ts` (`public/build/theme-boot.js`); kit admin uses Vite `kit-admin` (self-hosted Bootstrap); confirm dialogs / password toggle / selects use Stimulus. Vendor kit page `window.*Config` scripts are rewritten to JSON islands (`KitInlineConfigScriptSubscriber`). Production **`style-src-elem`** is `'self' 'nonce-…'` (host `<style>` blocks use `csp_nonce()`); **`style-src-attr 'unsafe-inline'`** allows CSSOM (`element.style`) used by SortableJS and shell motion. **`kernel.debug`** also allows `'unsafe-inline'` on `style-src-elem` for the Web Profiler. **`connect-src`** is `'self' ws: wss:` plus a cross-origin Mercure hub origin from `MERCURE_PUBLIC_URL` when needed; member realtime prefers same-origin `/.well-known/mercure` via Caddy.

- **HSTS:** default `max-age=31536000; includeSubDomains` on non-loopback hosts. Override or extend via `CADDY_SERVER_EXTRA_DIRECTIVES` if you terminate TLS elsewhere (or need `preload`).
- Do not ship analytics cookies without cookie-consent kit UX.
- **Swagger UI** (`/admin/api/doc`) still needs `script-src 'unsafe-eval'` (JSON Schema compile). The PHP CSP subscriber uses a path-specific policy (no `'unsafe-inline'`); Swagger assets are same-origin (`nelmio_api_doc.html_config.assets_mode: bundle`) and boot via Vite `swagger-ui-boot`.

`/admin/api/doc` and `/admin/api/doc.json` require **`ROLE_ADMIN`**.

Outbound webhooks: keep **Allow private notification URLs** disabled in production (Ops defaults). Delivery pins DNS (`resolve`) after `OutboundUrlGuard` validation (anti rebinding).

## Login throttling

`nowo-tech/login-throttle-bundle` + Symfony `login_throttling` on the `main` firewall (default: 5 attempts / 15 minutes). **Default storage is `database`** so counters are shared across FrankenPHP workers and multi-pod deployments (`login_attempts` table). Tune `config/packages/nowo_login_throttle.yaml` and keep `security.yaml` in sync (`nowo:login-throttle:configure-security --force`).

## Backups

Minimum operator checklist:

1. **MySQL**: scheduled `mysqldump` (or volume snapshots) of `./.data/infra/mysql-primary` / managed DB.
2. **Secrets**: backup `.env` / secret manager entries (`APP_SECRET`, DB passwords, webhook URLs) **separately** from SiteBackup archives — SiteBackup `include_paths` intentionally **omits `.env`**.
3. **Encrypt key**: include `var/secrets/.Halite.default.key` (or `APP_ENCRYPT_KEY`) with secret backups — see [Field encryption key](#field-encryption-key-halite).
4. **After restore**: run `doctrine:migrations:migrate`, restart `php` + `messenger`, confirm `/health/ready`.

## Operational inventory (deploy checklist)

Use this list before exposing an instance beyond a trusted network. Details live in the sections above.

| Area | Must configure / verify | Notes |
|------|-------------------------|--------|
| **SiteBackup / setup** | Unique `SITE_SETUP_TOKEN`, unique `SITE_BACKUP_PASSWORD_HASH`; prefer `X-Setup-Token` header | Query `?token=` works for the wizard — rotate after first setup; fail-closed outside `dev`/`test` if local defaults remain |
| **App secrets** | `APP_SECRET`, DB credentials, Halite key volume or `APP_ENCRYPT_KEY` | Losing the encrypt key breaks ciphertext |
| **Messenger** | Separate `messenger:consume`; watch `/metrics` queue gauge; purge `messenger:failed` periodically | Failed envelopes may hold PII; do not conflate with `FRANKENPHP_MODE=worker` |
| **Retention / quotas** | Ops defaults + optional per-project overrides; schedule `app:retention:purge` | `0` disables that rule |
| **HTTP audit log** | Admin → HTTP log (`/admin/http-log`); schedule `nowo:http-log:purge` | Default retention 30 days; may store IPs / user ids |
| **Ingest** | `X-Beacon-Auth` or envelope DSN only (query auth removed); secrets are SHA-256 at rest | Rotate project API key secrets if leaked |
| **Read API** | `BEACON_READ_API_RATE_LIMIT` (default 120/min); Bearer `brt_…` only | Returns 503 under maintenance (`095`) |
| **Public hooks** | `BEACON_HOOK_IP_RATE_LIMIT` (default 120/min); Teams Assign query HMAC may appear in logs/Referer; action tokens TTL **24h** | Assign-me is session-gated and excluded from the IP throttle |
| **Notification webhooks** | Keep allow-private-URLs off in Ops defaults; treat destination **signing secrets** as high privilege | Slack/Teams **Resolve** requires a mapped Beacon actor unless allow-anonymous-Resolve is enabled (legacy). Rotate secrets if leaked. |
| **Inbound email hook** | Enable + domain + webhook secret in Ops defaults; header **`X-Beacon-Inbound-Secret` only** | Body `beacon_secret` is rejected |
| **`/metrics`** | Set metrics token in Ops defaults; enable require-token in production (Ops Overview warns when off) | Prefer private scrape network / Caddy `remote_ip` allowlist |
| **Health** | `/health/live` + `/health/ready` for probes | Ready checks DB only; queue depth is on `/metrics` |
| **Trusted proxies** | If TLS terminates **in front of** Caddy/FrankenPHP, set Symfony `trusted_proxies` / `SYMFONY_TRUSTED_PROXIES` to **only** the real load balancer CIDRs | Too-broad trusts let clients forge `X-Forwarded-For` and bypass ingest/hook/read **IP rate limits** and login throttle. Default Compose terminates TLS in Caddy — leave empty unless you have an outer proxy |
| **First admin** | Complete `/setup` and/or first `/register` (**`registration_mode: first_user_only` → `ROLE_ADMIN`**) **before** publishing the HTTP(S) port | A fresh instance exposed before the first register lets anyone become admin |
| **Demo client env** | Keep `.demo-client.env` at mode **600** (seed/dogfood now chmod); never commit it | Contains `BEACON_DSN` + demo login — gitignored, but world-writable copies on shared WSL/hosts leak credentials |
| **Twig `\|raw`** | Appearance CSS overrides, breadcrumb HTML, kit JSON islands, Swagger boot JSON, `json_encode` in `onsubmit` | Controlled sources only; do not `|raw` user/event payload HTML |
| **Mailer / Mercure** | Real DSN/hub in Admin; never ship Mailpit in prod Compose | Encrypted at rest via Halite |
| **Legal / cookies** | Privacy/terms/cookies pages; consent kit for non-essential cookies | Required for public operator UX |

## Out of scope (intentionally)

This boilerplate does **not** ship Kubernetes manifests, TLS termination in front of Caddy, or a managed database. Use the prod image as the application unit inside your own platform.
