# Feature Specification: Setup Wizard UI

**Feature Branch**: `056-setup-wizard`  
**Created**: 2026-07-21  
**Status**: **Completed** (product 100% — 2026-08-15). History: custom wizard → SiteBackup `/setup` → locale-in-path ≥ 1.7 → UiKit host chrome → durable done / cold-start kits **1.12** / CookieConsent **1.7** → SiteBackup **1.13** `cache_doctrine` + empty-schema cold-start + CookieConsent **1.8** mid-migration skip. Roadmap **6.49**.

**Input**: After CLI seed layers (`055`), provide a cold-start UI so empty instances can migrate, seed platform catalogs, create the first admin, and optionally load sample data.

**Current product (pins)**: [`nowo-tech/site-backup-bundle`](https://packagist.org/packages/nowo-tech/site-backup-bundle) **1.13.0** at `setup.path_prefix: /setup` (ops panel `/_site_backup`); [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle) **1.9.0** (mid-migration skip since **1.8.0**). Beacon owns host chrome layouts (including friendlier token gate Twig), `AdminUserProvisioner`, `PlatformCatalogsSetupNeedDetector` / redirect subscriber, `InstanceSettingsDurableSetupDoneStore`, and `SetupCompletedSubscriber` → `instance_settings.setup_completed_at`. Complements AuthKit after setup is complete (`registration_mode: first_user_only`); does not replace Docker/Compose install. Cold-start first admin is the wizard `admin_user` step (FR-014) — AuthKit stays gated until catalogs/setup are done.

## User Scenarios

### US1 — Forced setup when catalogs or schema need it (P1)

As an operator on a fresh (or wiped) instance, SiteBackup detectors (including Beacon’s `PlatformCatalogsSetupNeedDetector`) send HTML traffic to the **locale-aware setup prefix** while the application schema is missing/empty, setup progress is incomplete, or menus / breadcrumbs / cookie consent catalogs are missing. With Beacon’s `setup.locale.in_path: both` + `unlocalized: serve`, that is bare **`/setup`** for `DEFAULT_LOCALE` and **`/{_locale}/setup`** for other enabled locales (`SetupPathPrefixResolver` + `SetupRequestSubscriber`). SiteBackup `setup.cold_start` (with `require_application_tables: true`) keeps the gate cold until non-`nowo_site_backup_*` tables exist. Exclusions cover health, assets, legal, cookie-consent, locale switch, RoutingKit `/admin/_routing`, error previews `/_error` (dev), and ingest API (`nowo_site_backup.exclusions`). **AuthKit `/login` / `/register` / `/logout` / `/reset-password` (and localized twins) are not excluded** — they stay gated until setup is 100% done; the first admin is created in the wizard `admin_user` step. `PlatformCatalogsSetupRedirectSubscriber` keeps the FR-006 non-admin exception on top of the kit gate and must not treat `nowo_auth_kit_*` as excluded.

### US2 — Guided fresh_install profile (P1)

As an anonymous or admin operator on setup, I follow the SiteBackup `fresh_install` profile: requirements → bootstrap mode (guided vs full SQL) → optional `database_url` (Skip allowed when Docker/`.env` already has `DATABASE_URL`) → `database_create` → cache clear → migrations → `messenger:setup-transports` → `app:seed-platform` → first `ROLE_ADMIN` via `AdminUserProvisioner` (skip if an admin already exists) → optional sample (`app:seed-sample --size=dev --ensure-demo` on guided) → durable done (`instance_settings.setup_completed_at` via `SetupCompletedEvent`) plus ephemeral file marker (`var/site-backup/setup.done`). Public AuthKit register is **not** a cold-start path; finish the wizard (or `make ready` / CLI setup) before `/login` is reachable.

### US3 — Existing instances skip the wizard (P1)

As an operator upgrading from a prior release, I am not forced through cold-start when the schema is present, platform catalogs exist, and setup is already marked complete in DB (`instance_settings.setup_completed_at`). Beacon keeps `require_done_marker: false` so a missing `setup.done` file alone does not lock existing installs. Detectors (empty schema / missing catalogs / incomplete progress) still reopen setup when the instance truly needs repair.

### US4 — Discoverability (P2)

As an admin who has not finished setup, I see a banner on the dashboard and a card on the admin hub linking to `nowo_site_backup_setup` (canonical localized route; bare twin `nowo_site_backup_setup_unlocalized` when `in_path: both`).

### US5 — Guest locale on setup (P2)

As an anonymous operator on first boot, I switch language from the setup guest shell via the **path locale switcher** (same AuthKit dual-URL pattern: default locale → `*_unlocalized` / bare `/setup`; other locales → `/{_locale}/setup`). Session `POST /locale/{locale}` with CSRF remains available for non-path surfaces. Wizard forms and redirects keep the effective prefix via SiteBackup `SetupPathPrefixResolver`.

### US6 — Setup token gate UX (P2)

As an operator who opens `/setup` without `SITE_SETUP_TOKEN`, I see Beacon’s host token template (`templates.setup_token` → `kit/site_backup_setup_token.html.twig`): calm copy, how-to steps (`.env` / `?token=` / `X-Setup-Token`), and a soft-gate SVG — not a bare “forbidden” wall. Catalogues under `setup.token.*` keep key parity across enabled locales.

### US7 — Durable done survives container recreate (P1)

As an operator of an initialized instance, after recreating the PHP container (or losing ephemeral `var/site-backup/setup.done` in prod images without a bind-mount), opening `/setup` (or `/setup/api/*`, including localized prefixes) MUST redirect home when `setup_completed_at` is set and no SiteBackup detector still requires setup. SiteBackup durable-done APIs + Beacon `InstanceSettingsDurableSetupDoneStore` MUST heal the ephemeral done marker and restore progress phase to `completed` from the DB signal. Catalog/schema repair paths remain available: if detectors still require setup, the wizard stays open even when `setup_completed_at` is set.

### US8 — Progress without `var/` JSON; wipe DB restarts past-migration steps (P1)

As an operator mid-wizard, progress MUST live in Redis (`cache.app`) until Doctrine can write, then in DBAL (`cache_doctrine`) — **not** in `var/site-backup/setup-progress.json`. Dropping the MySQL database after migrations/seed steps MUST invalidate stale cache progress that claimed those steps finished, so F5 restarts the wizard instead of resuming a dead step. Early steps (requirements → `database_create`) MAY remain in cache while the schema is still empty.

### US9 — Cookie consent safe during cold-start / mid-migration (P1)

As an operator on `/setup` before CookieConsent tables exist, the guest shell MUST NOT 500 querying `dashboard_cookie_config`. CookieConsent **1.8** sets `_nowo_cookie_consent_schema_ready=false` when that table is missing (and still honors SiteBackup `_nowo_site_backup_schema_exists=false`). Consent modal returns after migrations create the table.

## Requirements

- **FR-001**: `instance_settings.setup_completed_at` nullable; null means setup pending from Beacon’s perspective. `SetupCompletedSubscriber` MUST mark it when SiteBackup emits `SetupCompletedEvent`, and MUST also call `SetupMarkerManager::markDone()` so the ephemeral file matches the DB row at finish time.
- **FR-002**: Upgrade migration sets `setup_completed_at` on existing singleton row so prod upgrades are not interrupted by a legacy pending flag.
- **FR-003**: Cold-start UI MUST be SiteBackupBundle **1.13.0** (locale-in-path since **≥ 1.7.0**; step journal since **1.11**; durable done / cold-start APIs since **1.12**; `cache_doctrine` + empty-schema cold-start since **1.13**) with `setup.path_prefix: /setup`, host `layout_template` (`kit/site_backup_setup_layout.html.twig`), and Beacon `setup.locale` aligned with AuthKit: `in_path: both`, `default: '%default_locale%'`, `enabled: '%fallback_locales%'`, `unlocalized: serve`. Panel at `/_site_backup` with password hash from `SITE_BACKUP_PASSWORD_HASH`. No custom app `SetupWizardController` / `/setup/run`. Dual URLs are owned by SiteBackup `SetupRouteLoader`. CookieConsent MUST be **≥ 1.8.0** for mid-migration schema-ready probe (builds on **1.7** cold-start attribute skip).
- **FR-004**: Default profile `fresh_install` MUST run platform seed via `app:seed-platform` and create the first admin through `App\Setup\AdminUserProvisioner`. Guided path MUST include `messenger:setup-transports` after migrations. Optional sample step MUST NOT invent fixed-password demo admins unless using documented demo CLI (`055`). CLI `app:seed-demo` remains unchanged.
- **FR-005**: Guest shell / locale catalogues under `translations/messages.{locale}.yaml` for every enabled locale keep key parity with English (including `setup.token.*`). Docs in INSTALL.md and docs/dev/ADDING-LOCALES.md.
- **FR-006**: When platform catalogs are empty, `PlatformCatalogsSetupNeedDetector` MUST report setup required (including on Doctrine/Throwable cold-start), and `PlatformCatalogsSetupRedirectSubscriber` MUST still redirect safe HTML GETs to `SetupPathPrefixResolver::resolve()` when the kit gate has not already redirected. Authenticated non-admins are not redirected by the Beacon subscriber. Redirect Location MUST NOT embed `SITE_SETUP_TOKEN`.
- **FR-007**: Abuse controls for cold-start are owned by SiteBackupBundle (progress gate, detectors, password-protected panel). Beacon MUST NOT keep a separate setup rate limiter.
- **FR-008**: Progress storage MUST use SiteBackup `progress_storage: cache_doctrine` with `progress_cache_pool: cache.app` (no host decorator; no `var/` progress JSON). `progress_step_rows: true` MUST remain enabled so per-step rows upsert into `nowo_site_backup_setup_step` via runtime DDL. Cache progress that claimed migrations/seed after the schema was wiped MUST be cleared by the kit.
- **FR-009**: `setup.setup_token` MUST be wired to `SITE_SETUP_TOKEN` (`?token=` / `X-Setup-Token`). Production MUST refuse empty token and documented local defaults (`SiteBackupSecurityDefaultsGuard`); `compose.prod.yaml` MUST require both secrets. Token-required UI MAY use Beacon `templates.setup_token` override.
- **FR-010**: Security `access_control` MUST allow bare `/setup` and `/{enabled_locale}/setup`. SiteBackup auto-excludes localized setup prefixes from restore/setup gates when locale-in-path is enabled.
- **FR-011**: Beacon MUST treat `instance_settings.setup_completed_at` as the durable “setup done” signal via `InstanceSettingsDurableSetupDoneStore` implementing `DurableSetupDoneStoreInterface`. With `setup.durable_done.enabled: true`, SiteBackup closes `/setup` (+ localized / API) when the store says done and detectors do not require setup; the kit heals `setup.done` + progress phase. Cold-start uses SiteBackup `setup.cold_start` (`require_application_tables: true`) + CookieConsent **1.8** schema-ready probe — **no** Beacon host cold-start / CookieConsent decorators.
- **FR-012**: `database_url` steps in Beacon profiles MUST be `optional: true`. SiteBackup MUST keep Skip available (and the field non-required) when the step is optional even if detectors report `database connection failed` (missing/empty schema). Beacon MUST NOT ship Twig/form overrides for that Skip behaviour.
- **FR-013**: FrankenPHP entrypoint MUST wait for the MySQL **server** only (no auto `CREATE DATABASE` / migrate / `messenger:setup-transports` on boot) so SiteBackup can own cold-start; workers MAY wait for schema. Messenger doctrine transports MUST use `auto_setup: false` so consumers do not create tables before the wizard.
- **FR-014**: While setup is required, SiteBackup exclusions and `PlatformCatalogsSetupRedirectSubscriber` MUST NOT open AuthKit (`/login`, `/register`, `/logout`, `/reset-password`, localized twins, `nowo_auth_kit_*` routes). First `ROLE_ADMIN` MUST be created via the wizard `admin_user` step (`AdminUserProvisioner`) or CLI (`nowo:site-backup:setup` / `make ready`). After setup is complete, AuthKit `registration_mode: first_user_only` remains the post-bootstrap rule.

## Progress model

### Cache + Doctrine (`cache_doctrine`)

1. **Before DBAL writes**: PSR-6 key on `cache.app` (Redis) — shared across FrankenPHP workers and CLI.
2. **When DBAL works**: singleton row `nowo_site_backup_setup_progress` + optional step journal; load prefers DB, then cache.
3. **Not used**: `var/site-backup/setup-progress.json` (`filesystem` / `chain`).

### Per-step journal (SiteBackupBundle **≥ 1.11** / kit `002-setup-step-rows`)

Table `nowo_site_backup_setup_step` (`profile`, `step_id`, `status`, `finished_at`, …) created with **runtime DDL** (not Symfony Migrations). Beacon enables it via `progress_storage: cache_doctrine` + `progress_step_rows: true`. **Beacon MUST NOT invent a parallel per-step table.**

### Durable seal

Beacon global seal = `instance_settings.setup_completed_at` (FR-001 / FR-011); file marker `var/site-backup/setup.done` is ephemeral and healed from DB.

## Success Criteria

- After a completed cold-start, recreating the PHP container without `var/site-backup/setup.done` does not reopen `/setup` when `setup_completed_at` is set and detectors do not require repair (US7 / FR-011).
- Mid-wizard progress survives wiping `var/` (no filesystem progress); Redis + DB carry state (US8 / FR-008).
- Empty schema after `database_create` keeps `/setup` rendering (200) without CookieConsent / Twig Doctrine 500s (US1 / US9).
- Optional `database_url` Skip works with missing schema (FR-012).
- Catalog/schema repair still opens the wizard even if `setup_completed_at` is set (US7).
- Pins: SiteBackup **1.13.0**, CookieConsent **≥ 1.8.0** (current **1.9.0**), `progress_storage: cache_doctrine` in `nowo_site_backup.yaml`.
- Hitting `/login` or `/register` (bare or localized) while catalogs/setup are incomplete redirects to `/setup` (FR-014).

## Out of Scope

- Replacing AuthKit register / Docker bootstrap.
- Running `huge` sample from the UI (CLI only with `--force`).
- Hand-rolled wizard Twig pages (kit layout chrome + optional token gate only; wizard/done stay in the package).
- Enterprise backup/restore product surface beyond SiteBackup panel profiles already configured.
- RoutingKitBundle managing SiteBackup/AuthKit paths; see `064-routing-kit`.
- Beacon-owned normalized setup-step history table (belongs in SiteBackupBundle).
- Pure POST-only progress without Redis/cache (kit uses PSR-6 as the pre-DB bridge).

## Amendments

### 2026-08-17 — AuthKit gated until setup completes (FR-014)

Cold-start previously excluded AuthKit paths so `/login` and `/register` could run beside an incomplete catalog. That raced the wizard `admin_user` step (two first-admin paths). Host YAML `nowo_site_backup.exclusions` no longer lists `/login` `/register` `/logout` `/reset-password`; the localized exclusion regex is `(legal|setup)` only. `PlatformCatalogsSetupRedirectSubscriber` dropped those path prefixes and the `nowo_auth_kit_*` route allow-list. First admin = wizard / CLI only until `setup_completed_at` (and catalogs) are done.
