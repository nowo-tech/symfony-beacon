# Tasks: Setup Wizard UI (`056`)

**Input**: `specs/056-setup-wizard/spec.md`  
**Status**: Implemented (custom wizard → SiteBackupBundle; DB-durable done gate 2026-08-15)

## Phase 1: Custom wizard (historical — superseded)

- [x] T001 Add `instance_settings.setup_completed_at` + upgrade migration (`Version20260721200000`)
- [x] T002 Extract `DemoIdentitySeeder` for CLI + wizard reuse
- [x] T003 ~~`SetupWizardController` bare `/setup` + locale dual paths~~ (removed; see Phase 2)
- [x] T004 Twig catalogues for enabled locales; dashboard banner + admin hub card (links now `nowo_site_backup_setup`)
- [x] T005 ~~`SetupWizardTest`~~ → replaced by `tests/Functional/Setup/SiteBackupSetupTest.php`; docs (INSTALL / UPGRADING / CHANGELOG / ROADMAP / ADDING-LOCALES)
- [x] T006 Platform-empty HTML auto-redirect (`PlatformBootstrapState` + subscriber; class renamed in Phase 2)
- [x] T007 Anonymous bootstrap hardening / no fixed-password demo admin from public wizard (`DemoIdentitySeeder`)
- [x] T008 ~~Setup POST rate limit (`SetupWizardRateLimitSubscriber`)~~ (removed; SiteBackup owns gate)

## Phase 2: SiteBackupBundle cold-start

- [x] T009 Require `nowo-tech/site-backup-bundle`; `config/packages/nowo_site_backup.yaml` + routes; `SITE_BACKUP_PASSWORD_HASH` in `.env.dist`
- [x] T010 Profiles `fresh_install` / `post_restore` / `full_database` / `minimal` with `app:seed-platform` + optional sample
- [x] T011 `App\Setup\AdminUserProvisioner` for `admin_user` step
- [x] T012 `SetupCompletedSubscriber` → `InstanceSettings::markSetupCompleted()` on `SetupCompletedEvent` (+ `SetupMarkerManager::markDone()` heal)
- [x] T013 `PlatformCatalogsSetupRedirectSubscriber` (replace `PlatformSetupRedirectSubscriber`); remove `SetupWizardController` / `SetupWizardAccess` / `LocalizedPublicPath` / custom rate limiter / `templates/settings/setup.html.twig`
- [x] T014 Host layouts `kit/site_backup_setup_layout.html.twig` + `kit/site_backup_panel_layout.html.twig`
- [x] T015 `SiteBackupSetupTest` + INSTALL/CHANGELOG/ROADMAP sync
- [x] T016 Harden setup tests (no 404; home→`/setup`; login/health exempt; `SetupCompletedEvent`; catalog check on in test) + mark SiteBackup required when catalogs empty (avoid `/setup`→`/` loop)
- [x] T017 Wire `SITE_SETUP_TOKEN` → `setup.setup_token`; prod guard against local panel hash / local token; compose.prod requires both; tests use `test-setup-token`

## Phase 3: SiteBackup locale-in-path (kit ≥ 1.7.0)

- [x] T018 Require `nowo-tech/site-backup-bundle:1.7.0`; configure `setup.locale` (`both` + `serve`) matching AuthKit
- [x] T019 Remove Beacon dual-route patch (`setup_locale.yaml`, pathPrefix factory/compiler pass, locale gate subscribers)
- [x] T020 Locale switcher uses setup `*_unlocalized` twins; `PlatformCatalogsSetupRedirectSubscriber` uses kit `SetupPathPrefixResolver`
- [x] T021 Friendlier token gate (`templates.setup_token` + `setup.token.*` catalogues); update `056` spec / ADDING-LOCALES / CONTRIBUTING

## Phase 4: DB-durable done gate (Beacon)

- [x] T022 `SetupDbDoneGuard` — `setup_completed_at` + `SetupNeedEvaluator`; heal `setup.done` + progress phase `completed` when closing
- [x] T023 `SetupDbDoneRedirectSubscriber` — redirect `/setup`, localized `/setup`, `/setup/api/*` to `/` when guard closes wizard; leave `/setup/done` and catalog/schema repair paths open
- [x] T024 Unit tests (`SetupDbDoneGuardTest`, `SetupDbDoneRedirectSubscriberTest`, updated `SetupCompletedSubscriberTest`) + functional cases in `SiteBackupSetupTest` (DB done without file; repair when catalogs empty)
- [x] T025 Update `056` spec (US7, FR-001/003/008/011, progress model, success criteria)
- [x] T026 Sync ROADMAP **6.49**, INSTALL / UPGRADING / CHANGELOG Unreleased for SiteBackup 1.11 + DB-durable done gate

## Upstream SiteBackupBundle (kit `002-setup-step-rows` — adopted)

- [x] U001 Normalized setup-step table (`step_id` + `finished_at` / status per profile) — implemented in SiteBackupBundle **v1.11.0** / `specs/002-setup-step-rows` (runtime DDL; filesystem until DB exists)
- [x] U002 Beacon adopts U001 via `nowo-tech/site-backup-bundle` **1.11.0** + `progress_storage: chain` + `progress_step_rows: true` (no parallel Beacon step table)
