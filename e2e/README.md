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
make test-e2e ARGS='e2e/public.spec.ts'
make test-e2e ARGS='e2e/mutations.spec.ts'
make test-e2e ARGS='e2e/use-cases-*.spec.ts'
PLAYWRIGHT_BASE_URL=https://localhost:9447 make test-e2e
```

When `CI=1` or `PLAYWRIGHT_REQUIRE_SAMPLE=1`, tests that need sample/demo data **fail** instead of skipping (see `requireSampleOrSkip` in `helpers.ts`).

## Product use-case catalog

**All product use cases** (with Covered / Partial / Gap status and suggested next batches) live in:

[`docs/product/E2E-USE-CASES.md`](../docs/product/E2E-USE-CASES.md)

Add a `UC-*` row there whenever you introduce a new scenario, then implement it under `e2e/use-cases-*.spec.ts` (or extend an existing group).

Spec groups:

- `public` / `auth.setup` / `dashboard-project` / `account` / `admin` / `ingest` / `misc`
- `navigation-ui` — tabs, theme, guest redirects
- `mutations` — create project/group, triage, share links, governance, assignee, read API
- `issues-deep` — issue detail, AI export, performance/analytics/releases, env compare
- `dashboard-panels` — summary cards, assignments/mentions/activity/alerts filters
- `account-deep` — profile, privacy, security, display prefs, locale switch, tour replay
- `member-alerts` — account/project Live prefs (master cascade, event scope, project dialog)
- `admin-rbac` — permissions & roles index/show/tabs
- `kit-admin-deep` — HTTP log, RoutingKit, menus, breadcrumbs, cookie consent admin
- `settings-deep` — appearance tabs, ops-defaults tabs, instance config, admin hub cards, legacy redirects
- `cookie-consent` — guest modal + config endpoints
- `share-access` — invalid token + create/consume share link
- `project-settings-deep` — governance/members/DSN/notifications smoke
- `project-config` — project/admin config export/import
- **`use-cases-*`** — catalog-driven expansions (auth/errors, OTLP ingest, issue workflow, project tabs, admin ops, hooks, dashboard mentions, **share guest**, **notifications/keys**, **analytics/admin lifecycle**)

Auth storage is written to `e2e/.auth/` (gitignored).
HTML report: `pnpm run test:e2e:report`
