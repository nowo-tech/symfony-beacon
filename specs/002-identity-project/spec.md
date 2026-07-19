# Feature Specification: Identity/Project

**Feature Branch**: `002-identity-project`  
**Created**: 2026-07-19  
**Status**: Completed  

## Summary

Identity uses [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) for login, first-user registration, remember-me, and locale-prefixed routes. Password UX uses PasswordToggle + PasswordStrength on AuthKit forms. Projects, memberships, and API keys remain app modules under `src/Project`.

## Acceptance (high level)

1. Anonymous `/` redirects to `/en/login`; authenticated home is `/dashboard`.
2. Empty database: `/en/register` creates the first `ROLE_ADMIN` user, then closes registration (`first_user_only`).
3. Login supports remember-me; passwords can be shown/hidden; registration enforces medium strength.
4. Locales `en` / `es` are available via path prefix and the AuthKit locale dropdown.
5. Project access rules covered by PHPUnit under `tests/Identity` and related suites.

See product README, [`docs/CONTRIBUTING.md`](../../docs/CONTRIBUTING.md) (i18n), and constitution.
