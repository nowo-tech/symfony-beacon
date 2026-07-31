# Tasks: Identity Kit Polish (`037`)

**Input**: `specs/037-authkit-identity-migration/spec.md`  
**Status**: Implemented

## Phase 0: Already shipped (baseline)

- [x] T001 AuthKit owns login/register/logout (no custom `SecurityController`)
- [x] T002 Account sub-tabs: profile/projects/groups; security/history; display/panels/tours/notifications
- [x] T003 Password change + password history + content-width preference
- [x] T004 Password reset OTP (`delivery: both`) + rate limit/audit subscribers
- [x] T005 Magic login + social OAuth wired (`026` / `060`); mailer gates

## Phase 1: Remaining polish

- [x] T006 Shared Account area nav (Profile \| Security \| Display) on all `/account/*` pages
- [x] T007 Security: linked social providers list (+ empty/disabled states; unlink only if kit-supported)
- [x] T008 Security activity for current user from allowlisted `UserAction` auth events
- [x] T009 Guest reset/OTP chrome audit; minimal Twig overrides only if vendor pages break guest_shell skin
- [x] T010 i18n for new keys + PHPUnit regressions (nav, social list, activity, existing prefs)
- [x] T011 CHANGELOG / ROADMAP mark 037 Done when Phase 1 complete
