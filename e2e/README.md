# Playwright browser E2E against the local Compose stack

## Isolated DB (recommended for local dogfooding)

`make test-e2e` hits the **same** MySQL schema as `make up` (`MYSQL_DATABASE`, usually `app`) and creates ephemeral users/projects there.

To keep development data clean, use a **second Compose project** on schema `app_e2e` (Redis DB index `1`, HTTPS `:9460` by default). Dogfood on `:9447` can stay up in parallel — run Playwright against `:9460` without stopping development.

```bash
make up              # dogfood stack stays up (:9447)
make up-e2e          # create app_e2e + start symfony-beacon-e2e (php/messenger*)
make ready-e2e       # migrate + seed + sample + wire BeaconBundle on E2E
make test-e2e-isolated
# optional: make down-e2e   # stop E2E containers; schema app_e2e is kept
```

### BeaconBundle during isolated E2E (detect errors while tests run)

After `make ready-e2e`, the **E2E** PHP containers get `BEACON_DSN` in `.env.e2e.local` only (`.env.local` / dogfood DSN unchanged). Exceptions and Monolog `error+` from the app under test are reported via `nowo-tech/beacon-bundle` (same as dogfood), with `DropSelfIngestBeforeSend` avoiding ingest feedback loops.

| `E2E_BEACON_TARGET` | Where test-time errors appear |
|---------------------|-------------------------------|
| `self` (default) | E2E project issues — open `https://localhost:9460` |
| `dogfood` | Dogfood project issues — open `https://localhost:9447` (uses `.demo-client.env`) |
| `off` | No reporting from the E2E stack |

```bash
E2E_BEACON_TARGET=dogfood make ready-e2e   # re-seed + recreate with dogfood DSN
```

Overrides: `E2E_HTTPS_PORT=9450 E2E_MYSQL_DATABASE=app_e2e make up-e2e` (pick a free host port if `:9460` is taken).

Auth state: `e2e/.auth/admin.e2e.json` (does not overwrite dogfood `admin.json`). Client env: `.demo-client.e2e.env` (does not overwrite `.demo-client.env`). Seed uses `--skip-server-dsn` so `.env.local` `BEACON_DSN` is untouched.

## Dogfood stack (mutates your dev DB)

Prerequisites:

```bash
make up && make seed   # demo admin: admin@symfony-beacon.local / admin123
make seed-sample       # required for issue / performance / analytics assertions
```

Run (recommended — Docker Playwright image with Chromium deps):

```bash
make test-e2e
```

Host run (requires system libs via `pnpm exec playwright install-deps`):

```bash
PLAYWRIGHT_ON_HOST=1 make test-e2e
```

Filters / overrides:

```bash
make test-e2e ARGS='e2e/smoke/public.spec.ts'
make test-e2e-isolated ARGS='e2e/smoke/public.spec.ts'
make test-e2e ARGS='e2e/flows/mutations.spec.ts'
make test-e2e ARGS='e2e/hooks'
make test-e2e ARGS='e2e/**/use-cases-*.spec.ts'
PLAYWRIGHT_BASE_URL=https://localhost:9447 make test-e2e
```

When `CI=1` or `PLAYWRIGHT_REQUIRE_SAMPLE=1`, tests that need sample/demo data **fail** instead of skipping (see `requireSampleOrSkip` in `support/helpers.ts`).

## Layout

Specs are grouped by product domain (Playwright still uses `testDir: ./e2e`):

| Folder | Contents |
|--------|----------|
| `setup/` | `auth.setup.ts` (writes `e2e/.auth/admin.json`) |
| `support/` | Shared helpers |
| `smoke/` | Public/auth chrome, cookies, navigation, misc |
| `account/` | Profile, display prefs, member alerts |
| `admin/` | Hub, users, kits, settings, appearance mutations, analytics admin |
| `project/` | Dashboard, project settings, share, members |
| `issues/` | Issue list / triage deep |
| `ingest/` | Envelope / OTLP / Read API happy paths |
| `hooks/` | Slack / Teams / inbound |
| `notifications/` | Destinations, thresholds, health |
| `flows/` | Cross-cutting mutations and closing suites |
| `security/` | Negative access control: 403 denials, guest redirects, role demotion, inactive membership, auth gates |
| `z-late/` | Specs that must run last (Read API IP rate limit) |

## Product use-case catalog

**All product use cases** (Covered / Partial / Gap / Out of scope) live in:

[`docs/product/E2E-USE-CASES.md`](../docs/product/E2E-USE-CASES.md)

That catalog aims at **100% product-surface definition** (routes + primary operator mutations). Automation is ~259 Covered / ~0 Gap (~5 Out of scope). Extend specs under the matching domain folder when product surface grows.

Security denials (`UC-SEC-01`…`12`): `e2e/security/` — run with:

```bash
make test-e2e ARGS='e2e/security'
```

Warm setup surfaces (`UC-SETUP-04` GET progress / `UC-SETUP-05` done page): `e2e/smoke/use-cases-setup-warm.spec.ts` — never GET `/setup` or POST advance on a seeded install. On warm installs with SiteBackup `durable_done`, `/setup/api/*` may 302 to home (accepted).

Atomic gaps batch (`UC-AUTH-25/26`, `UC-ACC-24/25`, `UC-DASH-15`, `UC-OPS-12/13`, `UC-SETUP-06`, `UC-ADM-38..42`, `UC-PROJ-27`): `e2e/flows/use-cases-atomic-gaps.spec.ts`.

Local stability: Playwright `retries: 1` (non-CI) and `gotoStable()` (retries `ERR_NETWORK_CHANGED` / `chrome-error`, `domcontentloaded`).

Digest flush (`UC-NOTIF-17`): `make test-e2e` runs `app:notifications:flush-digests --force` and writes `var/e2e/flush-digests.last` for `notifications/use-cases-digest-flush.spec.ts`.

Mailpit-backed auth completion (`UC-AUTH-18` / `UC-AUTH-20`):

```bash
make mailpit
make test-e2e ARGS='e2e/smoke/use-cases-auth-mailpit.spec.ts'
# Optional: PLAYWRIGHT_REQUIRE_MAILPIT=1 to fail instead of skip when Mailpit is down
```

Push HTTP (`UC-ACC-23`) needs `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` in `.env` (see `.env.dist`); recreate `php` after setting keys.

## File map (legacy names)

Older flat names still appear in git history; current paths are under the folders above (e.g. `use-cases-hooks-happy.spec.ts` → `hooks/use-cases-hooks-happy.spec.ts`).
