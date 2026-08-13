# FrankenPHP hot reload (local development)

Local Docker uses FrankenPHP’s built-in **hot reload**: when you change PHP, Twig, config, or assets, the browser updates without a manual refresh.

Official docs: [frankenphp.dev/docs/hot-reload](https://frankenphp.dev/docs/hot-reload/). Client library: [`frankenphp-hot-reload`](https://www.npmjs.com/package/frankenphp-hot-reload).

## What is wired

| Piece | Role |
|-------|------|
| `.docker/frankenphp/Caddyfile` | `php_server { hot_reload { watch … } }` + built-in `mercure` (dev only); HTTP Mercure is reverse-proxied to that hub for Messenger |
| `FRANKENPHP_WORKER_CONFIG=watch` | With `make worker`, restarts PHP workers when watched files change |
| `assets/frankenphp-hot-reload.ts` | Idiomorph + Mercure EventSource client (Vite entry) |
| `templates/_frankenphp_hot_reload.html.twig` | Meta + script only when `FRANKENPHP_HOT_RELOAD` is set |
| `.docker/frankenphp/Caddyfile.prod` | **No** hot reload (production) |

## How to use

1. Start the stack (`make up` / `make classic` / `make worker`).
2. Run `make vite-build` after changing TypeScript/SCSS (or use the optional Vite HMR profile).
3. Edit a Twig/PHP file under the watched paths — the open tab should morph/reload.

Watched paths (see Caddyfile): `src/`, `config/`, `templates/`, `translations/`, `assets/`, `.env`, `composer.json`.

## Mercure note

Hot reload publishes through FrankenPHP’s **built-in** Mercure hub. Local `MERCURE_URL` therefore points at `http://php/.well-known/mercure` (same hub the UI uses). Production still uses the Compose `mercure` service — see [MERCURE.md](MERCURE.md).

## Disable temporarily

Comment out the `hot_reload { … }` block in `.docker/frankenphp/Caddyfile` and recreate the `php` container. Do not enable hot reload in `Caddyfile.prod`.
