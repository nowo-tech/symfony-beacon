# FrankenPHP hot reload (local development)

Local Docker uses FrankenPHP’s built-in **hot reload**: when you change PHP, Twig, config, or assets, the browser updates without a manual refresh.

This is **dev-only**. The **server** side is Caddy `php_server { hot_reload }` plus the built-in Mercure hub. The **client** comes from [`nowo-tech/hot-reload-bundle`](https://github.com/nowo-tech/HotReloadBundle) ≥**1.5.2** (`auto_inject: true`, `client_mode: shared_worker`).

Official server docs: [frankenphp.dev/docs/hot-reload](https://frankenphp.dev/docs/hot-reload/). Bundle: [CONFIGURATION.md](https://github.com/nowo-tech/HotReloadBundle/blob/main/docs/CONFIGURATION.md) / [USAGE.md](https://github.com/nowo-tech/HotReloadBundle/blob/main/docs/USAGE.md). CLI: `bin/console nowo:hot-reload:check`.

This is independent of Vite HMR: Vite still owns frontend module HMR; FrankenPHP hot reload covers PHP/Twig (and rebuilt assets) via DOM morph. There is no host Vite/Twig deferred client.

## What is wired

| Piece | Role |
|-------|------|
| `.docker/frankenphp/Caddyfile` | `php_server { hot_reload { watch … } }` + built-in `mercure` (dev only); HTTP Mercure is reverse-proxied to that hub for Messenger |
| `FRANKENPHP_WORKER_CONFIG=watch` | With `make worker`, restarts PHP workers when watched files change |
| `nowo-tech/hot-reload-bundle` **≥1.5.2** (`require-dev`) | Dev/test only: `auto_inject: true`, `client_mode: shared_worker`, CSP nonce (`_beacon_csp_nonce`) + `script-src` augment (`cdn.jsdelivr.net`), Web Debug Toolbar panel `nowo_hot_reload`. CLI: `bin/console nowo:hot-reload:check`. |
| `/_nowo/hot-reload/client.js` + `shared-worker.js` | Bundle-served same-origin client (one Mercure SSE for all tabs) |
| `.docker/frankenphp/Caddyfile.prod` | **No** hot reload (production) |

## Multi-tab modes

See the Web Debug Toolbar **Hot Reload** panel → **Multi-tab client modes (help)** for a comparison of `cdn` / `visibility` / `shared_worker` / `always` and HTTP/2 infra.

This project uses **`shared_worker`** so several admin tabs stay connected without exhausting HTTP/1.1 slots. Host CSP keeps `worker-src 'self'`.

## How to use

1. Start the stack (`make up` / `make classic` / `make worker`).
2. Run `make vite-build` after changing TypeScript/SCSS (or use the optional Vite HMR profile).
3. Edit a Twig/PHP file under the watched paths — the open tab should morph/reload.
4. Optional: `bin/console nowo:hot-reload:check` (pass/fail/warn). Open the Symfony Web Debug Toolbar → **Hot Reload** (`on` / `ready` / `idle` / `off`).

Watched paths (see Caddyfile): `src/`, `config/`, `templates/`, `translations/`, `assets/`, `.env`, `composer.json`.

No Twig includes are required: with `auto_inject: true`, `HotReloadResponseSubscriber` inserts assets before `</head>`.

## CSP note

Host CSP stays `script-src 'self' 'nonce-…'` in debug (no always-on jsDelivr). When Hot Reload injects, the bundle appends `https://cdn.jsdelivr.net` via `csp_augment_script_src` and stamps the preserve boot script with the `_beacon_csp_nonce` request attribute. Production does **not** register this bundle (`allow_production: false`). Do not enable `hot_reload` in production Compose.

## Mercure note

Hot reload publishes through FrankenPHP’s **built-in** Mercure hub. Local `MERCURE_URL` therefore points at `http://php/.well-known/mercure` (same hub the UI uses). Production still uses the Compose `mercure` service — see [MERCURE.md](MERCURE.md).

## Disable temporarily

Comment out the `hot_reload { … }` block in `.docker/frankenphp/Caddyfile` and recreate the `php` container. Do not enable hot reload in `Caddyfile.prod`. To disable the client only, set `nowo_hot_reload.enabled: false` under `when@dev`.
