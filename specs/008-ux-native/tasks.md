# Tasks: UX Native (Hotwire Native mobile shell)

**Feature**: `008-ux-native`  
**Updated**: 2026-08-13  
**Status**: **Deferred / Future** — tasks not scheduled  

## Phase 1: Server integration

- [ ] T001 Install `symfony/ux-native` and `symfony/ux-turbo`; enable Flex assets
- [ ] T002 Add `config/packages/ux_native.yaml`
- [ ] T003 Implement `src/Native/AppNativeConfiguration.php` (iOS + Android rules)
- [ ] T004 Allow public access to `/config/(ios|android)_v*.json` in security

## Phase 2: UI & front-end

- [ ] T005 Adapt `templates/base.html.twig` and AuthKit/guest layouts for `ux_is_native()`
- [ ] T006 Add native safe-area styles; suppress PWA install in native mode
- [ ] T007 Wire Turbo in Stimulus `controllers.json`; rebuild Vite assets
- [ ] T008 Add native bridge Stimulus controller (as needed)
- [ ] T009 Make theme/sidebar / page-loader Turbo-safe; logout `data-turbo="false"`

## Phase 3: Docs & verification

- [ ] T010 Update `docs/dev/NATIVE-MOBILE.md` and link from README / changelog
- [ ] T010a Amend constitution to allow Hotwire Native server support
- [ ] T011 Add PHPUnit coverage for config endpoints + native UA layout
- [ ] T012 Verify lint:container and native tests green

## Notes

- Do **not** start these tasks until the ROADMAP deferred item is explicitly prioritized.
- iOS/Android Hotwire Native client repositories remain out of scope (documented only).
