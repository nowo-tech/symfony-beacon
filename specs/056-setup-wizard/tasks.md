# Tasks: Setup Wizard UI (`056`)

**Input**: `specs/056-setup-wizard/spec.md`  
**Status**: Implemented (custom wizard → SiteBackupBundle)

## Phase 1: Custom wizard (historical — superseded)

- [x] T001 Add `instance_settings.setup_completed_at` + upgrade migration (`Version20260721200000`)
- [x] T002 Extract `DemoIdentitySeeder` for CLI + wizard reuse
- [x] T003 ~~`SetupWizardController` bare `/setup` + locale dual paths~~ (removed; see Phase 2)
- [x] T004 Twig catalogues for enabled locales; dashboard banner + admin hub card (links now `nowo_site_backup_setup`)
- [x] T005 ~~`SetupWizardTest`~~ → replaced by `tests/Setup/SiteBackupSetupTest.php`; docs (INSTALL / UPGRADING / CHANGELOG / ROADMAP / ADDING-LOCALES)
- [x] T006 Platform-empty HTML auto-redirect (`PlatformBootstrapState` + subscriber; class renamed in Phase 2)
- [x] T007 Anonymous bootstrap hardening / no fixed-password demo admin from public wizard (`DemoIdentitySeeder`)
- [x] T008 ~~Setup POST rate limit (`SetupWizardRateLimitSubscriber`)~~ (removed; SiteBackup owns gate)

## Phase 2: SiteBackupBundle cold-start

- [x] T009 Require `nowo-tech/site-backup-bundle`; `config/packages/nowo_site_backup.yaml` + routes; `SITE_BACKUP_PASSWORD_HASH` in `.env.dist`
- [x] T010 Profiles `fresh_install` / `post_restore` / `full_database` / `minimal` with `app:seed-platform` + optional sample
- [x] T011 `App\Setup\AdminUserProvisioner` for `admin_user` step
- [x] T012 `SetupCompletedSubscriber` → `InstanceSettings::markSetupCompleted()` on `SetupCompletedEvent`
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
