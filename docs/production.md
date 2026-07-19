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

FrankenPHP HTTP modes (`FRANKENPHP_MODE=classic|worker`, `LOOP_MAX`, `RESET_KERNEL`) work the same in prod — see [`frankenphp-coding.md`](frankenphp-coding.md).

## Build

```bash
docker build --target frankenphp_prod -t symfony-frankenphp:prod .
```

CI already builds this target (`.github/workflows/ci.yml`).

`.dockerignore` excludes `.env*` so secrets from your laptop are **not** copied into the image. Runtime must supply at least:

- `APP_SECRET`
- `DATABASE_URL` (or Compose-equivalent MySQL vars)
- `MESSENGER_TRANSPORT_DSN` if you run async workers
- Optional: `FRANKENPHP_MODE`, `FRANKENPHP_WORKER_NUM`, `FRANKENPHP_LOOP_MAX`, `FRANKENPHP_RESET_KERNEL`

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

## Messenger in production

Keep the **HTTP** container separate from the **`messenger:consume`** process (same as local Compose). Scale consumers independently; do not confuse them with `FRANKENPHP_MODE=worker`.

## Out of scope (intentionally)

This boilerplate does **not** ship Kubernetes manifests, TLS termination in front of Caddy, or a managed database. Use the prod image as the application unit inside your own platform.
