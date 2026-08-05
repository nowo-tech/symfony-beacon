# Feature Specification: Setup Wizard UI

**Feature Branch**: `056-setup-wizard`  
**Created**: 2026-07-21  
**Status**: Implemented (custom wizard 2026-07-21–22; SiteBackupBundle `/setup` — 2026-07-30; **locale-in-path SiteBackup ≥ 1.7.0** — 2026-07-31; host layouts Tailwind + UiKit tokens / pin **1.10.0** — `081-formkit-uikit-kit-sync` — 2026-08-05)  

**Input**: After CLI seed layers (`055`), provide a cold-start UI so empty instances can migrate, seed platform catalogs, create the first admin, and optionally load sample data. **Current implementation** uses [`nowo-tech/site-backup-bundle`](https://packagist.org/packages/nowo-tech/site-backup-bundle) **≥ 1.7.0** at `setup.path_prefix: /setup` (ops panel `/_site_backup`). Beacon owns host chrome layouts (including a friendlier token gate Twig), `AdminUserProvisioner`, catalog-empty redirect, and `SetupCompletedEvent` → `instance_settings.setup_completed_at`. Complements AuthKit first-user register; does not replace Docker/Compose install.

## User Scenarios

### US1 — Forced setup when catalogs or schema need it (P1)

As an operator on a fresh (or wiped) instance, SiteBackup detectors (including Beacon’s `PlatformCatalogsSetupNeedDetector`) send HTML traffic to the **locale-aware setup prefix** while schema is empty, setup progress is incomplete, or menus / breadcrumbs / cookie consent are missing. With Beacon’s `setup.locale.in_path: both` + `unlocalized: serve`, that is bare **`/setup`** for `DEFAULT_LOCALE` and **`/{_locale}/setup`** for other enabled locales (SiteBackup `SetupPathPrefixResolver` + `SetupRequestSubscriber`). Exclusions cover health, assets, AuthKit login/register/reset, legal, cookie-consent, locale switch, RoutingKit `/admin/_routing`, error previews `/_error` (dev), and ingest API (see `nowo_site_backup.exclusions`). `PlatformCatalogsSetupRedirectSubscriber` keeps the FR-006 non-admin exception on top of the kit gate.

### US2 — Guided fresh_install profile (P1)

As an anonymous or admin operator on setup, I follow the SiteBackup `fresh_install` profile: requirements → bootstrap mode (guided vs full SQL) → database → migrations → `app:seed-platform` → first `ROLE_ADMIN` via `AdminUserProvisioner` (skip if an admin already exists) → optional sample (`app:seed-sample --size=dev --ensure-demo` on guided) → done marker (`var/site-backup/setup.done`). AuthKit register remains available outside the wizard for first-user signup when appropriate.

### US3 — Existing instances skip the wizard (P1)

As an operator upgrading from a prior release, I am not forced through cold-start when the schema is present, platform catalogs exist, and setup is already marked complete (`setup_completed_at` and/or `setup.done` with `require_done_marker: false` so existing installs stay usable).

### US4 — Discoverability (P2)

As an admin who has not finished setup, I see a banner on the dashboard and a card on the admin hub linking to `nowo_site_backup_setup` (canonical localized route; bare twin `nowo_site_backup_setup_unlocalized` when `in_path: both`).

### US5 — Guest locale on setup (P2)

As an anonymous operator on first boot, I switch language from the setup guest shell via the **path locale switcher** (same AuthKit dual-URL pattern: default locale → `*_unlocalized` / bare `/setup`; other locales → `/{_locale}/setup`). Session `POST /locale/{locale}` with CSRF remains available for non-path surfaces. Wizard forms and redirects keep the effective prefix via SiteBackup `SetupPathPrefixResolver`.

### US6 — Setup token gate UX (P2)

As an operator who opens `/setup` without `SITE_SETUP_TOKEN`, I see Beacon’s host token template (`templates.setup_token` → `kit/site_backup_setup_token.html.twig`): calm copy, how-to steps (`.env` / `?token=` / `X-Setup-Token`), and a soft-gate SVG — not a bare “forbidden” wall. Catalogues under `setup.token.*` keep key parity across enabled locales.

## Requirements

- **FR-001**: `instance_settings.setup_completed_at` nullable; null means setup pending from Beacon’s perspective. `SetupCompletedSubscriber` MUST mark it when SiteBackup emits `SetupCompletedEvent`.
- **FR-002**: Upgrade migration sets `setup_completed_at` on existing singleton row so prod upgrades are not interrupted by a legacy pending flag.
- **FR-003**: Cold-start UI MUST be SiteBackupBundle **≥ 1.7.0** with `setup.path_prefix: /setup`, host `layout_template` (`kit/site_backup_setup_layout.html.twig`), and Beacon `setup.locale` aligned with AuthKit: `in_path: both`, `default: '%default_locale%'`, `enabled: '%fallback_locales%'`, `unlocalized: serve`. Panel at `/_site_backup` with password hash from `SITE_BACKUP_PASSWORD_HASH`. No custom app `SetupWizardController` / `/setup/run`. Dual URLs are owned by SiteBackup `SetupRouteLoader` (canonical `nowo_site_backup_setup*` + `*_unlocalized` when `both`); Beacon MUST NOT maintain a parallel `setup_locale.yaml` / pathPrefix factory.
- **FR-004**: Default profile `fresh_install` MUST run platform seed via `app:seed-platform` and create the first admin through `App\Setup\AdminUserProvisioner` (not a fixed-password demo admin from public bootstrap). Optional sample step MUST NOT invent `admin@…` / `admin123` unless using documented demo CLI (`055`). CLI `app:seed-demo` remains unchanged.
- **FR-005**: Guest shell / locale catalogues under `translations/messages.{locale}.yaml` for every enabled locale keep key parity with English (including `setup.token.*`). Docs in INSTALL.md and docs/dev/ADDING-LOCALES.md.
- **FR-006**: When platform catalogs are empty, `PlatformCatalogsSetupNeedDetector` MUST report setup required to SiteBackup’s gate, and `PlatformCatalogsSetupRedirectSubscriber` MUST still redirect safe HTML GETs to `SetupPathPrefixResolver::resolve()` when the kit gate has not already redirected (locale-aware). Authenticated non-admins are not redirected by the Beacon subscriber. Redirect Location MUST NOT embed `SITE_SETUP_TOKEN`.
- **FR-007**: Abuse controls for cold-start are owned by SiteBackupBundle (progress gate, detectors, password-protected panel). Beacon MUST NOT keep a separate `SetupWizardRateLimitSubscriber` / `limiter.setup_wizard`.
- **FR-008**: Progress storage SHOULD prefer durable chain (filesystem + Doctrine) so wiping `var/` mid-wizard does not lose the current step when DB progress exists.
- **FR-009**: `setup.setup_token` MUST be wired to `SITE_SETUP_TOKEN` (`?token=` / `X-Setup-Token`). Production MUST refuse empty token and documented local defaults for token + panel hash (`SiteBackupSecurityDefaultsGuard`); `compose.prod.yaml` MUST require both secrets. Token-required UI MAY use Beacon `templates.setup_token` override.
- **FR-010**: Security `access_control` MUST allow bare `/setup` and `/{enabled_locale}/setup`. SiteBackup auto-excludes localized setup prefixes from restore/setup gates when locale-in-path is enabled.

## Out of Scope

- Replacing AuthKit register / Docker bootstrap.
- Running `huge` sample from the UI (CLI only with `--force`).
- Hand-rolled wizard Twig pages (kit layout chrome + optional token gate only; wizard/done stay in the package).
- Enterprise backup/restore product surface beyond SiteBackup panel profiles already configured.
- RoutingKitBundle managing SiteBackup/AuthKit paths (those kits keep their own locale config); see `064-routing-kit`.
