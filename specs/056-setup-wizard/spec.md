# Feature Specification: Setup Wizard UI

**Feature Branch**: `056-setup-wizard`  
**Created**: 2026-07-21  
**Status**: Implemented (custom wizard 2026-07-21–22; superseded by SiteBackupBundle `/setup` — 2026-07-30)  

**Input**: After CLI seed layers (`055`), provide a cold-start UI so empty instances can migrate, seed platform catalogs, create the first admin, and optionally load sample data. **Current implementation** uses [`nowo-tech/site-backup-bundle`](https://packagist.org/packages/nowo-tech/site-backup-bundle) at `/setup` (ops panel `/_site_backup`). Beacon owns only host chrome layouts, `AdminUserProvisioner`, catalog-empty redirect, and `SetupCompletedEvent` → `instance_settings.setup_completed_at`. Complements AuthKit first-user register; does not replace Docker/Compose install.

## User Scenarios

### US1 — Forced setup when catalogs or schema need it (P1)

As an operator on a fresh (or wiped) instance, SiteBackup detectors and/or Beacon’s platform-catalog check send HTML traffic to **`/setup`** (fixed path prefix; not locale-prefixed) while schema is empty, setup progress is incomplete, or menus / breadcrumbs / cookie consent are missing. Exclusions cover health, assets, AuthKit login/register/reset, legal, cookie-consent, locale switch, and ingest API (see `nowo_site_backup.exclusions` + `PlatformCatalogsSetupRedirectSubscriber`).

### US2 — Guided fresh_install profile (P1)

As an anonymous or admin operator on setup, I follow the SiteBackup `fresh_install` profile: requirements → bootstrap mode (guided vs full SQL) → database → migrations → `app:seed-platform` → first `ROLE_ADMIN` via `AdminUserProvisioner` (skip if an admin already exists) → optional sample (`app:seed-sample --size=dev --ensure-demo` on guided) → done marker (`var/site-backup/setup.done`). AuthKit register remains available outside the wizard for first-user signup when appropriate.

### US3 — Existing instances skip the wizard (P1)

As an operator upgrading from a prior release, I am not forced through cold-start when the schema is present, platform catalogs exist, and setup is already marked complete (`setup_completed_at` and/or `setup.done` with `require_done_marker: false` so existing installs stay usable).

### US4 — Discoverability (P2)

As an admin who has not finished setup, I see a banner on the dashboard and a card on the admin hub linking to `nowo_site_backup_setup` (`/setup`).

### US5 — Guest locale on setup (P2)

As an anonymous operator on first boot, I can switch language from the setup guest shell (session locale / `POST /locale/{locale}` with CSRF). Setup itself stays on bare `/setup` for all locales (unlike AuthKit dual public paths).

## Requirements

- **FR-001**: `instance_settings.setup_completed_at` nullable; null means setup pending from Beacon’s perspective. `SetupCompletedSubscriber` MUST mark it when SiteBackup emits `SetupCompletedEvent`.
- **FR-002**: Upgrade migration sets `setup_completed_at` on existing singleton row so prod upgrades are not interrupted by a legacy pending flag.
- **FR-003**: Cold-start UI MUST be SiteBackupBundle with `setup.path_prefix: /setup` and host `layout_template` (`kit/site_backup_setup_layout.html.twig`). Panel at `/_site_backup` with password hash from `SITE_BACKUP_PASSWORD_HASH`. No custom `SetupWizardController` / `/setup/run` / locale-prefixed setup routes.
- **FR-004**: Default profile `fresh_install` MUST run platform seed via `app:seed-platform` and create the first admin through `App\Setup\AdminUserProvisioner` (not a fixed-password demo admin from public bootstrap). Optional sample step MUST NOT invent `admin@…` / `admin123` unless using documented demo CLI (`055`). CLI `app:seed-demo` remains unchanged.
- **FR-005**: Guest shell / locale catalogues under `translations/messages.{locale}.yaml` for every enabled locale keep key parity with English. Docs in INSTALL.md and ADDING-LOCALES.md; setup is **not** on AuthKit dual public paths.
- **FR-006**: When platform catalogs are empty, `PlatformCatalogsSetupRedirectSubscriber` MUST redirect safe HTML GETs to `%nowo.site_backup.setup.path_prefix%` (after SiteBackup’s own gate). Authenticated non-admins are not redirected. Redirect Location MUST NOT embed `SITE_SETUP_TOKEN`.
- **FR-007**: Abuse controls for cold-start are owned by SiteBackupBundle (progress gate, detectors, password-protected panel). Beacon MUST NOT keep a separate `SetupWizardRateLimitSubscriber` / `limiter.setup_wizard`.
- **FR-008**: Progress storage SHOULD prefer durable chain (filesystem + Doctrine) so wiping `var/` mid-wizard does not lose the current step when DB progress exists.
- **FR-009**: `setup.setup_token` MUST be wired to `SITE_SETUP_TOKEN` (`?token=` / `X-Setup-Token`). Production MUST refuse empty token and documented local defaults for token + panel hash (`SiteBackupSecurityDefaultsGuard`); `compose.prod.yaml` MUST require both secrets.

## Out of Scope

- Replacing AuthKit register / Docker bootstrap.
- Running `huge` sample from the UI (CLI only with `--force`).
- Hand-rolled wizard Twig pages (kit layout chrome only; wizard/done/token stay in the package).
- Enterprise backup/restore product surface beyond SiteBackup panel profiles already configured.
- Aligning every legal/cookie public page to AuthKit-style bare-default dual URLs.
