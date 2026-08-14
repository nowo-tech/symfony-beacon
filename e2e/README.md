# Playwright browser E2E against the local Compose stack

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
| `admin/` | Hub, users, kits, settings, analytics admin |
| `project/` | Dashboard, project settings, share, members |
| `issues/` | Issue list / triage deep |
| `ingest/` | Envelope / OTLP / Read API happy paths |
| `hooks/` | Slack / Teams / inbound |
| `notifications/` | Destinations, thresholds, health |
| `flows/` | Cross-cutting mutations and closing suites |
| `z-late/` | Specs that must run last (Read API IP rate limit) |

## Product use-case catalog

**All product use cases** (with Covered / Partial / Gap status) live in:

[`docs/product/E2E-USE-CASES.md`](../docs/product/E2E-USE-CASES.md)

Add a `UC-*` row there whenever you introduce a new scenario, then implement it under the matching domain folder (or extend an existing group).

## File map (legacy names)

Older flat names still appear in git history; current paths are under the folders above (e.g. `use-cases-hooks-happy.spec.ts` → `hooks/use-cases-hooks-happy.spec.ts`).
