# Tasks: AuthKit security kits

**Feature**: `105-authkit-security-kits`  
**Status**: Implemented (Unreleased / Phase 6.57)

## Phase 1: Kit pins and AuthKit profile

- [x] T001 Pin AuthKit **1.20.0** + Device Intelligence **1.1.0** + OTP Input + Slide-to-confirm **1.1.0** + FormKit **2.5.1** (later **2.5.2** in `106`)
- [x] T002 Enable `slide_to_confirm` / `device_intelligence` / `otp_input` on `nowo_auth_kit.profiles.default`; keep QR Approve as a button
- [x] T003 Add host YAML for `nowo_device_intelligence`, `nowo_otp_input`, `nowo_slide_to_confirm`; drop host `type_map.search`

## Phase 2: Device collect, cookie, ops gates

- [x] T004 Migration `Version20260824120000` (`device_intelligence_*` tables)
- [x] T005 PUBLIC_ACCESS `/_device/`; Maintenance + SiteBackup exclusions; PWA deny-cache + `cache_version` v6
- [x] T006 Inventory `di_obs` as required in cookie seed + legal cookies doc
- [x] T007 Wire `AuthKitNewDeviceLoginMailNotifier` to AuthKit `NewDeviceLoginNotifierInterface`

## Phase 3: Account trust + danger slide + OTP chrome

- [x] T008 Account → Security → Trusted browsers (`AccountTrustedDevicesController` + CSRF-only trust/revoke)
- [x] T009 `ProjectClearHistoryType` `addSlideToConfirmField()` danger profile; E2E UC-PROJ-17 slider
- [x] T010 OTP form theme + host `_otp_input.scss`; guest layout assets (slide / device / OTP) with CSP nonce collect

## Phase 4: Tests + specs

- [x] T011 PHPUnit: AuthKit bootstrap, Trusted browsers chrome, `AccountTrustedDevices`, mail notifier, danger zone, setup `/_device` exclusion
- [x] T012 Spec `105`; amend `011` / `037` / `056` / `072` / `081` / `090` / `097` / `103` / `104`; ROADMAP **6.57**; `feature.json`
