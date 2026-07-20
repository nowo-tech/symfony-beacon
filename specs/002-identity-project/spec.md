# Feature Specification: Identity/Project

**Feature Branch**: `002-identity-project`  
**Created**: 2026-07-19  
**Status**: Completed (as-built through v0.6.0)  

## Summary

Identity uses [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) for login, first-user registration, remember-me, and locale-prefixed routes. Password UX uses PasswordToggle + PasswordStrength on AuthKit forms. Projects, memberships (`owner` / `admin` / `member`), and API keys live under `src/Project`. Account preferences and admin appearance live under Identity.

Prefer AuthKit / UserKit / AuditKit over hand-rolled auth CRUD when extending this area (see workspace nowo-tech kit guidance).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Auth and first admin (Priority: P1)

As an operator, I bootstrap the first admin via registration, then login with remember-me.

**Acceptance Scenarios**:

1. **Given** anonymous `/`, **When** requested, **Then** redirect to `/en/login`.
2. **Given** empty DB, **When** `/en/register` succeeds, **Then** the first user is `ROLE_ADMIN` and further registration is closed (`first_user_only`).
3. **Given** login, **When** remember-me is used, **Then** the session persists per AuthKit config.
4. **Given** locales `en` / `es`, **When** path prefix or AuthKit locale dropdown is used, **Then** UI locale switches.

### User Story 2 - Projects, keys, members (Priority: P1)

As an authenticated user, I manage projects from `/dashboard`, open Issues as the project home, and configure keys/members under Settings.

**Acceptance Scenarios**:

1. **Given** I create a project, **When** I open `/projects/{id}`, **Then** I am redirected to Issues.
2. **Given** I open `/projects/{id}/settings`, **Then** I can manage API keys (human-friendly labels / public key display, suggest name), memberships, and DSN copy helpers.
3. **Given** membership roles owner/admin/member, **When** permissions are checked, **Then** PHPUnit under `tests/` enforces access rules.

### User Story 3 - Account and admin surfaces (Priority: P2)

As a user, I update profile/security/display preferences; as admin, I reach Appearance.

**Acceptance Scenarios**:

1. **Given** `/account/profile`, `/account/security`, `/account/display`, **When** I save Display prefs, **Then** preferred collapsed issue panels are stored on the user (`preferredCollapsedIssuePanels` / `IssuePanelIds`).
2. **Given** `ROLE_ADMIN`, **When** I open `/admin` and `/settings/appearance`, **Then** admin hubs load.
3. **Given** public legal/cookie surfaces, **When** non-essential cookies apply, **Then** cookie consent and legal pages remain available (see `docs/legal-and-cookies.md`).

## Requirements *(mandatory)*

- **FR-001**: AuthKit owns login/register/password UX; app does not maintain a parallel SecurityController login.
- **FR-002**: Project Settings is the management surface; project show routes to Issues.
- **FR-003**: API keys support labels and safe public identifiers for operators.
- **FR-004**: Account Display preferences include default collapsed issue panels.
- **FR-005**: Kits may include dashboard-menu, breadcrumb-kit, form-kit, cookie-consent, PWA (as configured in the app).

## Success Criteria

- **SC-001**: First-boot registration + login + project membership flows are covered by tests.
- **SC-002**: Operators can copy a DSN and manage keys without leaving Settings.

See product README, [`docs/CONTRIBUTING.md`](../../docs/CONTRIBUTING.md), and constitution.
