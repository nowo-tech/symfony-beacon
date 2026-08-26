# Upgrading Guide

This guide helps you upgrade between versions of **symfony-beacon**.

## Table of contents

- [Unreleased (main after 1.23.3)](#unreleased-main-after-1233)
- [Upgrading from 1.23.2 to 1.23.3](#upgrading-from-1232-to-1233)
- [Upgrading from 1.23.1 to 1.23.2](#upgrading-from-1231-to-1232)
- [Upgrading from 1.23.0 to 1.23.1](#upgrading-from-1230-to-1231)
- [Upgrading from 1.22.0 to 1.23.0](#upgrading-from-1220-to-1230)
- [Upgrading from 1.21.0 to 1.22.0](#upgrading-from-1210-to-1220)
- [Upgrading from 1.20.3 to 1.21.0](#upgrading-from-1203-to-1210)
- [Upgrading from 1.20.2 to 1.20.3](#upgrading-from-1202-to-1203)
- [Upgrading from 1.20.1 to 1.20.2](#upgrading-from-1201-to-1202)
- [Upgrading from 1.20.0 to 1.20.1](#upgrading-from-1200-to-1201)
- [Upgrading from 1.19.0 to 1.20.0](#upgrading-from-1190-to-1200)
- [Upgrading from 1.18.7 to 1.19.0](#upgrading-from-1187-to-1190)
- [Upgrading from 1.18.6 to 1.18.7](#upgrading-from-1186-to-1187)
- [Upgrading from 1.18.5 to 1.18.6](#upgrading-from-1185-to-1186)
- [Upgrading from 1.18.4 to 1.18.5](#upgrading-from-1184-to-1185)
- [Upgrading from 1.18.3 to 1.18.4](#upgrading-from-1183-to-1184)
- [Upgrading from 1.18.2 to 1.18.3](#upgrading-from-1182-to-1183)
- [Upgrading from 1.18.1 to 1.18.2](#upgrading-from-1181-to-1182)
- [Upgrading from 1.18.0 to 1.18.1](#upgrading-from-1180-to-1181)
- [Upgrading from 1.17.0 to 1.18.0](#upgrading-from-1170-to-1180)
- [Upgrading from 1.16.0 to 1.17.0](#upgrading-from-1160-to-1170)
- [Upgrading from 1.15.1 to 1.16.0](#upgrading-from-1151-to-1160)
- [Upgrading from 1.15.0 to 1.15.1](#upgrading-from-1150-to-1151)
- [Upgrading from 1.14.0 to 1.15.0](#upgrading-from-1140-to-1150)
- [Upgrading from 1.13.0 to 1.14.0](#upgrading-from-1130-to-1140)
- [Upgrading from 1.12.0 to 1.13.0](#upgrading-from-1120-to-1130)
- [Upgrading from 1.11.0 to 1.12.0](#upgrading-from-1110-to-1120)
- [Upgrading from 1.10.0 to 1.11.0](#upgrading-from-1100-to-1110)
- [Upgrading from 1.9.0 to 1.10.0](#upgrading-from-190-to-1100)
- [Upgrading from 1.8.2 to 1.9.0](#upgrading-from-182-to-190)
- [Upgrading from 1.8.1 to 1.8.2](#upgrading-from-181-to-182)
- [Upgrading from 1.8.0 to 1.8.1](#upgrading-from-180-to-181)
- [Upgrading from 1.7.0 to 1.8.0](#upgrading-from-170-to-180)
- [Upgrading from 1.6.4 to 1.7.0](#upgrading-from-164-to-170)
- [Upgrading from 1.6.3 to 1.6.4](#upgrading-from-163-to-164)
- [Upgrading from 1.6.2 to 1.6.3](#upgrading-from-162-to-163)
- [Upgrading from 1.6.1 to 1.6.2](#upgrading-from-161-to-162)
- [Upgrading from 1.6.0 to 1.6.1](#upgrading-from-160-to-161)
- [Upgrading from 1.5.1 to 1.6.0](#upgrading-from-151-to-160)
- [Upgrading from 1.5.0 to 1.5.1](#upgrading-from-150-to-151)
- [Upgrading from 1.4.0 to 1.5.0](#upgrading-from-140-to-150)
- [Upgrading from 1.3.1 to 1.4.0](#upgrading-from-131-to-140)
- [Upgrading from 1.3.0 to 1.3.1](#upgrading-from-130-to-131)
- [Upgrading from 1.2.0 to 1.3.0](#upgrading-from-120-to-130)
- [Upgrading from 1.1.0 to 1.2.0](#upgrading-from-110-to-120)
- [Upgrading from 1.0.1 to 1.1.0](#upgrading-from-101-to-110)
- [Upgrading from 1.0.0 to 1.0.1](#upgrading-from-100-to-101)
- [Upgrading from 0.17.0 to 1.0.0](#upgrading-from-0170-to-100)
- [Upgrading from 0.16.0 to 0.17.0](#upgrading-from-0160-to-0170)
- [Upgrading from 0.15.0 to 0.16.0](#upgrading-from-0150-to-0160)
- [Upgrading from 0.14.0 to 0.15.0](#upgrading-from-0140-to-0150)
- [Upgrading from 0.13.0 to 0.14.0](#upgrading-from-0130-to-0140)
- [Upgrading from 0.12.8 to 0.13.0](#upgrading-from-0128-to-0130)
- [Upgrading from 0.12.7 to 0.12.8](#upgrading-from-0127-to-0128)
- [Upgrading from 0.12.6 to 0.12.7](#upgrading-from-0126-to-0127)
- [Upgrading from 0.12.5 to 0.12.6](#upgrading-from-0125-to-0126)
- [Upgrading from 0.12.4 to 0.12.5](#upgrading-from-0124-to-0125)
- [Upgrading from 0.12.3 to 0.12.4](#upgrading-from-0123-to-0124)
- [Upgrading from 0.12.2 to 0.12.3](#upgrading-from-0122-to-0123)
- [Upgrading from 0.12.1 to 0.12.2](#upgrading-from-0121-to-0122)
- [Upgrading from 0.12.0 to 0.12.1](#upgrading-from-0120-to-0121)
- [Upgrading from 0.11.1 to 0.12.0](#upgrading-from-0111-to-0120)
- [Upgrading from 0.11.0 to 0.11.1](#upgrading-from-0110-to-0111)
- [Upgrading from 0.10.2 to 0.11.0](#upgrading-from-0102-to-0110)
- [Upgrading from 0.10.1 to 0.10.2](#upgrading-from-0101-to-0102)
- [Upgrading from 0.10.0 to 0.10.1](#upgrading-from-0100-to-0101)
- [Upgrading from 0.9.4 to 0.10.0](#upgrading-from-094-to-0100)
- [Upgrading from 0.9.3 to 0.9.4](#upgrading-from-093-to-094)
- [Upgrading from 0.9.2 to 0.9.3](#upgrading-from-092-to-093)
- [Upgrading from 0.9.1 to 0.9.2](#upgrading-from-091-to-092)
- [Upgrading from 0.9.0 to 0.9.1](#upgrading-from-090-to-091)
- [Upgrading from 0.8.1 to 0.9.0](#upgrading-from-081-to-090)
- [Upgrading from 0.8.0 to 0.8.1](#upgrading-from-080-to-081)
- [Upgrading from 0.7.2 to 0.8.0](#upgrading-from-072-to-080)
- [Upgrading from 0.7.1 to 0.7.2](#upgrading-from-071-to-072)
- [Upgrading from 0.7.0 to 0.7.1](#upgrading-from-070-to-071)
- [Upgrading from 0.6.0 to 0.7.0](#upgrading-from-060-to-070)
- [Upgrading from 0.5.0 to 0.6.0](#upgrading-from-050-to-060)
- [Upgrading from 0.4.0 to 0.5.0](#upgrading-from-040-to-050)
- [Upgrading from 0.3.0 to 0.4.0](#upgrading-from-030-to-040)
- [Upgrading from 0.2.0 to 0.3.0](#upgrading-from-020-to-030)
- [Upgrading from 0.1.0 to 0.2.0](#upgrading-from-010-to-020)
- [First install (no previous version)](#first-install-no-previous-version)

---

## Unreleased (main after 1.23.3)

AuthKit **1.20.0** + SlideToConfirm **1.1.0** + Device Intelligence **1.1.0**. **Has a Doctrine migration.**

1. Pull `main` / the next tag.

2. `composer install` — pins `nowo-tech/auth-kit-bundle` **1.20.0**, `nowo-tech/slide-to-confirm-bundle` **1.1.0**, `nowo-tech/device-intelligence-bundle` **1.1.0**, `nowo-tech/otp-input-bundle` (password-reset code boxes).

3. `php bin/console doctrine:migrations:migrate -n` — creates `device_intelligence_*` tables.

4. `php bin/console assets:install` (or `make ready`) so `/bundles/nowoslidetoconfirm`, `/bundles/nowodeviceintelligence`, and `/bundles/nowootpinput` exist.

5. Re-seed cookie inventory so `di_obs` appears in `/legal/cookies`: `make seed-platform` (or Setup → platform).

6. **Behaviour**
   - AuthKit registration (when it is actually shown) uses a **gate** slide for terms consent. After the first user exists, `/register` still redirects to login.
   - Guest AuthKit pages call `POST /_device/collect` (public; origin CSRF). Privacy mode is **strict** (no canvas/webgl/audio/fonts).
   - After password/magic/social/QR login from a **new** device cluster, AuthKit sets session flag `nowo_auth_kit.new_device` and sends `auth.magic.new_device_email_*` when instance Mailer is configured.
   - QR **Approve** remains a normal button (`qr_login_approve: false`) so UC-AUTH-22 stays a click. `approve_require_trusted` stays false (no auto-trust).
   - **Account → Security → Trusted browsers** (`/account/security/devices`) lists explicit Device Intelligence grants. Login never auto-trusts; operators must tap **Trust this browser**. Collect runs on that page (CSP nonce).
   - Project Settings **clear history** uses a `danger` slide-to-confirm (UX only). Delete project / transfer ownership / anonymize stay type-to-confirm. API key rotate/revoke, config import, and magic-login confirm stay click submits so existing E2E keep working.
   - Password-reset **code** completion (`/reset-password/complete`) uses `OtpType` when `otp_input.enabled` is true. Hidden field value is still a single string; server `hash_equals` / attempt limits are unchanged. Phone SMS OTP stays Later.
   - Device Intelligence collect is excluded from SiteBackup setup redirect, HttpLog, MaintenanceMode 503, PWA runtime cache, and `ROLE_USER` catch-all.

7. **Kit pin refresh (no extra migrations)** — `composer install` also pins FormKit **2.5.1**, SiteBackup **1.13.8**, Symfony **8.1.5** (components that shipped that patch), and the rest of the `nowo-tech/*` patch bumps in [CHANGELOG.md](CHANGELOG.md) Unreleased. Host YAML: drop `nowo_form_kit.type_map.search`; keep `setup.short_circuit_when_done: true`. PWA operators pick up `cache_version` **v6** after the next asset/SW deploy.

8. **SQL error context (`107`)** — `php bin/console doctrine:migrations:migrate -n` widens `issue.culprit` to VARCHAR(255) on MySQL. Issue/event pages show a Query panel when SQLSTATE/SQL is in the payload. Pin `nowo-tech/beacon-bundle` **1.8.0** so dogfood clients send `contexts.db`.

Device ID is not a login factor. Keep LoginThrottle / CSRF / remember-me.

## Upgrading from 1.23.2 to 1.23.3

Kit pin refresh + prod hardening + isolated E2E Messenger Redis DB fix. **No migrations.**

1. Pull / checkout `v1.23.3`.

2. `composer install` — pins include auth-kit **1.17.4**, beacon-bundle **1.7.7**, http-log **1.1.4**, maintenance-mode **1.5.6**, site-backup **1.13.6**, dashboard-menu **2.1.8**, and related `nowo-tech/*` patch bumps (see [CHANGELOG.md](CHANGELOG.md) `[1.23.3]`).

3. **Production operators**
   - SiteBackup **`setup.enabled: false`** under `when@prod` — the `/setup` wizard is off. Cold-start before `APP_ENV=prod`, or use CLI / `make ready`.
   - HttpLog in prod no longer captures JSON response bodies (`response_body_by_type.json: false`); retention defaults to **14** days; export sync capped.
   - MaintenanceMode **preview** is disabled in prod.
   - Symfony access control: any path under **`/admin`** requires **`ROLE_ADMIN`** (catch-all in addition to the existing `/admin/(api/doc|_routing|http-log|maintenance)` rule).

4. **Local E2E** (optional): regenerate `.env.e2e.local` so `MESSENGER_TRANSPORT_DSN` uses `?dbindex=` (Redis path is the stream name). Then `make up-e2e` / `make ready-e2e`. See [e2e/README.md](../e2e/README.md).

See [CHANGELOG.md](CHANGELOG.md) `[1.23.3]`.

## Upgrading from 1.23.1 to 1.23.2

CI Quality: Rector 2.6.2 dropped `SymfonySetList::SYMFONY_81`. **No migrations.**

1. Pull / checkout `v1.23.2`.

2. No operator runtime steps. Contributors: `rector.php` keeps Symfony code-quality / annotations-to-attributes sets only — do not re-enable `withComposerBased(symfony: true)` until Autowire / `eraseCredentials` / Twig helper churn is acceptable.

See [CHANGELOG.md](CHANGELOG.md) `[1.23.2]`.

## Upgrading from 1.23.0 to 1.23.1

Pin refresh + Composer DX (`make update-deps` now bumps exact pins). **No migrations.**

1. Pull / checkout `v1.23.1`.

2. `composer install` — pins include `nowo-tech/hot-reload-bundle` **1.4.0** (`require-dev`), FormKit **2.4.1**, password-strength **2.2.0**, password-toggle **2.1.1**, `symfony/mercure-bundle` **0.5.0**, Symfony **8.1.4**. Mercure hubs stay protocol **0.x**; no JWT/cookie migration.

3. Optional (local Docker): `pnpm install` if you rebuild assets. Dev: `bin/console nowo:hot-reload:check` after `make up`.

4. Contributors: `make update-deps` runs `generate-composer-require.sh --run` then `composer update` + `pnpm update`. Preview: `make composer-outdated`.

See [CHANGELOG.md](CHANGELOG.md) `[1.23.1]`.

## Upgrading from 1.22.0 to 1.23.0

Kit + first-run polish (`056` FR-014, hot-reload-bundle **1.3.2**, PhoneInput theme bridge, PWA `install_links` vendor-only). **No migrations.**

1. Pull / checkout `v1.23.0`.

2. `composer install` — pins `nowo-tech/hot-reload-bundle` **1.3.2** (`require-dev`). Host YAML still only sets `csp_nonce_request_attribute: '_beacon_csp_nonce'`.

3. Rebuild frontend assets (PhoneInput host theme bridge `_phone_input.scss`):

```bash
make vite-build
```

4. Cold-start: finish **`/setup`** (or `make ready`) for the first admin. `/login` and `/register` stay gated until setup is complete (`056` FR-014). Existing instances with `setup_completed_at` are unchanged.

See [CHANGELOG.md](CHANGELOG.md) `[1.23.0]`.

## Upgrading from 1.21.0 to 1.22.0

Dogfood multi-event probe suite (`058` amendment). **No migrations.**

1. Pull / checkout `v1.22.0`.

2. Optional — validate Issues UI panels against the loopback dogfood project:

```bash
make dogfood          # if BEACON_DSN needs re-wiring
make restart          # reload .env.local into php/messenger
make beacon-suite     # or: make beacon-test ARGS='--suite --run-token=demo'
```

3. In the Symfony Beacon project Issues list, filter by tag `probe_run=<token>` (printed by the command). Console/HTTP suite issues expose where-it-happened client tags (`console.command`, `url` / `http.route`, …).

4. Single ACK probe is unchanged: `make beacon-test`.

See [DSN.md](DSN.md) and [CHANGELOG.md](CHANGELOG.md) `[1.22.0]`.

## Upgrading from 1.20.3 to 1.21.0

Isolated Playwright E2E stack (`104` / Phase 6.55) + Morphicons chrome. **No migrations.**

1. Pull / checkout `v1.21.0`.

2. Rebuild frontend assets (Morphicons / Vite):

```bash
make vite-build
```

3. Local E2E without mutating dogfood MySQL (optional):

```bash
make up-e2e
make ready-e2e
make test-e2e-isolated
# optional: E2E_BEACON_TARGET=dogfood make ready-e2e   # SUT errors → :9447
# make down-e2e
```

4. CI / default path unchanged: `make test-e2e` still targets the Compose dogfood schema.

See [e2e/README.md](../e2e/README.md) and [CHANGELOG.md](CHANGELOG.md) `[1.21.0]`.

## Upgrading from 1.20.2 to 1.20.3

CI Quality/Coverage: occurrence-sort PHPUnit harness fix (100% statement coverage). **No migrations.**

1. Pull / checkout `v1.20.3`.

2. No operator runtime steps.

## Upgrading from 1.20.1 to 1.20.2

CI Quality: PHPStan baseline + phpstan-phpunit; restore namespaced test-hook `\native()` fallbacks; PHP-CS-Fixer `native_function_invocation.strict=false`. **No migrations.**

1. Pull / checkout `v1.20.2`.

2. No operator runtime steps.

## Upgrading from 1.20.0 to 1.20.1

CI unblock: PHP-CS-Fixer, 100% PHPUnit coverage for `DropSelfIngestBeforeSend` contexts path, Playwright DSN reveal / assignee locator fixes. **No migrations.**

1. Pull / checkout `v1.20.1`.

2. No asset rebuild required for this patch alone (unless you were already mid-upgrade from `1.19.0` — then follow the `1.20.0` steps).

## Upgrading from 1.19.0 to 1.20.0

Cookie Consent skin is bundled into Vite `app` CSS (ad-blocker resilient); dogfood prefers the earliest `ROLE_ADMIN`; Playwright security denials catalog (`UC-SEC-01`…`12`). **No migrations.**

1. Pull / checkout `v1.20.0`.

2. Rebuild frontend assets and refresh the PHP container (PWA `cache_version` is **v5**):

```bash
make assets   # or your usual Vite build
make restart  # recreate PHP so env / SW precache rules reload
```

3. Local dogfood (optional): `make dogfood` grants **every** `ROLE_ADMIN` membership; `.demo-client.env` login hint is the **first registered** admin (not leftover `admin@…`). Password is not written to that file — use your real admin password.

4. Contributors — security E2E slice:

```bash
make test-e2e ARGS='e2e/security'
```

See [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md), [INSTALL.md](INSTALL.md), and [E2E-USE-CASES.md](product/E2E-USE-CASES.md) §16.

## Upgrading from 1.18.7 to 1.19.0

REQ-QA-002 / `033`: hard coverage gate on includable PHP (`COVERAGE_MIN=100`) plus Vitest 100% on the documented TypeScript whitelist. **No migrations.**

1. Pull / checkout `v1.19.0` (operators: no runtime steps).

2. Contributors — keep the Coverage CI job green:

```bash
make test-coverage          # defaults to COVERAGE_MIN=100
make test-unit-js-coverage  # Vitest V8 thresholds
```

See [COVERAGE.md](COVERAGE.md) for PHPUnit exclusions (controllers, demo/seed CLI) and the Vitest includable set. Local diagnosis only: `COVERAGE_MIN=0 make test-coverage`.

## Upgrading from 1.18.6 to 1.18.7

Makefile quieter recursive output only (`MAKEFLAGS += --no-print-directory`). **No migrations.** Pull `v1.18.7`.

## Upgrading from 1.18.5 to 1.18.6

CI Rector dry-run only. **No migrations.** Pull `v1.18.6`.

## Upgrading from 1.18.4 to 1.18.5

CI style-only fix. **No migrations.** Pull `v1.18.5` (or `composer install` on that tag).

## Upgrading from 1.18.3 to 1.18.4

Issue detail path tabs, temporary API DSN reveal UX, and dogfood noise reduction for expected 403s. **No migrations.**

1. Pull / checkout `v1.18.4` and recreate PHP so config reloads:

```bash
make restart
# or: php bin/console cache:clear
```

2. Optional — confirm dogfood no longer records ACL/admin 403s as issues (see [DSN.md](DSN.md) `ignore_exceptions`).

3. Issue URLs: Main remains `/projects/{uuid}/issues/{id}` (or `…/main`); Similar / History are `…/similar` and `…/history`. API key create/rotate still shows the DSN once (temporary reveal); ordinary Settings GET stays redacted.

## Upgrading from 1.18.2 to 1.18.3

Dogfood probe diagnostics, Web Push preference default **on** for new users, and CI/host readability of `.demo-client.env`.

1. Pull / checkout `v1.18.3` and run migrations:

```bash
make migrate
# Version20260816120000 — ALTER `user`.push_notifications_enabled DEFAULT 1
```

2. Optional — verify dogfood DSN and understand ACK ≠ Web Push:

```bash
make beacon-test ARGS='--check-only'
make beacon-test ARGS='--message=unique-probe --wait=15'
```

3. If host Playwright / local tools cannot read `.demo-client.env` after seed:

```bash
make reclaim-demo-client-env
```

Existing users keep their saved `pushNotificationsEnabled` value. Preference **on** still requires a browser `push_subscription` (open Issues so `issue-realtime` can subscribe). See [DSN.md](DSN.md) and [NOTIFICATIONS.md](product/NOTIFICATIONS.md).

## Upgrading from 1.18.1 to 1.18.2

Additive dogfood / UI tooling. **No migrations.**

1. Update dependencies so `nowo-tech/beacon-bundle` is **1.7.3**:

```bash
composer update nowo-tech/beacon-bundle
# or: composer install on a v1.18.2 tree
```

2. Clear cache if needed: `php bin/console cache:clear` (or `make restart`).

3. Optional — verify the loopback / operator `BEACON_DSN`:

```bash
make beacon-test ARGS='--check-only'
make beacon-test
```

Issue detail now highlights richer BeaconBundle extras (console, Messenger, Scheduler, Monolog, fatals, HTTP, trace). No config changes required for that UI.

## Upgrading from 1.18.0 to 1.18.1

**Messenger Redis DSN** — remove `/messages` from `MESSENGER_TRANSPORT_DSN` so yaml stream names apply (avoids multi-group conflict on shared Redis). See `[1.18.1]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.18.1
# In .env.local:
#   MESSENGER_TRANSPORT_DSN=redis://${REDIS_HOST}:${REDIS_PORT}
make restart   # recreates php + messenger workers with the new env
```

### Operator checklist

1. Drop `/messages` from `MESSENGER_TRANSPORT_DSN` (match `.env.dist`).
2. Recreate Messenger consumers so they load the new DSN.
3. No migrations.

## Upgrading from 1.17.0 to 1.18.0

**Audit follow-up: ingest/query performance + ops hardening + thin notification/dashboard refactor** — indexes on `event(project_id, received_at)` and `issue(project_id, last_environment)`; environment list filter uses `last_environment`; span/compare/release caps; `.demo-client.env` mode 600 + deploy docs. See `[1.18.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.18.0   # or pull main at the release commit
composer install
make ensure-up          # or make up
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear
```

### Operator checklist

1. **Migrations**: apply `Version20260815230000` and `Version20260815231000` (or `make migrate`).
2. **Environment filters**: issue list “environment” now matches **last seen** environment (aligned with env compare).
3. **Demo client env**: after `make seed` / `make dogfood`, `.demo-client.env` should be mode `600`; chmod older copies if needed.
4. **Deploy**: complete `/setup` + first `/register` before publishing HTTP(S); keep `SYMFONY_TRUSTED_PROXIES` limited to real LB CIDRs (see [PRODUCTION.md](PRODUCTION.md)).

### Notes

- No kit pin bumps in this cut.
- Performance Envelope spans above 500 are truncated at persist time.

## Upgrading from 1.16.0 to 1.17.0

**Kit CSP upstream + shared helpers (`101` / 6.52) + shared Mailpit preference** — pins: PhoneInput **1.3.0**, CookieConsent **1.9.0**, FormKit **2.4.0**, UiKit **1.8.0**; host phone/cookie/CSRF forks removed; `make mailpit` prefers shared `mailpit` (`smtp://mailpit:1025`). See `[1.17.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.17.0   # or pull main at the release commit
composer install
make ensure-up          # or make up
php bin/console assets:install
php bin/console cache:clear
make vite-build
```

### Operator checklist

1. **Assets**: `assets:install` publishes kit `nowo-cookie-consent.css` / phone picker JS; `make vite-build` refreshes Stimulus peer re-exports.
2. **Layouts**: ensure public shells link `nowo-cookie-consent.css` with `data-nowo-cookie-consent-css` (Beacon `base` + `guest_shell` already do).
3. **Code**: CSRF-only forms use FormKit `CsrfOnlyFormFactory::createNamed()` (nested) or `create()` (flat). Host `App\Shared\Form\CsrfOnly*` types were removed.
4. **Mailpit (optional)**: if you use the shared `developer.local.server/server` catcher, set Admin → Mailer to `smtp://mailpit:1025` (local profile `mail` still uses `smtp://mailer:1025`).
5. **No migrations** in this cut.

### Notes

- Profile phone CSS comes from kit `phone_input.css` + flag icons (host `_phone_input.scss` removed).
- Optional UiKit IIFEs (`nowo-ui-clipboard.js`, `nowo-ui-tabs.js`) are available; Beacon keeps Stimulus peers via vendor re-exports.
- AuthKit locale overlays and CI MySQL network reconnects are included (fixes after v1.16.0).

## Upgrading from 1.15.1 to 1.16.0

**Phone input kit + `.env.local` + CI Compose infra (`100` / 6.51)** — `nowo-tech/phone-input-bundle` **1.2.1**; CSP-safe prefix picker; AuthKit QR stays disabled in prod (`when@dev` / `when@test` enable it); operator working env is `.env.local`; `make restart` recreates workers so `BEACON_DSN` reloads. See `[1.16.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.16.0   # or pull main at the release commit
# Migrate working env (REQ-ENV-003):
#   mv .env .env.local   # or: make ensure-env
composer install
make ensure-up          # or make up
php bin/console cache:clear
make vite-build         # required for phone-prefix Stimulus + SCSS
make restart            # reload BEACON_DSN / Compose env from .env.local
```

### Operator checklist

1. **Env file**: move secrets to `.env.local` (`cp .env.dist .env.local` on fresh hosts; `mv .env .env.local` or `make ensure-env` when upgrading). Prefer deleting leftover `.env` so Compose does not prefer a stale copy.
2. **Assets**: rebuild Vite (`make vite-build`) so phone-prefix picker + `_phone_input.scss` ship.
3. **No migrations** in this cut. Existing `User.phone` values remain; Profile now edits via country + national number.
4. **QR login**: production remains `qr_login.mode: disabled` until SMS OTP. Local/E2E use `when@dev` / `when@test`.
5. **Dogfood DSN**: prefer `make restart` (force-recreate) after syncing `BEACON_DSN` — soft Compose restart keeps stale env.
6. **SMS**: leave `SMS_PROVIDER=null` unless you intentionally configure `sms_bridge` (future OTP; not used by AuthKit yet). New keys are in `.env.dist`.

### Notes

- Custom clients posting Account profile phone as a single text field must switch to `user_profile[phone][country_iso]` + `user_profile[phone][national_number]`.
- Phone is existing account PII (auth surface); no new optional cookies — Cookie Consent unchanged.
- CI E2E now starts `make up-infra` before the app stack (external `SHARED_DOCKER_NETWORK`).

## Upgrading from 1.15.0 to 1.15.1

**Cookie consent guest skin + test/E2E hardening** — host SCSS owns the public modal (CSP-safe), platform seed pins **bottom-left** equal-weight actions, PHPUnit SQLite cold-start fix, Appearance/theme/locale Playwright mutations. See `[1.15.1]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.15.1   # or pull main at the release commit
composer install
make ensure-up          # or make up
php bin/console cache:clear
make vite-build         # required for guest consent SCSS
make seed-platform      # upsert cookie profile layout (bottom-left)
```

### Operator checklist

1. **Assets**: rebuild Vite (`make vite-build`) so `_cookie_consent.scss` is in `public/build/`.
2. **Cookie profile**: re-run `make seed-platform` (or Setup wizard step 1) so existing `dashboard_cookie_config` picks up bottom-left + equal-weight buttons. See [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md).
3. **No migrations** in this patch.

### Notes

- PHPUnit / Playwright changes are developer-facing only; production operators skip test suite steps.

## Upgrading from 1.14.0 to 1.15.0

**Shared infra + setup wizard 100% + Redis scale (`056` / 6.49, `099` / 6.50)** — in-repo `compose.infra.yaml` (MySQL/Redis), SiteBackup **1.13** / CookieConsent **1.8** with `cache_doctrine` progress, Redis sessions/Messenger, promoted event tags/URL. See `[1.15.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.15.0   # or pull main at the release commit
# Align .env with .env.dist (MYSQL_HOST=mysql-9.7-primary, REDIS_HOST=redis-8.10.0, REDIS_URL)
composer install
make up                 # up-infra + app
make migrate            # Version20260815120000 — event.request_url + event_tag
php bin/console cache:clear
# Optional historical backfill:
# php bin/console app:events:backfill-promotions
make vite-build         # if you serve built assets
```

### Operator checklist

1. **Compose**: stop old embedded `database` / `redis` if present (`docker compose down`), then `make up`. Data path is `./.data/infra/` (old `./.data/mysql` unused). See [SHARED-SERVER.md](ops/SHARED-SERVER.md).
2. **Redis**: required for sessions, rate limits, Messenger, and setup `progress_storage: cache_doctrine`.
3. **Composer**: `nowo-tech/site-backup-bundle` **1.13.0**, `nowo-tech/cookie-consent-bundle` **1.8.0**.
4. **Setup wiring**: `setup.durable_done.enabled: true`; alias `DurableSetupDoneStoreInterface` → `App\Setup\InstanceSettingsDurableSetupDoneStore`; `progress_storage: cache_doctrine` (no `var/` progress JSON). Cold start: empty schema still enters `/setup`; CookieConsent **1.8** skips Doctrine mid-migration.
5. **Migrations**: confirm `event.request_url` and `event_tag`. Re-run is safe. Backfill optional for pre-existing events.
6. **Replica**: `MYSQL_TOPOLOGY=replica` + `MYSQL_HOST_RO=mysql-9.7-replica` when using the read replica container.

### Notes

- SiteBackup creates `nowo_site_backup_*` progress tables with runtime DDL — no host migration for those.
- Prod recreate: missing `var/site-backup/setup.done` still redirects home when durable done + catalogs/schema are present.

## Upgrading from 1.13.0 to 1.14.0

**Shared MySQL mode & account profile split (`098` / 6.48)** — optional `make up-shared` (now an alias of `make up`), identity table rename `app_user` → `user`, Account profile split into basic vs password-gated sensitive forms. See `[1.14.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.14.0   # or pull main at the release commit
# Shared hosts: copy MYSQL_* / SHARED_DOCKER_NETWORK from .env.dist — see docs/ops/SHARED-SERVER.md
composer install
make ensure-up          # or make up
make migrate            # Version20260814230000 renames app_user → user
php bin/console cache:clear
make vite-build         # if you serve built assets
```

### Operator checklist

1. **Migrations**: confirm table `` `user` `` exists (was `app_user`). Re-run is safe if already renamed.
2. **Env**: prefer `MYSQL_HOST=mysql-9.7-primary` / `REDIS_HOST=redis-8.10.0` (defaults in current `.env.dist`).
3. **Account profile**: display name / phone save without password; email and Slack user ID require current password on the second panel.
4. **Integrations**: any automation that POSTed `user_preferences[email]` (etc.) must use `user_profile` / `user_profile_sensitive` field names.

### Notes

- `DATABASE_URL_RO` is reserved; Doctrine does not route reads to the replica yet.
- No Composer kit pin bumps in this cut.

## Upgrading from 1.12.0 to 1.13.0

**E2E CI + AuthKit login throttle (`097` / 6.47)** — per-username AuthKit login throttle decorator, CI Playwright hardening, FormKit **2.3.0**, expanded E2E catalog. See `[1.13.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.13.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations expected for 1.13.0
php bin/console cache:clear
make vite-build         # if you serve built assets
```

### Operator checklist

1. **Composer**: confirm `nowo-tech/form-kit-bundle` **2.3.0** (filter defaults no longer force labels/required in PHP).
2. **Login throttle**: failed AuthKit logins are counted per username (with IP), not as one shared IP bucket — unrelated guest accounts are no longer locked after five failures elsewhere on the same IP.
3. **Migrations**: none required for this cut; still run `make migrate` / `app:seed-platform` as usual after pull.
4. **Local mail / E2E**: optional `make mailpit` for magic login / password reset; CI starts Mailpit via Compose profile `mail`.
5. **Dev only**: FrankenPHP hot reload — see [FRANKENPHP-HOT-RELOAD.md](ops/FRANKENPHP-HOT-RELOAD.md) (not for production Compose).

### Notes

- No breaking HTTP API or env var renames in this release.
- Product surface docs: [E2E-USE-CASES.md](product/E2E-USE-CASES.md).

## Upgrading from 1.11.0 to 1.12.0

**Audit follow-up hardening (`096` / 6.46)** — Read API rate limit, hash-at-rest ingest secrets, Project-subject voter abstain, membership write DRY, filter DTOs, QR disabled, Slack user-id hygiene, 24h interaction tokens. See `[1.12.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.12.0   # or pull main at the release commit
# Add to .env if missing (see .env.dist):
# BEACON_READ_API_RATE_LIMIT=120
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # Version20260813180000 indexes + Version20260813190000 secret_hash
php bin/console cache:clear
```

### Operator checklist

1. **Env**: set `BEACON_READ_API_RATE_LIMIT` (default `120`; `0` disables Read API IP throttle).
2. **Migrations**: confirm `secret_hash` column exists; existing keys keep working via legacy encrypted column until the next successful ingest upgrades them (or rotate keys).
3. **QR login**: AuthKit `qr_login` is **disabled** — phone fields remain for future OTP; do not expect QR approval until SMS OTP ships.
4. **Slack Assign mapping**: changing Account → Profile Slack user ID requires the current password and must be unique across users.
5. **Teams/Slack cards**: Assign/Resolve HMAC tokens expire in **24 hours** (was 7 days) — refresh notification cards if operators see expired-action errors.
6. **RBAC**: instance catalog `ROLE_PROJECT_*` never substitutes for project membership on product `#[IsGranted(ProjectPermission::…, 'project')]` (see [ROLES.md](product/ROLES.md)).

### Notes

- One-shot DSN flash after create/rotate is unchanged; Settings still shows public key only.
- Filter / membership FormKit field names are unchanged for UI operators.

## Upgrading from 1.10.0 to 1.11.0

**Audit residual hardening (`095` / 6.45)** — phone QR verification hygiene, maintenance ingest-only exclusions, Mercure hub URL guard, architecture/perf cleanup. See `[1.11.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.11.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
php bin/console cache:clear
# Optional: rebuild assets if you customize project Settings / dashboard chrome
# make vite-build
```

No Doctrine migrations in this release.

### Operator checklist

1. **QR phone login**: numbers saved before this release that were auto-marked verified stay verified until changed. Saving or changing a phone clears verification — QR approval will not work until SMS OTP ships (or an admin sets `phoneVerifiedAt` deliberately). Review Account → Profile status copy.
2. **Maintenance mode**: Envelope (`/api/{id}/envelope/`) and OTLP (`/api/{id}/otlp/…`) remain reachable during maintenance; **Read API** `/api/projects/…` is **not** excluded (returns 503). Confirm any automation that assumed a blanket `/api/` exclusion.
3. **Mercure**: invalid/private hub URLs are rejected when saving Administration → Mercure (and when publishing). Use a public HTTPS hub URL.
4. **Project Settings URLs**: deep-links should use `/projects/{uuid}/settings/{section}` (`general` / `access` / `alerts` / `data` / `danger`). Bare `/settings` still redirects to the default visible section.
5. **Developers**: prefer `#[IsGranted(ProjectPermission::…, 'project')]` on project-scoped controllers; Identity admin unlink must use `ProjectMembershipAdminPort`; Metrics scrape lives in `App\Ops\Metrics`.

### Notes

- Member-alert preference evaluation is batched on the realtime path (no operator action).
- Admin Users / Groups / Roles / Projects lists are paginated (same query `q` + page params as other admin indexes).

## Upgrading from 1.9.0 to 1.10.0

**Product FormKit form profiles + Twig `form_row` consolidation (`081` / `077` / `090` follow-up / 6.44).** See `[1.10.0]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.10.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
php bin/console cache:clear
# Optional: rebuild assets if you customize Appearance color JS / SCSS
# make vite-build
```

No Doctrine migrations in this release.

### Operator checklist

1. **UI operators**: no action — Settings / Issues / admin forms keep working in the browser.
2. **Custom scripts / scrapers** that POST host HTML forms MUST use Symfony block-prefixed field names (e.g. `project_governance[retention_days]`, `project_share_create[days]`, `project_read_token_create[label]`, `admin_group_member_add[email]`). Unprefixed `retention_days` / `days` / `label` / `email` payloads no longer bind.
3. **Developers**: Form chrome lives in `translations/form.*.yaml` (profiles `beacon` / `filter`). Prefer `form_row` + `form/_fields.html.twig` over hand-rolled `form_widget` + `form_help`. See `.cursor/rules/formkit-profiles.mdc` and `docs/CONTRIBUTING.md` (Symfony forms).
4. **E2E / PHPUnit**: update selectors to prefixed ids/names (Playwright suite already aligned in this cut).

### Notes

- Standing Twig exceptions (intentional): member-alert Live `pref-switch` rows, issue duplicate combobox query widget, form theme internals (`077`).
- Appearance Colors use theme `color_row` (swatch + hex); Themes apply still CSRF-only (theme cards submit `apply_theme`).

## Upgrading from 1.8.2 to 1.9.0

**Maintenance mode (`092` / 6.41), security residual hardening (`093` / 6.42), PHPStan FrankenPHP 1.1.0 (`094` / 6.43), Settings/search maintainability.** See `[1.9.0]` in [CHANGELOG.md](CHANGELOG.md).

**Breaking (ingest):** Envelope query-string auth (`?beacon_key=&beacon_secret=`) is **removed**. Update clients to `X-Beacon-Auth` or envelope `dsn` before upgrading.

```bash
git fetch --tags
git checkout v1.9.0   # or pull main at the release commit
composer install
# Add to .env if missing (see .env.dist):
# BEACON_HOOK_IP_RATE_LIMIT=120
make ensure-up          # or make up on a fresh clone
make migrate            # Version20260813100000 drops ingest_reject_query_auth
make seed-platform      # Maintenance admin menu + breadcrumbs
php bin/console cache:clear
```

### Operator checklist

1. Confirm no SDK still sends Envelope query credentials.
2. Open **Administration → Ops overview** — if a security posture warning appears (private URLs, anonymous Resolve, or metrics require-token off), tighten under **Ops defaults**.
3. Optional: tune `BEACON_HOOK_IP_RATE_LIMIT` (public Slack/Teams/email hooks; `0` disables).
4. Prefer `X-Setup-Token` for setup; rotate `SITE_SETUP_TOKEN` after first setup.
5. **Maintenance**: Administration → Maintenance (`/admin/maintenance`); preview `/_maintenance_preview` without enabling downtime. Public 503 uses `error-503.png`.
6. **Dev / CI**: `nowo-tech/phpstan-frankenphp` **1.1.0** with `rules.neon` production gate — re-run `make phpstan` after pull.

### Notes

- **Migration**: `Version20260813100000` removes `instance_settings.ingest_reject_query_auth` (reject is always on).
- **Messenger**: ingest notifications go through `DispatchIngestNotificationsMessage` on `async` — keep `messenger:consume` / Compose `messenger` + `messenger-notify` running.
- **Maintainability (no operator action)**: project Settings Twig section partials; issue search query traits; shared form helpers — behaviour unchanged.

## Upgrading from 1.8.1 to 1.8.2

**Security / QA polish (no schema).** Wire firewall `user_checker` to UserKit `AccountStatusUserChecker` (disabled accounts blocked on AuthKit magic login via `Security::login`); PHPStan / Rector / CS Fixer CI hardening. See `[1.8.2]` in [CHANGELOG.md](CHANGELOG.md).

```bash
git fetch --tags
git checkout v1.8.2   # or pull main at the release commit
composer install
php bin/console cache:clear
```

No `make migrate` / `make vite-build` required for this patch.

## Upgrading from 1.8.0 to 1.8.1

**CS / LiveComponent DI polish; PHPUnit bootstrap helper name.** No schema changes.

```bash
git fetch --tags
git checkout v1.8.1   # or pull main at the release commit
composer install
php bin/console cache:clear
```

No `make migrate` / `make vite-build` required for this patch. See `[1.8.1]` in [CHANGELOG.md](CHANGELOG.md).

## Upgrading from 1.7.0 to 1.8.0

**Member alert preferences (`091` / 6.40), UserKit 1.1.6, Mercure per-user topics.** Pull, then:

```bash
git fetch --tags
git checkout v1.8.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # member_alerts_enabled + member_*_alert_* tables
make vite-build         # issue-realtime toast labels + SW push titles
php bin/console cache:clear
```

### Notes

- **Migrations**: `Version20260812140000` adds `app_user.member_alerts_enabled` (default on) and relational preference tables. Missing rows mean **on** / scope **all** (opt-out).
- **Mercure**: member live alerts use private topics `/users/{userUuid}/member-alerts` (no longer project `/projects/{uuid}/issues` for this channel). Re-open browser tabs after upgrade so EventSource picks up the new JWT topics. Hub enablement unchanged (**Administration → Mercure**).
- **Prefs UI**: **Account → Display → Notifications** is primary (viewers included). Project Settings `#member-alerts` is an optional shortcut only when the member can open Settings; saving own overrides requires project access, not Settings-admin.
- **UserKit 1.1.6**: disabled accounts are rejected in `checkPreAuth` for form + magic/social/QR. Host no longer overrides `security.firewalls.main.user_checker` — do not reintroduce a custom checker that skips UserKit. Smoke-test a disabled account on all AuthKit login paths.
- **Web Push**: still requires VAPID + device opt-in; delivery now honors the member preference matrix. Rebuild assets so `/sw.js` event titles match toasts.
- Docs: [NOTIFICATIONS.md](product/NOTIFICATIONS.md), [MERCURE.md](ops/MERCURE.md), `[1.8.0]` in [CHANGELOG.md](CHANGELOG.md).

## Upgrading from 1.6.4 to 1.7.0

**CSRF via Symfony Forms (`090`), kit Administration chrome (`081`), AuthKit 1.17, CSP kit admin polish, Composer pins.** Pull, then:

```bash
git fetch --tags
git checkout v1.7.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new Doctrine migrations required for 1.7.0
make vite-build         # kit-admin modal bridge + Stimulus / theme assets
php bin/console cache:clear
```

### Notes

- **CSRF (`090`)**: host mutable POSTs use FormKit Types (`CsrfOnlyType` / named Types / `csrf_action_form()`). Custom Twig that still posts raw `csrf_token()` fields for product actions must migrate to those Types. Exceptions remain: AJAX header CSRF, AuthKit logout `_csrf_token`, kit modal `data-token` deletes.
- **AuthKit 1.17**: magic-login confirm is POST `/login/magic/confirm` with `magic_login_confirm_form` — update any host Twig forks of that flow.
- **Kit admin chrome**: Menu / Breadcrumb / Routing / Http Log host forks use Administration list pattern (`panel`, `kit_admin_header_actions`, `row_actions_display: text`). Rebuild frontend assets so `kit-admin` portals `<dialog>` under CSP.
- **CSP**: host kit `<style>` blocks need `csp_nonce()`; Dashboard Menu must not load CDN Stimulus (`stimulus_script_url` empty). See [PRODUCTION.md](PRODUCTION.md) security headers.
- **Breadcrumbs**: re-run `make seed-platform` (or Admin → Breadcrumbs) so Appearance / RoutingKit create-edit trails and ES “Migas de pan” labels apply.
- **Composer**: RoutingKit **1.4.0** panel uses Symfony forms for export/clear-cache/import/delete (`export_form` / `delete_forms` in host Twig). Password-toggle **2.1.1** is CSP-safe; host still uses `_toggle_password_csp` + Stimulus.

## Upgrading from 1.6.3 to 1.6.4

**Session longevity, project session cookie name, PWA logout fix, Mercure 0.8.** Pull, then:

```bash
git fetch --tags
git checkout v1.6.4   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone (restarts PHP so session.ini applies)
make migrate            # no new migrations for this release
```

### Notes

- No Doctrine schema changes. Re-run `make seed-platform` (or Admin → Cookie consent) so inventory names/durations match: session cookie `SYMFONY_BEACON_SESSID` (1 day), `REMEMBERME` (30 days).
- **Session**: without Remember me, login lasts **1 day** (`framework.session` + PHP `session.gc_maxlifetime`). With Remember me, **30 days**. Cookie name is `beacon.session_cookie_name` in `config/parameters.yaml` (default `SYMFONY_BEACON_SESSID`) — operators must sign in again after upgrade (old `PHPSESSID` is ignored).
- **PWA**: `/manifest.webmanifest` and `/sw.js` strip `Set-Cookie` so a guest bootstrap fetch cannot overwrite an authenticated session.
- **Mercure**: `ConfiguredMercure` uses `symfony/mercure` 0.8 `Grant` objects; `/account/realtime/config` works again when Mercure is enabled.

## Upgrading from 1.6.2 to 1.6.3

**Show-once DSN restore, 2 MiB JSON import cap, Cookie Consent public-only routes.** Pull, then:

```bash
git fetch --tags
git checkout v1.6.3   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations for this release
```

### Notes

- No Doctrine schema or seed changes required.
- Kit pin: Cookie Consent **1.6.3** (`render_routes` whitelist). Host config renders consent only on public shells (`legal_*`, `nowo_auth_kit_*`, setup, guest locale, home redirect). Authenticated dashboards omit the fragment; footer “Manage cookies” links to `/legal/cookies`.
- **API keys / DSN**: Settings ordinary GET shows the **public key only**. Full DSN appears once after create/rotate (session flash + copy control) and is not re-listed on later page loads. Rotate to mint a new secret.
- **Config imports**: project Settings, Administration → Projects, and instance config reject JSON uploads over **2 MiB**.

## Upgrading from 1.6.1 to 1.6.2

**Export/import Settings UI tabs.** Pull, then:

```bash
git fetch --tags
git checkout v1.6.2   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations for this release
```

### Notes

- No Doctrine schema, seed, or kit pin changes.
- Project Settings and Administration → Projects show Export / Import as tabs inside the config portability card (same Stimulus `tabs` controller as Admin permissions locale tabs). Clear Symfony Twig cache if templates look stale.

## Upgrading from 1.6.0 to 1.6.1

**Perf / ingest DRY / Mercure JWT guard + kit pins.** Pull, then:

```bash
git fetch --tags
git checkout v1.6.1   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations for this release
make vite-build         # if frontend lockfile / assets changed in your tree
```

### Notes

- No Doctrine schema or seed changes required.
- Kit pins: Dashboard Menu **2.1.1**, Cookie Consent **1.6.2**.
- Outside `dev`/`test`, if `MERCURE_JWT_SECRET` is set it must not be the `.env.dist` placeholder and must be at least 32 characters (`SiteBackupSecurityDefaultsGuard`). Empty remains allowed when Mercure is unused.
- Project config export/import: N+1 fixes are transparent. Panel import still cannot promote to `owner`/`full`; import (panel or admin) will not demote/deactivate the last active owner.

## Upgrading from 1.5.1 to 1.6.0

**Project config export/import (`089`), DSN UUID path, AuthKit 1.16.** Pull, then:

```bash
git fetch --tags
git checkout v1.6.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # Version20260811110000 — project.code + membership.active
make vite-build         # if frontend lockfile / assets changed in your tree
```

### Notes

- Migration backfills `project.code` from `slug` and sets `project_membership.active=true` for existing rows.
- New UI: Administration → Projects export/import; Project Settings export/import (`beacon-project-bundle` v1). API secrets are never exported. Admin import may create **disabled** users; Settings import skips unknown emails.
- Memberships can be deactivated/reactivated without delete (`project.members.manage`). Inactive memberships grant no product access.
- **DSN path**: newly copied DSNs use the project **UUID**. Legacy numeric ids in the path still work on ingest. Prefer re-copying DSN from Settings for new clients (`docs/DSN.md`).
- **API keys**: managers may copy DSN under **active** keys; **revoked** keys never show a copyable DSN.
- AuthKit **1.16**: magic-login confirm interstitial is kit-owned (`confirm_interstitial: true`). Host decorator / custom confirm controller removed; optional Twig override under `templates/bundles/NowoAuthKitBundle/`.

## Upgrading from 1.5.0 to 1.5.1

**Owner membership UI + kit admin modal chrome.** Pull, then:

```bash
git fetch --tags
git checkout v1.5.1   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations for this release
make vite-build         # kit admin styles / modal token remap
```

### Notes

- No Doctrine schema or seed changes required.
- Membership rows with role `owner` no longer expose edit-role / remove in Settings or Administration → Projects; use **Transfer ownership** to hand off primary ownership (server guards unchanged).
- Kit Menus / Breadcrumbs modals inherit Beacon tokens when UiKit portals `.nowo-ui-modal` to `<body>`.

## Upgrading from 1.4.0 to 1.5.0

**Project role `full` + InstanceRole delete guards** (`088`). Pull, then:

```bash
git fetch --tags
git checkout v1.5.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate            # no new migrations expected for this release
make seed-platform      # upserts ROLE_PROJECT_FULL + refreshes system role matrices
make vite-build         # if frontend assets changed in your tree
```

### Notes

- No Doctrine schema migration: `project_membership.role` already stores a string (`length: 20`) and accepts the new `full` value.
- After upgrade, re-run `make seed-platform` (or `app:seed-platform`) so `ROLE_PROJECT_FULL` appears under Administration → Roles.
- Existing projects are unchanged until the next ownership transfer (former owner becomes `full` instead of `admin`).
- Operators can manually assign membership role `full` where appropriate; groups still cannot use `owner` / `full`.

## Upgrading from 1.3.1 to 1.4.0

**Project permissions + Administration RBAC + security audit hardening** (`087` / 6.36). Pull, then:

```bash
git fetch --tags
git checkout v1.4.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make migrate
make seed-platform      # or: make seed — menus/permissions catalog + ROLE_ADMIN menu items
make vite-build         # Stimulus tabs + confirm-dialog / kit SCSS
```

### Migrations (since 1.3.1)

| Migration | Purpose |
|---|---|
| `Version20260809120000` | Create instance permission/role assignment tables |
| `Version20260809120100` | Rename `.system` → `is_system` (MySQL reserved word) |
| `Version20260809160000` | Rename to shared `permission` / `role` / `role_permission` / `role_user` |
| `Version20260810013000` | Temporary JSON translation columns on `permission` |
| `Version20260810023000` | `permission_translation` table; drop JSON translation columns |
| `Version20260810030000` | Ops defaults columns on `instance_settings` (envelope/metrics/inbound/SSRF) when missing |
| `Version20260810100000` | `metrics_require_token` column default **true** for new rows |

### Operator notes

- **API keys / DSN**: Managers with `project.api_keys.manage` may copy the full DSN under **active** keys when the secret is available; create/rotate still flashes a one-shot banner. **Revoked** keys never show a copyable DSN (`087` amendment 2026-08-11 / `002` FR-003).
- **`APP_SECRET`**: Outside `dev`/`test`, documented `.env.dist` values (`ChangeMePleaseUseARealSecret`) and secrets shorter than 16 characters fail boot. Generate with `openssl rand -hex 32` before exposing the instance.
- **`app:seed-demo`**: Blocked outside local environments unless `--allow-non-local` (random API keys only — never stable DEMO_* material). Prefer `make dogfood` only on laptop stacks.
- **Metrics**: New installs default `metrics_require_token` to **true**. Existing rows keep their stored value — enable require-token and set a Bearer token under Administration → Ops defaults if you scrape `/metrics`.
- **Instance config import**: JSON import cannot weaken SSRF private-URL allow, anonymous hook resolve, query-auth reject, or metrics require-token; it may still tighten those flags.
- **Sessions (prod)**: Cookies use `secure` + `httponly` + `SameSite=Lax`.
- **Slack**: Interactions URL no longer echoes unsigned Events-style `url_verification` challenges.
- **Project access**: Product mutations and project Settings use named `project.*` permissions from membership role (`docs/product/ROLES.md`). Administration remains `ROLE_ADMIN`-only (no `admin.*` catalog).
- **Admin URLs**: `/settings/*` → `/admin/*` with **301** redirects. Re-run platform seed so menus/breadcrumbs use `admin_*` routes.
- **Dashboard Menu 2.1.0**: required for tagged `MenuCurrentMatcherInterface` sidebar highlighting.
- Built-in `admin.*` permission rows are purged by platform seed; custom keys you added under other prefixes are kept.

## Upgrading from 1.3.0 to 1.3.1

**No migrations.** FormKit host-form completion + `086` demo-factory follow-up + unit tests. Pull, then:

```bash
git fetch --tags
git checkout v1.3.1   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
# make vite-build       # not required for this patch (no asset changes)
```

### Operator notes

- Envelope / OTLP HTTP contracts unchanged.
- Demo/dogfood seed still uses the same fixed public/secret keys; creation path now goes through `ProjectFactory`.
- Host admin UI forms (notification destinations, thresholds, issue assignee) use FormKit Types — no operator config change.

## Upgrading from 1.2.0 to 1.3.0

**No migrations.** Maintainability + form chrome (`086-dry-refactor`). Pull, then:

```bash
git fetch --tags
git checkout v1.3.0   # or pull main at the release commit
composer install
make ensure-up          # or make up on a fresh clone
make vite-build         # required for .checkbox + password-toggle CSS in public/build/
```

### Operator notes

- OTLP HTTP paths and Envelope contracts are unchanged; shared PHP/Twig structure and host form chrome only.
- `make test` / `make phpstan` / `make shell` (and other exec targets) auto-call `ensure-up` if Compose is down — they do **not** rebuild images or run Vite. Use `make up` when you need `--build` + asset build.
- If the password field “shrinks” on focus while a password manager is enabled, disable the extension to confirm — that UI is injected by the extension (e.g. NordPass), not by Beacon.
- PHPUnit test DB URL uses `pid_sqlite:` (`BEACON_TEST_DATABASE_URL` comment in `.env.dist`); no operator action for production.

## Upgrading from 1.1.0 to 1.2.0

**No migrations.** Developer + local Compose changes only.

```bash
git fetch --tags
git checkout v1.2.0   # or pull main at the release commit
composer install
pnpm install          # adds vitest / jsdom / @vitest/coverage-v8
docker compose up -d  # picks up env_file + port defaults
make vite-build       # optional if you only run PHP tests
```

### Local Compose / `.env`

- Compose services load secrets via `env_file: .env` (keep a real `.env` from `.env.dist`; do not commit it).
- Fresh `.env.dist` defaults: HTTPS `9447`, HTTP ingest `9084`, Vite `5177`, Mailpit UI `18026` / SMTP `1027`. Existing `.env` values are kept — update bookmarks / BeaconBundle client DSNs if you adopt the new defaults.
- MySQL is **not** published on the host. Use `make mysql` or `docker exec -it mysql-9.7-primary mysql …`. Update any host tooling that used `localhost:3308`.
- Prod (`compose.prod.yaml`) still fail-fast on missing `APP_SECRET` / MySQL / Mercure / SiteBackup secrets; other keys come from `.env`.

### Vitest (frontend unit)

- Run asset unit tests in the php container: `make test-unit-js` (or `pnpm run test:unit`).
- Coverage HTML/LCOV: `make test-unit-js-coverage` → `var/coverage-js/`.
- Specs live next to sources as `assets/**/*.test.ts`; config: `vitest.config.ts`. See [CONTRIBUTING.md](CONTRIBUTING.md).

### PHPUnit / Playwright

- Additional Unit tests under `tests/Unit/` (AuthKit, Issues changers, Setup, Ops, …). Filter: `make test ARGS='--testsuite Unit'`.
- Extra Playwright deep specs (`e2e/*-deep.spec.ts`, cookie consent, share access). Same `make test-e2e` flow as **1.1.0** (default base URL `https://localhost:9447`).

## Upgrading from 1.0.1 to 1.1.0

```bash
git fetch --tags
git checkout v1.1.0   # or pull main at the release commit
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
php bin/console assets:install
```

When this release includes kit admin `css_framework: tailwind` (no Bootstrap in Vite `kit-admin`; `.kit-admin` remaps UiKit tokens) and kit pins FormKit **2.2.0** / AuthKit **1.15.0** / UiKit **1.7.0** / RoutingKit **1.3.0** (plus Menu/Breadcrumb/CookieConsent/SiteBackup/HttpLog as pinned): smoke menus / breadcrumbs / cookie admin (`/cookie-consent-config/{id}/settings` → `/settings/profile`) / `/admin/_routing/` (Symfony/FormKit create-edit form) / `/setup` + `/_site_backup` for shell chrome (aside, brand, crumbs) and modals. Confirm list pagination shows `«` / `»` + page numbers. After AuthKit **1.15**, run `bin/console doctrine:encrypt:database --force` once so existing social OAuth plaintext rows become Halite ciphertext. Re-run `make seed` (or platform seed) so breadcrumb trails include `nowo_cookie_consent_config_settings_section`. Clear UX Icons cache if you rely on Iconify on-demand icons (`bin/console cache:clear`). See `specs/081-formkit-uikit-kit-sync/`.

**HttpLogBundle (`nowo-tech/http-log-bundle` 1.0.1):** run migrations for `nowo_http_log_entry`, ensure Messenger workers consume `PersistHttpLogMessage` / `ExportHttpLogMessage` / `PurgeHttpLogMessage` (routed to `async`), open **Administration → HTTP log** (`/admin/http-log`, `ROLE_ADMIN`), and schedule `nowo:http-log:purge`. Re-run platform/demo seed so the administration menu and breadcrumbs include the new routes. Document HTTP audit logging (IPs / user identifiers) in operator privacy copy — see [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md).

### Appearance theme presets (`082` / 6.32)

Migrations `Version20260805120000`–`Version20260805160000` add `site_appearance.theme_id`, `footer_fixed`, `corner_style`, `border_strength`, and `theme_id_dark` (dark preset ids formerly stored in `theme_id` are moved). After migrate, open **Administration → Appearance → Themes** and confirm light/dark cards; review Brand / Layout / Colors tabs. Instance config export/import includes the new keys — see `specs/082-appearance-theme-presets/`.

### Ops env → database (`084`)

Configure envelope max bytes, reject-query-auth, metrics token, inbound email, SSRF / anonymous Resolve under **Administration → Ops defaults** (no longer via `BEACON_*` env). Copy any previous env values into the UI once, then remove the obsolete env keys from `.env` / Compose. Instance config export is **v3**.

### Architecture / modules (`083` / `085`)

No operator action beyond migrate + seed. Code moved: `Ops` module, `Ingest\Otlp\*`, domain enums under Issues/Project, Project admin controllers under `Project`, demo fixtures under `Setup\Demo`. Optional Compose service `messenger-notify` for notification drain isolation — see [ARCHITECTURE.md](ARCHITECTURE.md) / `compose.yaml`.

### PHPUnit layout (developers)

Tests live under `tests/Unit/`, `tests/Functional/`, `tests/Integration/`, with helpers in `tests/Support/`. Filter by suite: `vendor/bin/phpunit --testsuite Unit`. PHPUnit uses `BEACON_TEST_DATABASE_URL` (default SQLite under `/dev/shm`; per-PID override in `tests/bootstrap.php`). See [CONTRIBUTING.md](CONTRIBUTING.md).

### Playwright E2E (developers)

After `make up` + `make seed` (+ `make seed-sample` for issue/performance flows): `make test-e2e`. See `e2e/README.md`.

### Vitest (developers; shipped in 1.2.0)

Frontend unit tests: `make test-unit-js` / `make test-unit-js-coverage`. See [Upgrading from 1.1.0 to 1.2.0](#upgrading-from-110-to-120).

### Security remediations (Codex Security medium findings)

Hardening included in **1.1.0** (apply on upgrade without a separate migration):

- Project settings hide full ingest DSN/secrets from Viewer/Member and share-link sessions (owner/admin only).
- Revoking a share link invalidates redeemed session grants (share UUID stored in session and re-checked).
- Changing account email requires the current password.
- Multi-item Envelope first-of-day stats reuse the pending `DailyProjectStat` insert (no unique-key poison).
- Production image uses `Caddyfile.prod` (HTTP `/api/*` cleartext ingest disabled; HTTPS DSN only).
- Mailer DSN allowlist rejects `sendmail` / `native` (no host `?command=` execution from admin UI).
- CSV exports neutralize spreadsheet formula cells (`=`, `+`, `-`, `@`, …).
- AI issue export redacts nested secrets, URL userinfo/query, tags, and bearer-like breadcrumbs.
- Share-link max-uses claimed with an atomic SQL update (no concurrent over-consume).
- HTTP audit log ignores AuthKit reset-password and magic-login routes/paths (tokens never stored in `path`).
- Inbound email reply tokens bind the recipient email; spoofed `From` is ignored.
- Teams Resolve/Assign action tokens include a nonce and are consume-once via `cache.action_token`.

## Upgrading from 1.0.0 to 1.0.1

```bash
git fetch --tags
git checkout v1.0.1   # or pull main at the release commit
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

**1.0.1** is a patch: documentation layout (`docs/product|ops|dev`) plus Doctrine query reductions on list/export, retention, ingest thresholds, and related paths. **No new migrations.** Bookmark updates only if you linked secondary manuals by old paths (see [docs/README.md](README.md)).

## Upgrading from 0.17.0 to 1.0.0

```bash
git fetch --tags
git checkout v1.0.0   # or pull main at the release commit
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

**1.0.0** is the first stable major. Changes since **0.17.0** are additive. Migrations to apply: `Version20260731180000` (destination `signing_secret`), `Version20260731190000` (`app_user.slack_user_id`), `Version20260731200000` (AuthKit QR + enterprise SSO + phone), `Version20260731210000` (`inbound_email_message`).

Requires **AuthKit 1.12.1** and `endroid/qr-code` ^6 (Composer lock).

### OTLP logs / traces / metrics (`067` / `070` / `074`)

- New routes: `POST /api/{projectId}/otlp/v1/logs`, `/traces`, `/metrics` (same `X-Beacon-Auth` as Envelope; no query auth).
- No schema migration for OTLP. Caps **200** mapped records/spans/data points per request. See [API.md](API.md) / [DSN.md](DSN.md).

### Slack interactive Resolve + Assign (`068` / `071`)

- Optional encrypted **signing secret** on Slack notification destinations (migration `…180000`).
- Configure Slack App Interactivity Request URL → `POST /hooks/slack/interactions`.
- Optional Account **Slack user ID** (`…190000`) enables **Assign to me** and Resolve actor attribution. Guide: [NOTIFICATIONS.md](product/NOTIFICATIONS.md).

### Teams interactive Resolve + Assign OpenUri (`069` / `073`)

- Teams destinations with a signing secret get MessageCard **Resolve** (HttpPOST) and **Assign to me** (OpenUri).
- Ensure `DEFAULT_URI` is the public Beacon origin so OpenUri targets `/hooks/teams/assign-me`.
- Assign requires Beacon login + project triage (no Teams user-id mapping).

### AuthKit 1.12 + QR login (`072` / `075`)

- Migration `Version20260731200000` adds `auth_kit_qr_login_challenge`, `auth_kit_social_credential.enterprise_sso`, and `app_user.phone` / `phone_verified_at`.
- QR login is enabled in `nowo_auth_kit.yaml`; users set a phone on Account → Profile.
- QR show pages render PNG (with `ext-gd`) or SVG data URIs. SMS OTP remains Later.
- Mark OIDC IdPs as **Enterprise SSO** in Administration → Social login when they should appear under the organization heading.

### Inbound email comments (`076` / 6.28)

- Migration `Version20260731210000` adds `inbound_email_message`.
- Opt-in: `BEACON_INBOUND_EMAIL_ENABLED`, `BEACON_INBOUND_MAIL_DOMAIN`, `BEACON_INBOUND_WEBHOOK_SECRET`. Guide: [INBOUND-EMAIL.md](product/INBOUND-EMAIL.md).
- Stores reply bodies as issue comments (personal data) — update privacy/terms as needed.

### Other

- Branded error pages now cover 400/401/408/429/502 in addition to 403/404/500.
- Constitution Principle X: do not add Cursor co-author trailers on commits/PRs.

## Upgrading from 1.0.1 (ops env → database)

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
```

Migration `Version20260803140000` adds remaining ops knobs to `instance_settings` (envelope max bytes, reject query auth, metrics token/require, inbound email, allow private URLs, anonymous Resolve).

Re-apply any former env values under **Administration → Ops defaults**. Secrets (metrics token, inbound webhook secret) are encrypted at rest — blank fields on save keep the current secret.

In production, enable **Require metrics scrape token** and set a token (this replaces the former `when@prod` `BEACON_METRICS_REQUIRE_TOKEN=1` default). For local private webhooks, enable **Allow private notification URLs**.

The former `BEACON_INGEST_REJECT_QUERY_AUTH`, `BEACON_METRICS_*`, `BEACON_ENVELOPE_MAX_BYTES`, `BEACON_INBOUND_*`, `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS`, and `BEACON_HOOKS_ALLOW_ANONYMOUS_RESOLVE` variables are no longer read and may be removed from `.env`. Instance config export is now **v3**; imports continue to accept v1–v2 files.

## Upgrading from 0.16.0 to 0.17.0

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

Migration `Version20260731170000` adds operational defaults to `instance_settings`. After migrating, configure retention, ingest rate, daily/monthly quotas, delivery history, and circuit-breaker behavior under **Administration → Ops defaults**.

The former `BEACON_RETENTION_*`, `BEACON_INGEST_RATE_LIMIT`, `BEACON_EVENT_QUOTA_*`, `BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT`, and `BEACON_NOTIFICATION_CIRCUIT_BREAKER_*` variables are no longer read and may be removed. Instance config export is now v2; imports continue to accept v1 files.

### Mailer DSN audit (`6.15`)

- Saving or clearing Administration → Mailer records `UserAction` `instance.mailer_updated` with redacted `scheme`/`host` only (never DSN secrets).
- `MailerDsnValidator` rejects schemes outside the allowlist (`smtp`/`smtps` + common provider schemes). `sendmail`/`native` are blocked (host command execution via `?command=`).

### Local Mailpit (`066`)

- The Flex `mailer` service in `compose.override.yaml` is now behind Compose profile **`mail`** (not started by `make up`).
- Start with `make mailpit` (or `docker compose --profile mail up -d mailer`). UI default: http://localhost:18026; PHP DSN: `smtp://mailer:1025` under **Administration → Mailer**.
- Host ports: `MAILPIT_UI_PORT` / `MAILPIT_SMTP_PORT` in `.env` (defaults 18026 / 1027 in `.env.dist`). Guide: [MAILPIT.md](ops/MAILPIT.md).
- Production (`compose.prod.yaml`) never includes Mailpit.

### Social login admin (extends `060`)

- Manage OAuth provider credentials under **Administration → Social login** (create / edit / delete / enable).
- `app:seed-social-login` and `AUTH_KIT_SOCIAL_*` env bootstrap are removed; migrate any env-seeded providers via the admin UI if needed.
- AuthKit profile `social_login.mode` must still be enabled for buttons to appear on login.

## Upgrading from 0.15.0 to 0.16.0

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

Migrations since **0.15.0**: `Version20260731140000` (`anonymized_at`), `Version20260731150000` (share-link max uses), `Version20260731160000` (`project_read_token`).

Rebuild front-end assets after upgrade (CSP Stimulus controllers, cookie-consent padding, kit-admin JSON islands, theme-boot on guest shell).

### GDPR account export / anonymize (`043`)

- Account → **Privacy** (`/account/privacy`): download JSON export; optional self-service anonymize (blocked if sole project owner or last instance admin).
- Admin → Users: **Export data** / **Anonymize** for other accounts.
- Anonymize does **not** purge project events/issues — see [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md).
- Runtime anonymize is app-owned (do not use `anonymize-bundle` as the production executor).

### Collaboration / API (`040`–`042`, `044`, `061`)

- Issue `@mentions` and assignee email require a deliverable encrypted instance Mailer.
- Similar-issues suggestions appear on issue show (cap 5).
- Project Settings → read API tokens (`brt_…`); Envelope ingest keys are rejected on the read API.
- Administration → Instance config: JSON export/import of allowlisted appearance + flags (secrets rejected).
- Share links support optional `max_uses` (UI default **1**; clear for unlimited until expiry).

### SiteBackup / locales (`056`, `062`, kits)

- Requires **SiteBackupBundle ≥ 1.7.0**: bare `/setup` serves `DEFAULT_LOCALE`; other locales use `/{_locale}/setup`. Align `setup.locale.enabled` with `%fallback_locales%` — see [ADDING-LOCALES.md](dev/ADDING-LOCALES.md).
- Non-`dev`/`test` environments fail closed when `SITE_SETUP_TOKEN` / `SITE_BACKUP_PASSWORD_HASH` are empty or still the local defaults (`062`).
- Docker image builds that run `cache:clear` / `cache:warmup` / `assets:install` skip that secrets guard (`064-sitebackup-guard-skip-cache-clear`).

### RoutingKit (`064-routing-kit`)

- Dependency `nowo-tech/routing-kit-bundle` (**≥ 1.3.0**); config `config/packages/nowo_routing_kit.yaml`; admin UI `/admin/_routing/`.
- Panel create/edit uses FormKit (`RoutePathDefinitionType`); host Twig overrides must keep `form_*` (see `081-formkit-uikit-kit-sync`).
- Use `#[Routable]` for app controllers that need dual locale paths. AuthKit and SiteBackup keep their own locale loaders.

### Branded HTTP errors (`063-branded-http-errors`)

- Twig overrides under `templates/bundles/TwigBundle/Exception/`; illustrations in `public/illustrations/error-{400,401,403,404,408,429,500,502,503}.png`.
- Preview `/_error/{code}` is registered **only** when `APP_ENV=dev`.
- **503**: use `error-503.png` for both Symfony error503 and the MaintenanceMode public page (`092`).

### Site-wide maintenance mode (`092-maintenance-mode`)

- Composer: `nowo-tech/maintenance-mode-bundle`; config `config/packages/nowo_maintenance_mode.yaml` (panel `/admin/maintenance`, preview `/_maintenance_preview`).
- Host Twig: `templates/bundles/NowoMaintenanceModeBundle/` + `kit/maintenance_mode_panel_layout.html.twig`.
- Re-run **`make seed-platform`** so Administration menus/breadcrumbs include Maintenance and Maintenance preview.

### CSP delivery (post-0.15.0 hardening)

- CSP is emitted by PHP (`ContentSecurityPolicySubscriber`), not Caddy, so the Web Debug Toolbar can merge nonces.
- Debug CSP allows `'unsafe-eval'` for the toolbar; `/_wdt` and `/_profiler` skip app CSP.
- Kit admin pages no longer rely on inline `window.*Config` scripts (JSON islands + Vite `kit-admin`).
- Password toggle / confirms / selects need the rebuilt Stimulus entrypoints — see [PRODUCTION.md](PRODUCTION.md#security-headers-caddy).

### Account display preference defaults

- New users always persist locale (`%default_locale%`), theme `light`, contrast/motion `system` (and related appearance columns).
- Opening `/account/display` heals legacy null columns; anonymized accounts stay scrubbed.
- No migration required (data heal on persist/update and account display).

### CI / coverage (`033`)

- Optional local `make test-coverage`; CI Coverage job is informational until `COVERAGE_MIN` is set — [CONTRIBUTING.md](CONTRIBUTING.md).

## Upgrading from 0.14.0 to 0.15.0

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Notification circuit breaker (`039`)

- New columns on `notification_destination`: `consecutive_failures`, `circuit_opened_at`.
- Env (optional): `BEACON_NOTIFICATION_CIRCUIT_BREAKER_THRESHOLD` (default `5`), `BEACON_NOTIFICATION_CIRCUIT_BREAKER_COOLDOWN_MINUTES` (default `0` = pause until admin Resume).
- Project Settings shows **Auto-paused** + **Resume** when a destination trips.
- See [NOTIFICATIONS.md](product/NOTIFICATIONS.md).

### CSP / HSTS

- Rebuild front-end assets (`pnpm install` + `make vite-build`) — new Vite entries `kit-admin` and `swagger-ui-boot`; Bootstrap is a JS dependency (no CDN).
- Default CSP no longer allows inline scripts; confirm forms and saved-view select use Stimulus.
- HSTS is on by default for non-localhost hosts (`max-age=31536000; includeSubDomains`). Local `https://localhost:…` is excluded. If TLS terminates in front of Caddy, set HSTS there or via `CADDY_SERVER_EXTRA_DIRECTIVES`.
- See [PRODUCTION.md](PRODUCTION.md#security-headers-caddy).

### Appearance palette

- Migration adds `site_appearance` warn / paper / ink / surface colors (light + dark). Existing rows get defaults; review Administration → Appearance after upgrade.

## Upgrading from 0.13.0 to 0.14.0

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Prometheus `/metrics` (`038`)

- Scrapers: `GET /metrics` with session as `ROLE_ADMIN`, or `Authorization: Bearer <BEACON_METRICS_TOKEN>`.
- **Query `?token=` is rejected** (use Bearer only).
- Production: set a non-empty `BEACON_METRICS_TOKEN` (`.env.dist` / Compose). Empty token in prod fails closed when `BEACON_METRICS_REQUIRE_TOKEN=1`.
- See [PRODUCTION.md](PRODUCTION.md) and [API.md](API.md).

### Security residual

- **`BEACON_INGEST_REJECT_QUERY_AUTH` defaults to `1`** in all environments (not only prod). Migrate Envelope clients to `X-Beacon-Auth` or envelope `dsn`; set `0` only during a short migration window.
- Guest locale switch rejects backslash / protocol-relative open redirects (`SafeInternalRedirect`).
- Web Push unsubscribe is scoped to the signed-in user (endpoint hash alone is not enough).
- Magic login confirm page no longer auto-POSTs; user must click **Continue**.
- Theme preferences boot via blocking IIFE compiled from `assets/theme-boot.ts` → `public/build/theme-boot.js` (FOUC-safe; CSP `script-src 'self'`). Rebuild front-end assets after upgrade.

## Upgrading from 0.12.8 to 0.13.0

```bash
git pull
composer install
# If Packagist lacks nowo-tech/beacon-bundle, composer.json already lists the GitHub VCS repository.
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Security / ops

- **OpenAPI UI moved** to **`/admin/api/doc`** and **`/admin/api/doc.json`** (was `/api/doc` / `/api/doc.json`). Still requires **`ROLE_ADMIN`**. Re-seed menus/breadcrumbs (`app:seed-platform`) so links and crumbs use route `admin_api_doc`.
- **Magic login** consumes via **POST** (`check_post_only: true`): email links open a confirm page; the user must click **Continue** to POST. Bookmarklets/scripts that only GET the signed URL must POST `user` / `expires` / `hash`.
- **Query-string ingest auth:** defaults to **`BEACON_INGEST_REJECT_QUERY_AUTH=1`** in all environments (401). Migrate clients to `X-Beacon-Auth` or envelope `dsn`; set `0` only during migration.
- **`/metrics`:** production requires a non-empty `BEACON_METRICS_TOKEN` (`BEACON_METRICS_REQUIRE_TOKEN=1`); scrapers use `Authorization: Bearer` only.
- **Web Push:** subscribe/delivery accept only known HTTPS push hosts (FCM / Mozilla / Apple).
- **Webhooks:** delivery pins DNS after SSRF checks (`resolve`); keep `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS=0` in prod.
- **Caddy:** baseline CSP (`object-src 'none'`) / frame / referrer / nosniff headers; theme boot is same-origin JS; add HSTS via `CADDY_SERVER_EXTRA_DIRECTIVES` in prod (see [PRODUCTION.md](PRODUCTION.md)).

### Dogfooding + AI export

- New dependency: `nowo-tech/beacon-bundle`. Empty `BEACON_DSN` keeps reporting off.
- Local first-run: prefer `make ready` (bootstrap + seed). Seed writes loopback `BEACON_DSN` into `.env` only when empty; then `make restart`.
- Demo API keys created by seed use a **stable secret** (`DEMO_SECRET_KEY`) in addition to the existing stable public key — for new keys only.
- Issue show: **Copy for AI** / Markdown+JSON download (`059`, [AI-EXPORT.md](product/AI-EXPORT.md)).

### SiteBackupBundle 1.5.0

- Require `nowo-tech/site-backup-bundle:1.5.0`. Wizard URL **`/setup`**, panel **`/_site_backup`**.
- **`setup.layout_template: kit/site_backup_setup_layout.html.twig`** and **`panel.layout_template: kit/site_backup_panel_layout.html.twig`** — host chrome only (Twig globals `nowo_site_backup_*_layout_template`).
- Restyle vendor markup with `.nowo-site-backup-setup` / `.nowo-site-backup-panel` / `.nowo-ui-*` in `assets/styles/_setup.scss`.
- Bundle shows detector reasons + hides database Skip when connection failed.
- Upstream notes: [SiteBackupBundle UPGRADING](https://github.com/nowo-tech/SiteBackupBundle/blob/main/docs/UPGRADING.md) · [CHANGELOG](https://github.com/nowo-tech/SiteBackupBundle/blob/main/docs/CHANGELOG.md)

### SiteBackupBundle 1.3.x–1.4.x (historical)

- **Bootstrap choice** on `fresh_install`: guided (create admin + optional sample) or **full database** SQL import.
- Deep-link profile: `/setup?profile=full_database`.
- Durable progress (`progress_storage: chain`), `advance_mode: manual`, incomplete detector.
- 1.4 introduced setup `layout_template`; 1.5 adds panel `layout_template` + layout Twig globals.

### Composer / Symfony patches

- Symfony 8.1 components bumped where patches exist (`framework-bundle` 8.1.2, `http-client` 8.1.3, …).
- Dev tooling: PHPUnit 13.2.6, PHPStan 2.2.7, Rector 2.5.8, PHP-CS-Fixer 3.95.17.
- `symfony/ux-icons` stays on **2.36.1** (3.x is a major; not taken in this bump).

## Upgrading from 0.12.7 to 0.12.8

```bash
git pull
composer install
# Recreate PHP container if you pull the FrankenPHP memory_limit mount (cache:clear OOM fix):
docker compose up -d --force-recreate php
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Monthly event quota (`032`)

- Env `BEACON_EVENT_QUOTA_MONTHLY` (default `0` = unlimited) + nullable `project.event_quota_monthly`.
- Run migrations; Settings → Governance exposes the field (same inherit/`0` semantics as daily).
- Envelope returns `429 monthly event quota exceeded` (`Retry-After: 3600`) when the UTC month cap is hit; worker drops queued envelopes likewise. Daily and monthly both apply when configured.
- Approaching warning at 80% of the monthly cap (`flash.project.quota_monthly_approaching`).

### Dual public URLs (AuthKit)

- **Default locale** (`DEFAULT_LOCALE`, e.g. `es` in this project’s `.env`, `en` in `.env.dist`): bare paths serve content — `/login`, `/register`.
- **Other locales**: prefixed — `/en/login`, `/en/register`.
- AuthKit uses `locale.in_path: both` + `unlocalized: serve` (see `config/packages/nowo_auth_kit.yaml`).
- Cold-start / restore setup is SiteBackup at **`/setup`** (panel **`/_site_backup`**). Set **`SITE_SETUP_TOKEN`** (open `/setup?token=…`) and **`SITE_BACKUP_PASSWORD_HASH`** (see `.env.dist`). Outside **`dev`/`test`** (including `prod` and `staging`), Beacon refuses the documented local defaults — rotate before deploy (`062`).
- Legal pages still redirect bare → `/{DEFAULT_LOCALE}/legal/…`.
- Dashboard / app shell URLs never include `_locale` (account `preferredLocale`).
- Operator manual: [ADDING-LOCALES.md](dev/ADDING-LOCALES.md).

### Ops overview (`035`)

- Instance admins: **Administration → Ops** (`/admin/ops`) — Messenger async depth (instance-wide), open issues, suspended ingest, error spikes, failed last deliveries; optional `?project=` UUID filter.
- Re-run `app:seed-platform` (or menu/breadcrumb seed) so the Administration sidebar includes **Ops**.

### Setup wizard (SiteBackup)

- Missing schema / `setup.required` / empty platform catalogs force visitors toward **`/setup`**.
- Wizard profile runs migrations, `app:seed-platform`, admin creation, optional sample data.
- Existing instances: `mkdir -p var/site-backup && touch var/site-backup/setup.done` after `app:seed-platform` if needed.
- Details: [INSTALL.md](INSTALL.md).

### AuthKit password reset + magic login

- Password reset and magic login are AuthKit routes/templates (custom `MagicLoginController` removed).
- Migration `Version20260721250000` adds `password_reset_token` / `password_reset_expires_at` on `app_user`.
- Magic login / reset email still require a deliverable encrypted Mailer DSN under Administration → Mailer.
- Password reset `delivery: both` (email link + OTP code at `/reset-password/complete`).
- Logout uses CSRF (`enable_csrf: true`); UI must pass `_csrf_token` (AuthKit embed pattern).
- **Social / OAuth login is not part of AuthKit** (SSO/OIDC remains roadmap Later).

### PHP memory for cache warm

- Compose mounts `.docker/frankenphp/conf.d/10-app.ini` with `memory_limit = 512M` so prod `cache:clear` does not OOM on Twig kit themes.

## Upgrading from 0.12.6 to 0.12.7

```bash
git pull
composer install
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Setup wizard before login

- When the instance has **no users**, `/setup` is public and offers **Minimum** (platform + demo admin) or **Full sample load** (minimum + `load` telemetry).
- After either preset, sign in with `admin@symfony-beacon.local` / `admin123` (or register your own first admin).
- Once users exist, only `ROLE_ADMIN` can reopen `/setup` (granular steps + dashboard banner until dismissed).
- Login shows a **First-run setup** link while bootstrap is open.

### Cookie consent from platform seed

- `app:seed-platform` / Setup platform step seeds the default cookie consent profile + inventory (`CookieConsentDemoSeeder`).
- `use_database_config: true` — re-run `make seed-platform` after upgrade to refresh professional modal copy.
- Details: [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md).

### Database docs + Compose MySQL path

- Schema overview: [DATABASE.md](dev/DATABASE.md).
- Local Compose MySQL data directory is `./.data/mysql` (gitignored). If you previously used a named Docker volume, migrate data or accept a fresh local DB.

### Fresh-install migration hardening

- Safer concurrent migrate / empty-DB setup stamps (`Version20260721195000` idempotent indexes; setup completion only when users exist).

## Upgrading from 0.12.5 to 0.12.6

```bash
git pull
composer install
# Ensure Mercure + VAPID env vars are set (see `.env.dist`: MERCURE_*, VAPID_*).
# Mercure JWT / hub setup: docs/ops/MERCURE.md
# Generate VAPID keys if enabling Web Push:
#   docker compose exec php php -r 'require "vendor/autoload.php"; print_r(Minishlink\WebPush\VAPID::createVapidKeys());'
docker compose up -d
php bin/console doctrine:migrations:migrate --no-interaction
# Encrypt any plaintext Mailer From / Mercure URL rows saved before this release:
#   php bin/console doctrine:encrypt:database --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Member live alerts (Mercure) + Web Push

- Compose `mercure` service + Caddy `/.well-known/mercure` (see [MERCURE.md](ops/MERCURE.md)).
- **Administration → Mercure** (`/settings/mercure`): master switch; optional URL / public URL / JWT overrides (env fallbacks).
- Off by default until an admin enables it (or sample seed copies env defaults — see below).
- Migrations: `Version20260721241000` (instance Mercure fields), `Version20260721240000` (`push_subscription` + user opt-in).
- Members opt in to **Web Push** under **Account → Display → Push notifications** (requires `VAPID_*`).

### Encrypted Mailer + Mercure instance settings

- `instance_settings.mailer_from`, `mercure_url`, and `mercure_public_url` join `mailer_dsn` / `mercure_jwt_secret` as Halite-encrypted columns (`#[Encrypted]`).
- Migration `Version20260721242000` widens `mailer_from` to `text` for ciphertext.
- Existing plaintext values stay readable until the next save, or run `doctrine:encrypt:database` after migrate.

### Sample seed enables Mercure

- `app:seed-sample` / Setup → sample data enables Mercure and fills blank URL / public URL / JWT from `MERCURE_*` without overwriting existing DB values.

### Product tours on Account → Display

- Active tours multi-checkbox + Select all (`nowo-tech/select-all-choice-bundle`) replaces the previous “mark all completed” control.

## Upgrading from 0.12.4 to 0.12.5

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
pnpm install
make vite-build
```

### Product tour (`057`)

- Migrations `Version20260721220000` / `Version20260721221000` add `app_user.product_tour_seen_at` and `product_tour_seen_pages`.
- Tours auto-start once per page (`dashboard`, `project_issues`, `admin`) after setup is complete; steps respect `ROLE_ADMIN` and project role capabilities.
- Finishing or closing a tour marks that page as seen — it will not auto-start again until Account → Display → **Replay**.
- Frontend depends on `driver.js` — run `pnpm install` (or `make pnpm ARGS='install'`) before `make vite-build`.

### Schema hardening (`event` tenancy + issue level)

- Migration `Version20260721230000`:
  - Ensures `event.project_id` (backfill from issue) with `NOT NULL` + FK CASCADE to `project`.
  - Replaces global `uniq_event_id` with `uniq_project_event_id` (`project_id`, `event_id`).
  - Adds `(issue_id, environment)` and `(issue_id, release_version)` indexes.
  - Normalizes unknown `issue.level` values to `error` (allowed: `fatal`, `error`, `warning`, `info`, `debug`).
- After retention purge, issue counters are recomputed from remaining events (no manual fix-up needed).

## Upgrading from 0.12.3 to 0.12.4

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-platform
make vite-build
```

### Install / seed layers (`055`) + setup wizard (`056`)

- **Platform seed** (`app:seed-platform` / `make seed-platform`) upserts menus and breadcrumbs. Use this after upgrades instead of re-running demo seed only for navigation.
- **`make bootstrap`** = migrate + platform seed (no longer auto-creates the demo user).
- **`make seed`** = platform + demo user/project + `.demo-client.env`.
- **Sample telemetry** moved to `app:seed-sample` (`make seed-sample`, sizes `dev`/`load`/`huge`). See [INSTALL.md](INSTALL.md).
- **Setup wizard** at `/setup` (Administration card + dashboard banner). Migration sets `setup_completed_at` on existing instances so upgrades are not interrupted.

## Upgrading from 0.12.2 to 0.12.3

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

### Brand & typography

- UI typeface is **Montserrat** (replaces Source Serif 4 / IBM Plex Sans for chrome). Code blocks still use IBM Plex Mono.
- Favicon / PWA icons under `public/brand/` and `public/icons/` use the tower + three-arc beacon mark.
- Rebuild frontend assets (`make vite-build`) and hard-refresh browsers / reinstall the PWA so cached icons and CSS update (`nowo_pwa` `cache_version` is now `v2`).
- No database migration is required for this release.

## Upgrading from 0.12.1 to 0.12.2

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

### Security hardening (`045`–`052`)

- **Webhooks**: delivery HTTP clients do not follow redirects. Update destinations that relied on 302 chains to point at the final HTTPS URL.
- **Share links**: issue-scoped tokens unlock only that issue (and its events), not the project issue list / analytics. Create a project-wide share link when broader read access is needed.
- **Admin / locale redirects**: `redirect` POST/query values must be same-origin relative paths; external URLs fall back to a safe default.
- **Prod Compose**: pull the updated `compose.prod.yaml` so `php` and `messenger` share the `php_secrets` volume (`/app/var/secrets`). Back up `.Halite.default.key` (or set `APP_ENCRYPT_KEY`) before recreating containers — see [PRODUCTION.md](PRODUCTION.md#field-encryption-key-halite).
- **Query ingest auth** (`049`): `?beacon_key=&beacon_secret=` still works but is deprecated (logs + `Deprecation`/`Warning` headers). Prefer `X-Beacon-Auth` or envelope `dsn` — see [DSN.md](DSN.md).
- **Health** (`050`): `/health/ready` 503 bodies no longer include exception text.
- **Worker governance** (`051`): envelopes already ACKed may be dropped if the project is suspended or daily quota is exceeded before the consumer runs.
- **API secrets** (`052`): ingest always requires the secret; empty-secret keys are rejected.

### Magic login requires encrypted Mailer DSN

- `/login/magic` and the password-login link are enabled only when **Administration → Mailer** has a stored, encrypted DSN that is not a `null://` transport.
- Env `MAILER_DSN` alone does **not** turn magic login on (it remains a fallback for other outbound mail).
- After saving a real SMTP (or other) DSN under Mailer, magic login appears automatically; clearing the DSN hides it again.
- Use **Send sample email** on the Mailer page to verify delivery; invalid DSNs and `null://` are rejected on save.

### Notifications sample send

- **Send test** on a destination now delivers a channel-native sample (and works while the destination is disabled). No configuration change required.

## Upgrading from 0.12.0 to 0.12.1

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

### Encrypted Mailer DSN (`034`)

- Migration `Version20260721193000`: table `instance_settings` (singleton id=1).
- Configure under **Administration → Mailer** (`/settings/mailer`): paste SMTP (or other) DSN; it is encrypted at rest like API secrets / webhook URLs.
- Optional **From** address (defaults to `beacon@localhost`).
- Env `MAILER_DSN` is only a **fallback** when no DB DSN is stored (keep `null://null` for local/tests). After saving a DB DSN, you can remove secrets from `.env`.
- **Magic-link sign-in** requires this encrypted DB DSN (non-null). Env fallback alone does not enable `/login/magic`.
- Ensure the Halite encrypt key under `var/secrets/` (or `APP_ENCRYPT_KEY`) is durable across deploys — see [PRODUCTION.md](PRODUCTION.md) and ROADMAP `048`.
- Re-run `app:seed-demo` (or sync menus) so Administration sidebar includes **Mailer**.

### Account appearance extras

- Migration `Version20260721194000`: `app_user.preferred_font_scale`, `preferred_contrast`, `preferred_sidebar`.
- Users set them under **Account → Display**; existing accounts keep defaults (medium font, system contrast, expanded sidebar).

### Admin users / groups audit columns

- Migration `Version20260721195000`: blame columns on `app_user` and `user_group`, plus `user_group.updated_at`.
- Admin lists/detail show AuditKit created/updated meta; historical rows may show **System** until the next update by an authenticated admin.

## Upgrading from 0.11.1 to 0.12.0

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

### Threshold alerts (`027`)

- Migration `Version20260721190000`: table `project_threshold_rule`.
- Configure under **Project → Settings → Threshold alerts**. Destinations must include category `volume.threshold`.

### Issue FULLTEXT search (`029`)

- Migration `Version20260721191000`: MySQL `FULLTEXT` index `idx_issue_title_culprit_ft` on `issue (title, culprit)`.
- SQLite / tests keep a documented `LIKE` fallback (migration is skipped).
- InnoDB typically ignores tokens shorter than `innodb_ft_min_token_size` (often **3**). BOOLEAN MODE uses `+token*` prefixes.

### Delivery history (`030`)

- Migration `Version20260721192000`: table `notification_delivery_attempt`.
- Optional env `BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT` (default **20**) bounds retained attempts per destination.
- Project Settings → Health shows expandable recent attempts (summary fields from `021` unchanged).

### Release health (`028`) / Admin project audit (`031`)

- Release health: **Project → Releases** (`/projects/{uuid}/releases`).
- Admin audit timeline: **Administration → Projects → show** (filters by action / date).

## Upgrading from 0.11.0 to 0.11.1

Pull, install, migrate, and rebuild frontend assets:

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

Migration `Version20260721180000`: table `project_share_link`.

Configure outbound mail under **Administration → Mailer** (encrypted DSN). Env `MAILER_DSN` alone does not enable magic-link sign-in.

### Viewer role (`026`)

- New project membership role **viewer** (below member): can open Issues / Performance / Analytics; cannot triage, comment, or change Settings/keys/memberships.
- Groups may be linked as viewer / member / admin (still never owner).
- Existing members are unchanged.

### Magic login

- Request a link at `/login/magic` (also linked from the password login page).
- Defaults: lifetime **600 seconds**, **max_uses: 1**, IP rate limit 5 / 15 minutes (`limiter.magic_login`).
- Disabled accounts cannot consume links.

### Share links

- Owners/admins create links under Project Settings → Share links (optional issue UUID; max 30 days; **max uses** defaults to **1**, leave empty for unlimited until expiry).
- Opening `/share/{token}` requires sign-in; grants session-scoped viewer access until the link expires (and while uses remain).
- Revoking a link invalidates future opens; raw token is shown once at creation.
- Migration `Version20260731150000` adds `max_uses` / `use_count`. Existing links stay unlimited (`max_uses` NULL) until recreated.

### View-as-member vs viewer

- Instance admin **View as member** remains a temporary session demotion to member for UX testing.
- **Viewer** is a lasting project membership (or share-link grant), not the same as view-as-member.

### Golden Envelope contract (dev / QA)

- Optional: `make check-envelope-goldens` when a sibling BeaconBundle checkout is present (Phase 3.6 fixtures).

## Upgrading from 0.10.2 to 0.11.0

Pull, install, migrate, and rebuild frontend assets:

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

Migration `Version20260721170000`: `preferred_ui_density` / `preferred_motion` on `app_user`; `danger_color` / `danger_color_dark` on `site_appearance`.

Frontend: `chart.js` is a new dependency — run `pnpm install` (or rely on the image/`make vite-build` path) before building assets.

### Appearance / display

- Account → Display: UI density (comfortable / compact) and motion (system / reduce / full).
- Administration → Appearance: danger / error color pickers for light and dark (`--beacon-alert`).
- Theme toggle for signed-in users writes `preferred_theme` via `POST /account/theme`.

### Analytics charts (`025`)

Project Analytics (`/projects/{uuid}/analytics`) now includes:

- Chart.js time series (errors; transactions / N+1 when unfiltered)
- Period presets `7` / `14` / `30` / `90` and custom `from`/`to` (`Y-m-d`, UTC days, max **366** days)
- Optional filters: `environment`, `release`, `level` (error events only; tx/N+1 hidden while filters are active)
- Shareable query string; invalid custom ranges flash a warning and fall back to 30 days
- Daily table remains (zero-filled calendar days for the selected window)

### Locales and pagination

- Enabled locales now include `de`, `nl`, `fr`, `it`, `pt` (with AuthKit / cookie-consent catalogues). Re-run `make seed` if demo menus/breadcrumbs should pick up locale labels.
- Issues, Performance, and Analytics lists share server-side `page` / `per_page` pagination.

## Upgrading from 0.10.1 to 0.10.2

No database migrations. Pull, install, and rebuild frontend assets (modal CSS / kit admin styles):

```bash
git pull
composer install
make vite-build
```

Phase 5 product features (`025`–`033`) were **specified only** at this tag — most shipped in later 0.11–0.16 releases; see [ROADMAP](ROADMAP.md).

## Upgrading from 0.10.0 to 0.10.1

No database migrations. Pull, install, and rebuild frontend assets:

```bash
git pull
composer install
make vite-build
```

Re-run demo/menu seed if the Administration sidebar is missing **Projects**:

```bash
php bin/console app:seed-demo --no-interaction
# or: make seed
```

The menu seeder now updates position/label/permission for existing items (not only missing routes).

### Issue detail UX

- Aside panels: **Triage**, **Assignee**, **Duplicate**, **Activity** (plus Details / Recent events).
- Mark-as-duplicate opens a modal with searchable canonical issue picker.
- Account → Display: new collapsible panel ids (`triage`, `duplicate`, `activity`). Clear saved browser panel states if an old layout looks wrong.

Phase 5 product work (`025-analytics-charts`, `026-magic-links-viewer`) is specified on the roadmap but **not** shipped in 0.10.1.

## Upgrading from 0.9.4 to 0.10.0

Run migrations after pull (release fields, issue workflow, governance, digests, delivery health):

```bash
git pull
composer install
php bin/console doctrine:migrations:migrate --no-interaction
make vite-build
```

Migrations include `Version20260721160000` … `Version20260721165000`.

Add to `.env` if missing (defaults match `.env.dist`):

```bash
BEACON_EVENT_QUOTA_DAILY=0
BEACON_EVENT_QUOTA_MONTHLY=0
```

### Product depth (014–022)

- **Releases**: denormalized `firstRelease` / `lastRelease` / `lastEnvironment`; filter `?release=`; compare environments.
- **Issue workflow**: priority, comments, mark-as-duplicate (optional event merge into canonical), saved views.
- **Search**: tag / URL / user filters; SQL occurrence sorts.
- **Export**: `/projects/{uuid}/export/issues|events.{csv,json}` (owner/admin).
- **Lifecycle webhooks**: opt-in categories `issue.resolved|reopened|assigned|commented|duplicated`.
- **Governance**: per-project retention/rate/daily+monthly quota in Settings; API key revoke/rotate; `BEACON_EVENT_QUOTA_DAILY` / `BEACON_EVENT_QUOTA_MONTHLY` (monthly uses UTC calendar month; empty project field inherits env; `0` = unlimited).
- **Admin ops**: project stats, suspend ingest, view-as-member.
- **Digest / quiet hours**: configure on destinations; flush with `bin/console app:notifications:flush-digests` (schedule via cron).
- **Health UI**: last delivery status on Settings / Admin project show.
- **Client**: upgrade BeaconBundle for public tags, `before_send`, and opt-in Doctrine/HttpClient spans (see [EVENT-CONTEXT](product/EVENT-CONTEXT.md) / [DSN](DSN.md)).

Re-run `make seed` if admin menu items are missing.

## Upgrading from 0.9.3 to 0.9.4

No database migrations. Pull, install, and rebuild assets:

```bash
git pull
composer install
make vite-build
```

### Administration → Projects

Instance admins get **Administration → Projects** (`/admin/projects`):

- List / search every project on the instance
- Create and edit name/description
- Manage direct members (add / change role / remove) and linked groups
- Delete a project with typed name confirmation (same semantics as project Settings danger zone)
- Open product Settings / Issues for any project — instance `ROLE_ADMIN` now resolves as effective **owner** on all projects (`ProjectAccessService`)

Re-run `make seed` (or `app:seed-demo`) to pick up the admin Projects menu item and breadcrumbs.

## Upgrading from 0.9.2 to 0.9.3

No database migrations. Pull and rebuild frontend assets (confirm-dialog Stimulus portal fix):

```bash
git pull
make vite-build
```

## Upgrading from 0.9.1 to 0.9.2

No database migrations. Pull, install, and rebuild assets:

```bash
git pull
composer install
make vite-build
```

### Transfer project ownership

Project Settings → Danger zone includes **Transfer ownership…** (owners only):

- Pick another **direct** project member as the new owner.
- Confirm by typing the project name (same pattern as delete).
- The selected member becomes **owner**; you become **admin** (you can no longer delete the project unless promoted again).
- The action is disabled until at least one other direct member exists.
- Recorded in user activity as `project.ownership_transferred`.

## Upgrading from 0.9.0 to 0.9.1

No database migrations. Pull, install, and rebuild assets:

```bash
git pull
composer install
make vite-build
```

### Admin: unlink projects from users and groups

- **Administration → Groups → (group)**: linked projects with an unlink action (same domain rules as Project Settings).
- **Administration → Users → Activity**: direct project memberships with remove (cannot remove the last project owner).
- Instance `ROLE_ADMIN` can perform these actions without being a member of the project.

Re-run `make seed` (or `app:seed-demo`) if you want demo breadcrumbs for the admin group routes.

## Upgrading from 0.8.1 to 0.9.0

### 1. Pull and migrate

```bash
git pull
composer install
make console ARGS='doctrine:migrations:migrate -n'
make vite-build
```

Migrations:

- `Version20260721080000` — password policy (`password_changed_at`, `password_history`)
- `Version20260721090000` — UserKit (`enabled`, `last_activity_at`, `updated_at` on `app_user`) + AuditKit blame/timestamp columns on `project`, `site_appearance`, `notification_destination`
- `Version20260721100000` — widen `project_api_key.secret_key` and `notification_destination.endpoint_url` for Halite ciphertext
- `Version20260721110000` — `issue_history` timeline for assignee and status changes
- `Version20260721120000` — public `uuid` columns for UI routes (Project, Issue, PerfTransaction, NotificationDestination, User)
- `Version20260721130000` — user groups (`user_group`, `user_group_membership`) and project↔group access (`project_group_access`)
- `Version20260721140000` — user activity history (`user_action`) for admin, membership, and product actions
- `Version20260721150000` — `login_attempts` table for login-throttle database storage (multi-worker)

Existing users keep working: new users default to `enabled = 1`; `password_changed_at` / `last_activity_at` null until first change / request. Plaintext secrets/URLs already in the DB remain readable until the next update (then encrypted with the `<ENC>` marker).

### Breaking: Envelope auth wire names

Ingest auth uses Beacon-native names only:

- Header: `X-Beacon-Auth: Beacon beacon_key=PUBLIC, beacon_secret=SECRET`
- Or query: `?beacon_key=…&beacon_secret=…`
- Or envelope header JSON `"dsn": "https://PUBLIC:SECRET@host/projectId"`

Upgrade the client to [`nowo-tech/beacon-bundle`](https://github.com/nowo-tech/BeaconBundle) **1.5.0+** (required DSN secret + `X-Beacon-Auth`). See [DSN.md](DSN.md).

### UI route UUIDs

Dashboard URLs now use opaque UUID path segments (e.g. `/projects/{uuid}/issues/{uuid}`). Integer primary keys stay internal. **Envelope ingest is unchanged:** `/api/{projectId}/envelope/` still uses the numeric project id from the DSN. Update bookmarks and any hard-coded UI links after migrate.

### Users and groups

- **Administration → Groups**: create groups and add existing users by email.
- **Project Settings**: add users one-to-one **or** link a group (role admin/member). Owners remain direct user memberships only.
- **Administration → Users → Activity**: timeline of admin/membership/product actions (optional client IP — see [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md)).

### Notification channels

Project Settings → Notifications now supports **Discord**, **Microsoft Teams**, **Telegram** (`bot_token@chat_id`), and **email** in addition to Slack / generic HTTP. Email requires a real `MAILER_DSN`. See [NOTIFICATIONS.md](product/NOTIFICATIONS.md).

### API docs

Authenticated admins can open **Administration → API docs** (`/admin/api/doc`). Authorize Try-it-out with `X-Beacon-Auth`.

### Migrations Kit rewrite (no new schema from the rewrite itself)

All files under `migrations/` were rewritten to the declarative MDK format of [`nowo-tech/migrations-kit-bundle`](https://packagist.org/packages/nowo-tech/migrations-kit-bundle). **Version class names are unchanged**, so already-applied installs do not re-run them.

### Issue status UI + history

Issue detail sidebar supports **Mark resolved**, **Reopen**, and **Ignore**. Assignee and status changes (including ingest reopen) are stored in `issue_history`.

### 2. Behaviour

- Changing password at `/account/security` rejects reuse of the last N hashes (`passwords_to_remember: 5`) and rejects the same as the current password.
- Expiry flash (90 days after last change) on account + dashboard routes; see `config/packages/nowo_password_policy.yaml`.
- Disabled accounts cannot log in. Admins toggle status at `/admin/users`.
- AuditKit timestamps/blame via `App\Shared\Audit\AuditableDoctrineBridge`.
- Field encryption (Halite): `ProjectApiKey.secretKey` and `NotificationDestination.endpointUrl`. Persist `var/secrets/.Halite.default.key` (or `APP_ENCRYPT_KEY`) before multi-node / prod deploy.

### 3. Verify

```bash
make test
# or
docker compose exec php vendor/bin/phpunit tests/Functional/Identity/ tests/Unit/Ingest/EnvelopeAuthParserTest.php tests/Functional/Shared/ApiDocAccessTest.php
```

Log in as admin, open `/admin/api/doc`, and confirm OpenAPI title **Symfony Beacon API**. Send a test Envelope with BeaconBundle **1.5+**.

---

## Upgrading from 0.8.0 to 0.8.1

### 1. Pull

```bash
git fetch --tags
git checkout v0.8.1
```

No database migrations. No `.env` changes.

### 2. Docs paths

Markdown under `docs/` is now **UPPERCASE** (e.g. `docs/architecture.md` → `docs/ARCHITECTURE.md`). Update bookmarks and external links.

### 3. Deploy notes

- Rebuild frontend assets so brand lockup CSS is included: `make vite-build` (or your usual asset pipeline).
- Rebuild the prod image if you use `frankenphp_prod` (Twig Inspector config is scoped to `when@dev`).
- Breadcrumb parent links on issue/transaction detail pages now resolve with the project id; no admin re-seed required.

### 4. Verify

```bash
make test
# or at least
docker compose exec php vendor/bin/phpunit tests/Functional/Shared/NowoKitsUiTest.php
```

---

## Upgrading from 0.7.2 to 0.8.0

### 1. Pull

```bash
git fetch --tags
git checkout v0.8.0
make hooks   # once per clone: enable .githooks (optional but recommended)
```

No database migrations. No `.env` changes.

### 2. Behaviour

- Envelope **ingest auth is unchanged** for clients (same HTTP header / query / DSN mechanisms).
- Internal parser keys are now `public_key` / `secret_key` (only relevant if you called `EnvelopeAuthParser` from custom code).
- Docs point at BeaconBundle as the supported PHP client ([DSN.md](DSN.md)).

### 3. Contributors

Run `make hooks` so commits cannot pick up Cursor `Co-authored-by` trailers. See [CONTRIBUTING.md](CONTRIBUTING.md).

### 4. Verify

```bash
make test
# or at least ingest tests
docker compose exec php vendor/bin/phpunit tests/Unit/Ingest tests/Functional/Ingest tests/Integration/Ingest
```

---

## Upgrading from 0.7.1 to 0.7.2

Code style only (PHP-CS-Fixer). **No schema, env, Composer, or behaviour changes.**

```bash
git fetch --tags
git checkout v0.7.2
# or pull main and deploy as usual
```

No migrations required.

---

## Upgrading from 0.7.0 to 0.7.1

Documentation and release-notes clarity only. **No schema, env, or Composer changes.**

1. Pull `v0.7.1` (or merge `main`).
2. Optional: re-read [NATIVE-MOBILE.md](dev/NATIVE-MOBILE.md) if you still expected Hotwire Native — that stack was removed in **0.7.0**.
3. Local BeaconBundle pairing remains: `make bootstrap` → demo `make sync-beacon` ([DSN.md](DSN.md)).

```bash
git fetch --tags
git checkout v0.7.1
```

No migrations required.

---

## Upgrading from 0.6.0 to 0.7.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.7.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make vite-build
```

### 2. Environment

Diff `.env` against `.env.dist` and add if missing:

| Key | Role |
|---|---|
| `BEACON_RETENTION_DAYS` | Retention purge age (`0` = off) |
| `BEACON_RETENTION_MAX_EVENTS_PER_PROJECT` | Cap events per project (`0` = off) |
| `BEACON_INGEST_RATE_LIMIT` | Per-project ingest rate (HTTP 429 when exceeded) |

### 3. Database

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

Adds `notification_destination` (and related schema from `Version20260720233000`).

### 4. Issues list behaviour

Sort and paging are **server-side** again (column header links + `per_page` in the filter form). DataTables only collapses columns on narrow viewports. Existing bookmarks with `sort` / `dir` / `page` / `per_page` keep working.

### 5. Ops / product features

- Project → Settings → **Notifications** (Slack / HTTP): [NOTIFICATIONS.md](product/NOTIFICATIONS.md)
- Optional cron: `app:retention:purge`
- Probes: `/health/live`, `/health/ready` — [PRODUCTION.md](PRODUCTION.md)
- Login throttling defaults: see `config/packages/nowo_login_throttle.yaml`

### 6. Turbo / Hotwire Native removed

`symfony/ux-turbo` and `symfony/ux-native` are gone. Use the PWA for installable mobile access ([NATIVE-MOBILE.md](dev/NATIVE-MOBILE.md)). Full page loads replace Turbo Drive navigation.

### 7. Local BeaconBundle demo (optional)

```bash
make bootstrap   # migrate + seed + write .demo-client.env
```

Then in `BeaconBundle/demo/symfony8`: `make sync-beacon` (see [DSN.md](DSN.md)).

### 8. Verify

```bash
make qa
# or
make test
curl -fsS http://localhost:9084/health/live
```

### Stack versions (0.7.0)

| Component | Constraint / image | Notes |
|---|---|---|
| PHP | `>=8.5` / `dunglas/frankenphp:1-php8.5` | Canonical image line |
| Symfony | `8.1.*` (Flex) / exact pins in `composer.json` | Application framework |
| MySQL | Compose service (see `compose.yaml`) | Default host port `3308` |
| Auth | `nowo-tech/auth-kit-bundle` | First-user registration + i18n |
| Login throttle | `nowo-tech/login-throttle-bundle` | Brute-force protection |
| Password policy | `nowo-tech/password-policy-bundle` | History + expiry on account security |
| User lifecycle | `nowo-tech/user-kit-bundle` | Enable/disable, last activity, online |
| Audit fields | `nowo-tech/audit-kit-bundle` | created/updated + blame on opt-in entities |
| Field encryption | `nowo-tech/doctrine-encrypt-bundle` | Halite at-rest encryption for secrets |
| Migrations | `nowo-tech/migrations-kit-bundle` | Declarative MDK definitions in `migrations/` |
| Cookies / legal | `nowo-tech/cookie-consent-bundle` | Consent modal + legal pages |
| Menus / breadcrumbs / forms / PWA | Nowo kit bundles | See README Features |
| Autocomplete | `symfony/ux-autocomplete` | Issue assignee field |
| Issues table | DataTables 2 + Responsive (+ jQuery) | Responsive only; sort/page server-side |
| Vite / Tailwind / SCSS | Tailwind 4, Sass, Stimulus | Assets via HTTPS `/build` proxy |

---

## Upgrading from 0.5.0 to 0.6.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.6.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install   # pulls DataTables / jQuery
make vite-build
```

No new Doctrine migrations in 0.6.0.

### 2. Frontend (required)

`pnpm install` + `make vite-build` are required: the issues index now depends on DataTables assets baked into `public/build/`.

### 3. URL / bookmark notes

Issues list query params:

| Param | Role |
|---|---|
| `q`, `level`, `status`, `assignee`, `environment` | Server-side filters (GET form) |
| `sort`, `dir` | Initial sort (server) + kept in sync by DataTables |
| `page`, `per_page` | DataTables paging (`per_page` ∈ 10/25/50/100) |

Example: `/projects/1/issues?q=demo&sort=last_seen&dir=desc&page=1&per_page=25`

### 4. Verify

```bash
make test
# /projects/{id}/issues → paging, responsive collapse, sort updates the URL
# Issue detail → Stack Trace → Copy path
```

---

## Upgrading from 0.4.0 to 0.5.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.5.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make console ARGS='doctrine:migrations:migrate -n'
make vite-build   # required when Vite dev server is not used in the deploy
```

### 2. Database migrations (required)

New columns:

| Migration | Change |
|---|---|
| `Version20260720214500` | `issue.assignee_id` (nullable FK → `app_user`) |
| `Version20260720223000` | `app_user.preferred_collapsed_issue_panels` (JSON) |

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

### 3. Behaviour notes (non-breaking for operators)

- **Fingerprints** are recalculated for new events only; existing issues keep their stored fingerprint. Similar new events may join an existing group more often (line numbers no longer dominate).
- **Assignee** is optional; members appear via `/autocomplete/project_member` (requires login).
- **Panel collapse** defaults live under Account → Display; browsers also store open/closed state in `localStorage` (`beacon.issuePanelState`).
- Stack **source context** appears when the client (e.g. BeaconBundle ≥ 1.3.0) sends `pre_context` / `context_line` / `post_context`.

### 4. Client pairing

For full stack source snippets in the UI, upgrade the PHP client to **BeaconBundle `v1.3.0+`** (or another SDK that sends frame source context).

### 5. Verify

```bash
make test
# Issue list → assign filter; open issue → assignee autocomplete + collapsible stack frames
# Account → Display → default collapsed panels
```

---

## Upgrading from 0.3.0 to 0.4.0

### 1. Pull and refresh

```bash
git fetch --tags
git checkout v0.4.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
make console ARGS='doctrine:migrations:migrate -n'
make console ARGS='assets:install public -n'
make seed   # refreshes breadcrumb/menu demos if needed
```

### 2. Database migrations (required)

New tables/columns include cookie-consent storage, dashboard menu / breadcrumb kit tables, appearance, and rich event fields (`php_version`, `symfony_version`, `user_identifier`, `DATETIME(6)` timestamps). Always run:

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

### 3. Project URLs (bookmarks)

| Before (0.3.0) | After (0.4.0) |
|---|---|
| `/projects/{id}` (API keys + members) | Redirects to **`/projects/{id}/issues`** |
| — | Settings: **`/projects/{id}/settings`** (keys, members, clear/delete) |

Update any hard-coded links that expected the old project overview page.

### 4. Docker / Envelope ingest

Caddy now serves **`/api/*` over HTTP** for `host.docker.internal` and `127.0.0.1` (in addition to redirecting browsers from `http://localhost` to HTTPS). Restart PHP after pulling so the Caddyfile reloads:

```bash
docker compose restart php
```

BeaconBundle demos should use:

```env
BEACON_DSN=http://PUBLIC_KEY:SECRET_KEY@host.docker.internal:9084/1
```

See [`DSN.md`](DSN.md).

### 5. Legal / cookies

Review public legal placeholders under `/legal/*` and cookie categories in `config/packages/nowo_cookie_consent.yaml`. Operators must replace placeholder operator text before production.

### 6. Verify

```bash
make test
# https://localhost:9447/dashboard → open a project → Issues
# Project Settings → API keys / Danger zone
# From BeaconBundle demo: http://localhost:8011/report → issue appears
```

---

## Upgrading from 0.2.0 to 0.3.0

### 1. Pull and refresh dependencies

```bash
git fetch --tags
git checkout v0.3.0   # or merge/rebase main
make down && make up
docker compose exec php composer install
docker compose exec vite pnpm install
```

Composer **require** entries are now exact versions (e.g. `1.5.1`, not `^1.5`). Prefer `composer install` from the lock file; bump pins deliberately when upgrading packages.

### 2. Routes and URLs (breaking bookmarks)

| Before (0.2.0) | After (0.3.0) |
|---|---|
| `/` (dashboard when logged in) | `/` → redirect to `/en/login`; dashboard at **`/dashboard`** |
| `/login`, `/register` (no locale) | Prefer **`/en/login`**, **`/en/register`** (bare paths redirect to `/en/…`) |
| — | Spanish: `/es/login`, `/es/register` |

Update bookmarks, reverse proxies, and any hard-coded links to use `/dashboard` and locale-prefixed auth URLs.

### 3. Auth UX packages

- Enable/install assets if needed: `make console ARGS='assets:install public -n'`.
- Registration enforces **medium** password strength (min 8, lower, upper, digit) via PasswordStrengthBundle.
- Login/register password fields include show/hide (PasswordToggleBundle) and strength feedback on register.
- Remember-me checkbox is available on login (7-day cookie). Firewall `remember_me` must remain configured (see `config/packages/security.yaml`).

### 4. i18n

- `framework.enabled_locales` and `nowo_auth_kit.enabled_locales` must stay in sync (`en`, `es`, `de`, `nl`, `fr`, `it`, `pt` by default).
- Security `access_control` patterns must include every enabled locale prefix (see [`CONTRIBUTING.md`](CONTRIBUTING.md)).
- Locale switcher is a top-right dropdown on AuthKit layouts.

### 5. Verify

```bash
make console ARGS='doctrine:migrations:migrate -n'
make test
# https://localhost:9447/ → /en/login
# https://localhost:9447/en/register (empty DB) or /dashboard when authenticated
```

No database schema migration is required for 0.3.0 auth/i18n changes.

---

## Upgrading from 0.1.0 to 0.2.0

### 1. Pull and refresh dependencies

```bash
git fetch --tags
git checkout v0.2.0   # or merge/rebase main
cp .env.dist .env.dist.upstream && diff -u .env.local .env.dist.upstream || true
# Merge any new keys from .env.dist into your .env.local (especially DEFAULT_URI / Vite notes)
make down
make up
docker compose exec php composer install
docker compose exec vite pnpm install
```

### 2. AuthKit (breaking for custom login forks)

- Login/logout route names are now `nowo_auth_kit_login` / `nowo_auth_kit_logout`.
- `form_login` uses nested fields: `login_form[_username]`, `login_form[_password]`, `login_form[_csrf_token]`.
- Custom `App\Identity\Controller\SecurityController` was removed — use AuthKit + Twig overrides under `templates/bundles/NowoAuthKitBundle/`.
- Empty databases can bootstrap via **https://localhost:9447/en/register** (first user only, `ROLE_ADMIN`). After any user exists, register redirects to login.
- Existing users continue to work; no schema migration is required for AuthKit in 0.2.0.

### 3. Frontend / Vite

- Styles entry is TypeScript + SCSS + Tailwind: `assets/app.ts` imports `styles/tailwind.css` and `styles/app.scss`.
- Caddy proxies `/build*` to the Vite container. Ensure `DEFAULT_URI` matches your public HTTPS URL (default `https://localhost:9447`).
- Rebuild/restart so PHP picks up the Caddyfile and Vite listens on **5173** inside Compose:

```bash
make down && make up
```

Hard-refresh the browser if old HTTP Vite URLs were cached.

### 4. Verify

```bash
make console ARGS='doctrine:migrations:migrate -n'
make test
# Open https://localhost:9447/en/login — Tailwind UI should load
```

---

## First install (no previous version)

If you are starting from this project for the first time:

```bash
git clone git@github.com:nowo-tech/symfony-beacon.git
cd symfony-beacon
cp .env.dist .env.local
git config core.hooksPath .githooks   # optional: strip Cursor co-author trailers
make up
make bootstrap   # migrate + seed (or: make console ARGS='doctrine:migrations:migrate -n')
```

Then either:

- Open **`/setup?token=$SITE_SETUP_TOKEN`** and create the first admin in the wizard (`admin_user` step), or
- Use `make ready` / `make seed` for the demo login: `admin@symfony-beacon.local` / `admin123`

AuthKit `/login` and `/register` stay gated until setup (or platform catalogs) are complete. Open https://localhost:9447/ (redirects to `/setup` on a cold DB). After login you land on `/dashboard`.

### Breaking expectations for consumers

This is a **self-hosted application**, not a Composer library. “Upgrading” usually means:

- **Pulling** tagged releases into your deployment clone, or
- **Rebasing / cherry-picking** upstream changes into your fork.

There is no `composer update nowo-tech/symfony-beacon` path for application code.

### Env file policy

- Only `.env.dist` is versioned.
- Do not commit `.env`, `.env.dev`, or `.env.local`.
- After pulling upstream changes, always merge new keys from `.env.dist` into your local `.env`.
