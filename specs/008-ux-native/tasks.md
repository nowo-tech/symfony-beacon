# Tasks: UX Native (Hotwire Native mobile shell)

**Feature**: `008-ux-native`  
**Status**: Completed  

## Phase 1: Server integration

- [x] T001 Install `symfony/ux-native` and `symfony/ux-turbo`; enable Flex assets
- [x] T002 Add `config/packages/ux_native.yaml`
- [x] T003 Implement `src/Native/AppNativeConfiguration.php` (iOS + Android rules)
- [x] T004 Allow public access to `/config/(ios|android)_v*.json` in security

## Phase 2: UI & front-end

- [x] T005 Adapt `templates/base.html.twig` and AuthKit layout for `ux_is_native()`
- [x] T006 Add native safe-area styles; suppress PWA install in native mode
- [x] T007 Wire Turbo in Stimulus `controllers.json`; rebuild Vite assets
- [x] T008 Add `beacon-theme-bridge` Stimulus bridge controller
- [x] T009 Make theme/sidebar init Turbo-safe

## Phase 3: Docs & verification

- [x] T010 Write `docs/NATIVE-MOBILE.md` and link from README / changelog
- [x] T011 Add PHPUnit coverage for config endpoints + native UA layout
- [x] T012 Verify lint:container and native tests green

## Notes

iOS/Android Hotwire Native client repositories remain out of scope (documented only).
