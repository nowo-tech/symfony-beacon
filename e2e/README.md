# Playwright browser E2E against the local Compose stack
#
# Prerequisites:
#   make up && make seed   # demo admin: admin@symfony-beacon.local / admin123
# Recommended for issue/performance/analytics assertions:
#   make seed-sample
#
# Run (recommended — Docker Playwright image with Chromium deps):
#   make test-e2e
#
# Host run (requires system libs via `pnpm exec playwright install-deps`):
#   PLAYWRIGHT_ON_HOST=1 make test-e2e
#
# Filters / overrides:
#   make test-e2e ARGS='e2e/public.spec.ts'
#   make test-e2e ARGS='e2e/mutations.spec.ts'
#   PLAYWRIGHT_BASE_URL=https://localhost:9444 make test-e2e
#
# Spec groups:
#   public / auth.setup / dashboard-project / account / admin / ingest / misc
#   navigation-ui — tabs, theme, guest redirects
#   mutations — create project/group, triage, share links, governance
#   issues-deep — issue detail, AI export, performance/analytics/releases
#
# Auth storage is written to e2e/.auth/ (gitignored).
# HTML report: pnpm run test:e2e:report
