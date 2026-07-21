# Feature Specification: Identity/Project

**Feature Branch**: `002-identity-project`  
**Created**: 2026-07-19  
**Status**: Completed (as-built; dashboard create modal, group-link policy, CSRF on API keys — 2026-07-21)  

## Summary

Identity uses [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) for login, first-user registration, remember-me, and locale-prefixed routes. Password UX uses PasswordToggle + PasswordStrength on AuthKit forms. Account enable/disable uses [`nowo-tech/user-kit-bundle`](https://packagist.org/packages/nowo-tech/user-kit-bundle) with **session invalidation when an account is disabled**. Projects, memberships (`owner` / `admin` / `member`), and API keys live under `src/Project`. Account preferences and admin appearance live under Identity.

Prefer AuthKit / UserKit / AuditKit over hand-rolled auth CRUD when extending this area (see workspace nowo-tech kit guidance).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Auth and first admin (Priority: P1)

As an operator, I bootstrap the first admin via registration, then login with remember-me.

**Acceptance Scenarios**:

1. **Given** anonymous `/`, **When** requested, **Then** redirect to `/en/login`.
2. **Given** empty DB, **When** `/en/register` succeeds, **Then** the first user is `ROLE_ADMIN` and further registration is closed (`first_user_only`).
3. **Given** login, **When** remember-me is used, **Then** the session persists per AuthKit config.
4. **Given** locales `en` / `es`, **When** path prefix or AuthKit locale dropdown is used, **Then** UI locale switches.
5. **Given** an enabled user with an active session, **When** an admin disables the account, **Then** subsequent requests using that session are rejected (UserKit `invalidate_sessions_on_disable: true`).

### User Story 2 - Projects, keys, members (Priority: P1)

As an authenticated user, I manage projects from `/dashboard`, open Issues as the project home, and configure keys/members under Settings.

**Acceptance Scenarios**:

1. **Given** the dashboard, **When** I create a project, **Then** creation is a **modal** next to the project search (not a sidebar nav item); `GET /projects/new` redirects to `/dashboard?new=1` and opens the modal.
2. **Given** I create a project, **When** I open `/projects/{id}`, **Then** I am redirected to Issues.
3. **Given** I open `/projects/{uuid}/settings`, **Then** I can manage API keys (create POST requires **CSRF**), **direct members** (add by email with roles), **linked groups** (admin/member), and DSN helpers that include **public and secret** key material.
4. **Given** membership roles owner/admin/member (direct or via group), **When** a non-member opens a project URL, **Then** access is denied (403); the dashboard lists only accessible projects.
5. **Given** a project admin who is **not** a member of group G, **When** they try to link G to the project, **Then** the action is denied; owners and instance `ROLE_ADMIN` may link any group; project admins may only link groups they belong to.

### User Story 3 - Account and admin surfaces (Priority: P2)

As a user, I update profile/security/display preferences; as admin, I reach Appearance.

**Acceptance Scenarios**:

1. **Given** `/account/profile`, `/account/security`, `/account/display`, **When** I save Display prefs, **Then** preferred collapsed issue panels are stored on the user (`preferredCollapsedIssuePanels` / `IssuePanelIds`).
2. **Given** `ROLE_ADMIN`, **When** I open `/admin` and `/settings/appearance`, **Then** admin hubs load.
3. **Given** public legal/cookie surfaces, **When** non-essential cookies apply, **Then** cookie consent and legal pages remain available (see `docs/LEGAL-AND-COOKIES.md`).

## Requirements *(mandatory)*

- **FR-001**: AuthKit owns login/register/password UX; app does not maintain a parallel SecurityController login.
- **FR-002**: Project Settings is the management surface; project show routes to Issues; owners/admins add/remove **direct** members and assign roles (`owner` / `admin` / `member`); the last owner cannot be removed or demoted.
- **FR-003**: API keys support labels and safe public identifiers for operators; creating a key MUST validate CSRF; DSNs MUST include secret when present (`https://public:secret@host/projectId`).
- **FR-004**: Account Display preferences include default collapsed issue panels.
- **FR-005**: Kits may include dashboard-menu, breadcrumb-kit, form-kit, cookie-consent, PWA (as configured in the app).
- **FR-006**: Project data is membership-scoped: dashboard lists only accessible projects; controllers enforce `ProjectAccessService` (direct membership **or** linked group).
- **FR-007**: Admins manage **user groups**; projects may link groups with `admin`/`member` role so all group users gain access. Owner role is direct-user only. Linking policy: instance admin or project **owner** may link any group; project **admin** only groups they belong to.
- **FR-008**: New project UX is dashboard-modal (search row), not Dashboard sidebar menu.
- **FR-009**: Disabling a user account MUST invalidate existing sessions (`nowo_user_kit` account_status).

## Success Criteria

- **SC-001**: First-boot registration + login + project membership flows are covered by tests.
- **SC-002**: Operators can copy a DSN (with secret) and manage keys without leaving Settings.
- **SC-003**: Dashboard create-project modal and group-link restrictions are covered by functional tests.

See product README, [`docs/CONTRIBUTING.md`](../../docs/CONTRIBUTING.md), and constitution.
