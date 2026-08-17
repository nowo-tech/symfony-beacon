# FrankenPHP hot reload (local development)

Local Docker uses FrankenPHP’s built-in **hot reload**: when you change PHP, Twig, config, or assets, the browser updates without a manual refresh.

Official docs: [frankenphp.dev/docs/hot-reload](https://frankenphp.dev/docs/hot-reload/). Client integration: [`nowo-tech/hot-reload-bundle`](https://packagist.org/packages/nowo-tech/hot-reload-bundle) ≥**1.3.0** (wraps [`frankenphp-hot-reload`](https://www.npmjs.com/package/frankenphp-hot-reload) + optional Idiomorph from jsDelivr).

## What is wired

| Piece | Role |
|-------|------|
| `.docker/frankenphp/Caddyfile` | `php_server { hot_reload { watch … } }` + built-in `mercure` (dev only); HTTP Mercure is reverse-proxied to that hub for Messenger |
| `FRANKENPHP_WORKER_CONFIG=watch` | With `make worker`, restarts PHP workers when watched files change |
| `nowo-tech/hot-reload-bundle` | Dev/test: auto-inject client, CSP nonce (`_beacon_csp_nonce`) + `script-src` augment, Web Debug Toolbar panel `nowo_hot_reload` |
| `.docker/frankenphp/Caddyfile.prod` | **No** hot reload (production) |

## How to use

1. Start the stack (`make up` / `make classic` / `make worker`).
2. Run `make vite-build` after changing TypeScript/SCSS (or use the optional Vite HMR profile).
3. Edit a Twig/PHP file under the watched paths — the open tab should morph/reload.
4. Optional: open the Symfony Web Debug Toolbar → **Hot Reload** (`on` / `ready` / `idle` / `off`).

Watched paths (see Caddyfile): `src/`, `config/`, `templates/`, `translations/`, `assets/`, `.env`, `composer.json`.

No Twig includes are required: with `auto_inject: true`, `HotReloadResponseSubscriber` inserts assets before `</head>`.

## CSP note

Host CSP stays `script-src 'self' 'nonce-…'` in debug. When Hot Reload injects, the bundle appends `https://cdn.jsdelivr.net` via `csp_augment_script_src` and stamps the preserve boot script with the `_beacon_csp_nonce` request attribute. Production does **not** load this bundle. Do not enable `hot_reload` in production Compose.

## Mercure note

Hot reload publishes through FrankenPHP’s **built-in** Mercure hub. Local `MERCURE_URL` therefore points at `http://php/.well-known/mercure` (same hub the UI uses). Production still uses the Compose `mercure` service — see [MERCURE.md](MERCURE.md).

## Disable temporarily

Comment out the `hot_reload { … }` block in `.docker/frankenphp/Caddyfile` and recreate the `php` container. Do not enable hot reload in `Caddyfile.prod`. To disable the client only, set `nowo_hot_reload.enabled: false` under `when@dev`.
