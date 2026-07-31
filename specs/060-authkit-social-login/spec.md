# Feature Specification: AuthKit Social Login

**Feature Branch**: `060-authkit-social-login`  
**Created**: 2026-07-30  
**Status**: Implemented (OAuth + seed in v0.13.0; **admin CRUD** replaces env seeder in v0.17.0)  

**Input**: Enable AuthKit OAuth social login on Beacon using [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) **≥ 1.9** so operators can “Continue with …” Google / GitHub / Microsoft (or custom). Provider **client id/secret** and linked-user tokens are stored in Doctrine tables owned by AuthKit. Prefer kit integration over a bespoke OAuth stack.

**Related**: Magic login / password reset remain Mailer-gated (`034`, `026`). Full enterprise **SSO/SAML/OIDC** stays roadmap Later (not the same as social OAuth buttons).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Opt-in social buttons on login (Priority: P1)

As a guest, I see social login buttons on the AuthKit login page only when the profile has `social_login.mode: enabled` **and** at least one **enabled** credential row exists in `auth_kit_social_credential`.

**Independent Test**: With mode enabled and no DB credentials, login shows no social section; after inserting an enabled Google credential, a Continue-with button appears.

**Acceptance Scenarios**:

1. **Given** `social_login.mode: disabled`, **When** I open login, **Then** no social buttons are shown.
2. **Given** mode enabled but zero enabled credentials, **When** I open login, **Then** no social buttons are shown.
3. **Given** mode enabled and ≥1 enabled credential, **When** I open login, **Then** each provider shows a Continue-with link to `/login/social/{provider}` (and locale-prefixed variants).

### User Story 2 - OAuth round-trip links existing users (Priority: P1)

As a user with an existing Beacon account whose email matches the provider profile, I complete OAuth and land authenticated on the dashboard without creating a second local user.

**Independent Test**: Seed a user email; mock or use a test IdP credential; complete check callback; assert session + `auth_kit_social_account` row.

**Acceptance Scenarios**:

1. **Given** an existing user email returned by the IdP, **When** OAuth completes, **Then** I am logged in as that user and a social account row stores provider subject + tokens.
2. **Given** `create_user_if_missing: false` (Beacon default), **When** the IdP email matches no local user, **Then** login fails closed (redirect to login with error; no ROLE_ADMIN auto-create).
3. **Given** public `access_control`, **When** I hit start/check routes (bare and locale-prefixed), **Then** they are `PUBLIC_ACCESS`.

### User Story 3 - Admin manages credentials in UI (Priority: P1)

As an instance admin, I create, edit, enable/disable, and delete OAuth provider credentials under **Administration → Social login** without env bootstrap or a CLI seeder.

**Independent Test**: As `ROLE_ADMIN`, open `/admin/social-login`, add an enabled Google credential, assert login shows the Continue-with button; disable or delete → button gone.

**Acceptance Scenarios**:

1. **Given** I am an instance admin, **When** I save a provider with client id/secret enabled, **Then** the credential is stored in `auth_kit_social_credential` and buttons appear when AuthKit mode is enabled.
2. **Given** an existing credential, **When** I disable or delete it, **Then** that provider no longer appears on login.
3. **Given** a non-admin, **When** I open the social login admin routes, **Then** access is denied (403).

## Requirements *(mandatory)*

- **FR-001**: Depend on AuthKit with `social_login` support (Packagist ≥ 1.9.1); configure profile `mode` + `create_user_if_missing`.
- **FR-002**: Schema MUST include AuthKit tables `auth_kit_social_credential` and `auth_kit_social_account` (Doctrine migration).
- **FR-003**: Security `access_control` MUST allow `/login/social` (bare + locale prefixes).
- **FR-004**: Login Twig override MUST render social buttons using AuthKit variables (`social_login_enabled`, `social_login_providers`, `social_login_route`).
- **FR-005**: Beacon MUST default `create_user_if_missing: false` while `registration_role` is `ROLE_ADMIN` / `first_user_only`, to avoid open admin signup via OAuth.
- **FR-006**: Provide Administration → Social login CRUD for `auth_kit_social_credential` (`ROLE_ADMIN`); do not rely on `app:seed-social-login` / `AUTH_KIT_SOCIAL_*` env bootstrap (removed in v0.17.0).
- **FR-007**: Password-policy excluded routes and locale switcher MUST include social login route names.
- **FR-008**: Privacy/legal: document third-party IdP use; cookie consent when non-essential third-party scripts are added later (OAuth redirect itself is not a Beacon tracking cookie).

## Key Entities

- **SocialLoginCredential** (AuthKit): provider key, label, client id/secret, enabled, optional custom URLs/scopes.
- **SocialLoginAccount** (AuthKit): provider subject, local user class/id, tokens, email, raw profile.

## Success Criteria

- **SC-001**: Login shows social affordances only when mode + enabled DB credentials exist.
- **SC-002**: OAuth start/check routes are public and locale-aware.
- **SC-003**: With `create_user_if_missing: false`, unknown emails do not create users.
- **SC-004**: Admins can manage provider credentials via the Social login admin UI without SQL or env seeders.

## Assumptions

- Built-in endpoint catalogs for `google`, `github`, `microsoft`; custom providers need URLs on the credential.
- Redirect URI registered at the IdP matches absolute check URL including locale when used.
- QR phone login (`AuthKit` design doc) is **not** part of this feature.

## Out of Scope

- Enterprise SSO/SAML/OIDC federation.
- Encrypting OAuth client secrets with Halite (AuthKit stores them; optional follow-up).
- Creating local users as `ROLE_ADMIN` via social signup.
- QR phone login / WebAuthn.
