# Quickstart: E2E cold-start circuit (`110`)

Disposable empty-DB install → SiteBackup `/setup` → AuthKit login. Does **not** use `ready-e2e`.

```bash
make wipe-e2e-cold           # drop app_e2e_cold + cold volumes + Redis DB 2
make up-e2e-cold             # :9461 / classic FrankenPHP / .env.e2e.cold.local
curl -kfsS https://localhost:9461/health/live
make test-e2e-cold           # wipe + up + Playwright project `cold` (workers=1)
# optional: make down-e2e-cold
```

| | Warm (`104` / `108`) | Cold (`110`) |
|--|----------------------|--------------|
| Project | `symfony-beacon-e2e` | `symfony-beacon-e2e-cold` |
| Schema | `app_e2e` | `app_e2e_cold` |
| HTTPS | `:9460` | `:9461` |
| Redis DB | `1` | `2` |
| Seed | `ready-e2e` / lite | **none** (wizard owns migrate/seed) |
| FrankenPHP | worker (default) | **classic** |
| Playwright | `chromium` (+ auth setup) | project `cold` only |

Token: `SITE_SETUP_TOKEN=beacon-local-setup` (`X-Setup-Token` or `?token=`). Warm suites must **never** `POST /setup/api/advance`.

See `e2e/README.md` and `docs/product/E2E-USE-CASES.md` (UC-SETUP-01 / UC-SETUP-07 / UC-AUTH-10).
