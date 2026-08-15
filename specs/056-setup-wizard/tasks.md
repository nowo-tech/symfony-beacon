# Tasks: Setup Wizard UI (`056`)

**Input**: `specs/056-setup-wizard/spec.md`  
**Status**: **Completed** (product 100% — SiteBackup **1.13.0** + CookieConsent **1.8.0**; Beacon host store / provisioner / catalog detectors only)

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

## Phase 4: DB-durable done gate (historical host — superseded by Phase 5)

- [x] T022 ~~`SetupDbDoneGuard`~~ → SiteBackup **1.12** `SetupDbDoneGuard` + host `DurableSetupDoneStoreInterface`
- [x] T023 ~~`SetupDbDoneRedirectSubscriber`~~ → SiteBackup **1.12** durable-done redirect subscriber
- [x] T024 Unit/functional coverage moved to kit + Beacon `InstanceSettingsDurableSetupDoneStoreTest` / `SetupCompletedSubscriberTest` / `SiteBackupSetupTest`
- [x] T025 Update `056` spec (US7, FR-001/003/008/011, progress model, success criteria)
- [x] T026 Sync ROADMAP **6.49**, INSTALL / UPGRADING / CHANGELOG Unreleased

## Phase 5: Kits own durable done + cold-start (2026-08-15)

- [x] T027 Pin `nowo-tech/site-backup-bundle` **1.12.0** + `nowo-tech/cookie-consent-bundle` **1.7.0**
- [x] T028 `InstanceSettingsDurableSetupDoneStore` + `config/services/site_backup.yaml` alias; enable `setup.durable_done` / `setup.cold_start`
- [x] T029 Remove host `SetupDbDoneGuard` / redirect subscriber / cold-start gate / CookieConsent decorators
- [x] T030 `SetupCompletedSubscriber` uses `DurableSetupDoneStoreInterface` + `SetupMarkerManager::markDone()`
- [x] T031 Spec/docs sync (FR-011, ROADMAP 6.49, CHANGELOG / UPGRADING)

## Phase 6: SiteBackup 1.13 + CookieConsent 1.8 — product complete (2026-08-15)

- [x] T032 Upstream SiteBackup **1.13.0**: empty-schema cold-start (`require_application_tables`), `progress_storage: cache|cache_doctrine`, optional `database_url` Skip
- [x] T033 Upstream CookieConsent **1.8.0**: `CookieConsentSchemaReadySubscriber` + `CookieConsentConfig::TABLE_NAME`
- [x] T034 Beacon pin **1.13.0** / **1.8.0**; `progress_storage: cache_doctrine` + `progress_cache_pool: cache.app`
- [x] T035 Remove temporary Beacon host workarounds (Redis progress decorators, ApplicationTables checker, CookieConsent schema subscriber, DatabaseUrl form/Twig overrides)
- [x] T036 Profiles: `database_url` `optional: true`; `messenger:setup-transports` after migrations; entrypoint / messenger `auto_setup: false` (FR-012 / FR-013)
- [x] T037 Spec 100% product sync (US8–US9, FR-003/008/011–013, progress model, success criteria, ROADMAP **6.49**)

## Upstream SiteBackupBundle / CookieConsentBundle (adopted)

- [x] U001 Normalized setup-step table — SiteBackup **≥ 1.11.0** / kit `002-setup-step-rows` (runtime DDL)
- [x] U002 Beacon adopts progress via **`cache_doctrine`** on SiteBackup **1.13.0** (no `var/` JSON; no host Redis storage class)
- [x] U003 Durable done + cold-start APIs — SiteBackup **1.12.0**; empty-schema + Skip fixes — **1.13.0**
- [x] U004 CookieConsent cold-start attributes — **1.7.0**; mid-migration table probe — **1.8.0**
