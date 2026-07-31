# Plan: Identity Kit Polish (`037`)

**Branch**: `037-authkit-identity-migration` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

## Summary

Close remaining account-chrome gaps on top of AuthKit + existing `/account/*` tabs. No auth stack rewrite. Prefer Twig partials and AuthKit APIs over new domains.

## Technical Context

| Area | Decision |
|------|----------|
| Auth owner | `nowo-tech/auth-kit-bundle` (≥ current pin) |
| Account UI | `AccountPreferencesController` + `templates/account/*` |
| Social | AuthKit social account entity/API; `create_user_if_missing: false` unchanged |
| Activity | Filter `UserAction` for current user UUID + auth allowlist |
| Guest | `templates/bundles/NowoAuthKitBundle/layout.html.twig` → `guest_shell` |

## Constitution Check

| Gate | Status |
|------|--------|
| Spec-first | Pass |
| Prefer nowo-tech kits | Pass — explicit non-goal to hand-roll Security UI |
| English UI | Pass |
| Legal/cookies | Guest shell keeps cookie consent bubble; no new tracking |

## Implementation

1. Extract/shared `templates/account/_area_nav.html.twig` (Profile / Security / Display); include from account layouts.
2. Security: linked providers section (query AuthKit social accounts for current user).
3. Security activity: repository query + Twig section or sub-route; allowlist types.
4. Audit guest reset/OTP pages; add minimal Twig overrides only if needed.
5. Expand `AccountPreferencesTest` / AuthKit tests; CHANGELOG / ROADMAP.

## Risks

- Unlink without kit support → ship read-only list.
- Over-sharing admin `UserAction` types to end users — strict allowlist.
