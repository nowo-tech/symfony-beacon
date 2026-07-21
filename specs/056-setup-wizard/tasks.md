# Tasks: Setup Wizard UI (`056`)

**Input**: `specs/056-setup-wizard/spec.md`  
**Status**: Implemented

- [x] T001 Add `instance_settings.setup_completed_at` + upgrade migration (`Version20260721200000`)
- [x] T002 Extract `DemoIdentitySeeder` for CLI + wizard reuse
- [x] T003 `SetupWizardController` bare `/setup` + `/setup/run` and localized `/{_locale}/setup` (+ run); default-locale prefix redirects to bare (`LocalizedPublicPath`)
- [x] T004 Twig UI + catalogues for enabled locales (`en`/`es`/`de`/`nl`/`fr`/`it`/`pt`); dashboard banner + admin hub card
- [x] T005 PHPUnit `SetupWizardTest` + public locale routing; docs (INSTALL / UPGRADING / CHANGELOG / ROADMAP / ADDING-LOCALES)
- [x] T006 Platform-empty HTML auto-redirect (`PlatformSetupRedirectSubscriber` + `PlatformBootstrapState`); required platform step before optional register / sample load
