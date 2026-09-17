# Quickstart: Identity book + error manual (114)

## Prerequisites

- Repo checkout with Unreleased / Phase 6.66 work.
- For captures or warm smoke: `make ready-e2e` (or equivalent) so `APP_ENV=dev` serves `/_error/{code}`.

## Read the identity book

1. Open [`docs/identity/README.md`](../../docs/identity/README.md).
2. Confirm links from [`docs/README.md`](../../docs/README.md), [`docs/manual/README.md`](../../docs/manual/README.md), [`docs/wiki/Home.md`](../../docs/wiki/Home.md), and the root README.

## Read / regenerate error chapter shots

1. Read [`docs/manual/08-errors.md`](../../docs/manual/08-errors.md).
2. With warm stack: `make docs-manual-screenshots` (or the project’s documented capture target).
3. Confirm PNGs under `docs/manual/images/error-*.png` and `error-maintenance.png` are 1440×900.
4. Hygiene checks:
   - `dashboard.png` / `prefs-private-theme*.png` — demo project only; English legal footer (`Legal notice`, not `Aviso legal`).
   - `admin-groups.png` — `Beacon operators` row; no `E2E` names.
   - `error-maintenance.png` — no multi-year countdown.

## Cold setup shots (English)

```bash
make wipe-e2e-cold && make up-e2e-cold
# If :9461/:9086 are busy, override: E2E_COLD_HTTPS_PORT=9463 E2E_COLD_HTTP_PORT=9089
make docs-manual-screenshots-setup
# setup-gate.png must read FIRST RUN / Continue (not PRIMER ARRANQUE)
```

## Verify transparent runtime art

```bash
# Prefer the project’s PHPUnit filter for error / art assertions, e.g.:
php bin/phpunit --filter HttpErrorPages
# or the suite that asserts PNG IHDR / color type 6 on public/brand + public/illustrations
```

## Warm markup smoke (no Mailpit)

```bash
make test-e2e-smoke
# includes e2e/smoke/html-twig-standardization.spec.ts when the smoke suite is selected
```

## Opt-in Mailpit auth lane

```bash
make test-e2e-mailpit
# Compose profile mail + PLAYWRIGHT_MAILPIT=1 + serial workers
```

## CSP style nonce (unit)

```bash
php bin/phpunit --filter ContentSecurityPolicySubscriber
```

## Spec amendments

- Branded errors: [`../063-branded-http-errors/spec.md`](../063-branded-http-errors/spec.md) (FR-002 item 9)
- Product UI manual: [`../111-product-ui-manual/spec.md`](../111-product-ui-manual/spec.md) (chapter 08 + identity + FR-015…017 hygiene)
- Footer locale unit: `php bin/phpunit tests/Unit/Identity/EventSubscriber/UserPreferredLocaleSubscriberTest.php`
