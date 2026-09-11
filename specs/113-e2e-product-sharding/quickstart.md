# Quickstart: Product E2E sharding (`113`)

## Local shard (same as CI)

```bash
make up-e2e && make ready-e2e
make test-e2e ARGS='--shard=2/4'   # or 1/4 … 4/4
```

## Worker-safe (must opt in)

```bash
make test-e2e-worker-safe          # sets PLAYWRIGHT_WORKER_SUITE=1
# Product chromium ignores e2e/worker/ when the env is unset.
```

## Mailer in CI / Compose mail profile

```bash
# CI sets:
# PLAYWRIGHT_MAILER_DSN=smtp://mailer:1025
```

## View-as-member sticky session

If an admin test enables view-as-member, prefer `/dashboard` (banner with disable form) and call `exitViewAsMember` (CSRF POST). Do not assert the success toast as the banner.

## BreadcrumbKit ephemeral writes

Prefer helpers `createEphemeralBreadcrumbCollection` / `openNewBreadcrumbItemForm` (full-page + JSON clear). Modal UX remains for operators in the kit UI.
