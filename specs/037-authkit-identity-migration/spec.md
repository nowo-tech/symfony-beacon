# Feature Specification: Identity Kit Polish (Account Chrome)

**Feature Branch**: `037-authkit-identity-migration`  
**Created**: 2026-07-31  
**Status**: Implemented (v0.13.0)

**Input**: Finish **Identity kit polish** for Beacon account surfaces (consistent Account navigation, security activity, linked social accounts UX, guest reset/OTP skin). **AuthKit already owns** login, register, magic login, password reset/OTP, and social OAuth (`registration_mode: first_user_only`). This is **not** a greenfield AuthKit migration and MUST NOT reintroduce a custom `SecurityController`.

**Folder name note**: Kept as `037-authkit-identity-migration` for ROADMAP continuity; treat “migration” as historical naming — scope is polish only.

## Already shipped (baseline — do not regress)

- AuthKit login / register / logout / remember-me / dual public locales.
- Magic login + social OAuth (`026`, `060`) with mailer gating where required (`034`).
- Account tabs: Profile | Projects | Groups; Security | History; Display | Panels | Tours | Notifications.
- Password change + password history; content-width preference; profile overview (roles, UUID, memberships).
- Password reset OTP path (`delivery: both`) shipped with AuthKit polish.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consistent Account area navigation (Priority: P1)

As a signed-in user, every Account page exposes the same top-level chrome for **Profile / Security / Display** (in addition to existing sub-tabs), so I do not hunt for sibling settings.

**Independent Test**: Open `/account/security` and `/account/display` → both show the shared Account nav with correct active state.

**Acceptance Scenarios**:

1. **Given** I am on Profile, Security, or Display (any sub-tab), **When** the page renders, **Then** a shared Account nav links to the three areas.
2. **Given** I follow Security from Profile, **When** the page loads, **Then** Security is marked active and existing security sub-tabs still work.

### User Story 2 - Linked social accounts on Security (Priority: P2)

As a signed-in user, on Account → Security I see which OAuth providers are linked to my account (or a clear empty state when social login is disabled / none linked). I do not get open admin signup via social (`create_user_if_missing` remains false at instance level).

**Acceptance Scenarios**:

1. **Given** social mode enabled and my user has a linked provider, **When** I open Security, **Then** I see that provider listed.
2. **Given** no linked providers, **When** I open Security, **Then** I see an empty/disabled explanation (not an error).
3. **Given** AuthKit does not expose unlink yet, **When** polish ships, **Then** either unlink is implemented via kit API **or** unlink is explicitly out of scope in UI copy (no fake button).

### User Story 3 - Security activity beyond password history (Priority: P2)

As a signed-in user, I can see recent security-relevant events for my account (e.g. password reset requested, magic login requested/consumed) in addition to password-change history — sourced from existing `UserAction` (or kit audit) without a new store.

**Acceptance Scenarios**:

1. **Given** a password-reset request was recorded for me, **When** I open security activity, **Then** that event appears.
2. **Given** I am another user, **When** I request my activity, **Then** I never see another user’s events.

### User Story 4 - Guest reset/OTP chrome (Priority: P3)

As a guest completing password reset (link or OTP), pages use Beacon guest shell branding consistently (host layout already extends `guest_shell`; add Twig overrides only if vendor pages still show unstyled kit chrome).

**Acceptance Scenarios**:

1. **Given** reset/OTP routes, **When** rendered, **Then** they sit in the same guest chrome as login (mark, locale, cookie bubble).

## Edge Cases

- Social login globally disabled → Security shows “not enabled” not a broken providers list.
- Mailer not ready → magic/reset remain gated (existing behavior); polish MUST NOT bypass gates.
- GDPR export/delete and SSO/SAML stay outside this feature (`043` / Later).

## Requirements *(mandatory)*

- **FR-001**: Spec/Status MUST describe polish only; AuthKit remains the auth UI owner; no custom login/register controllers.
- **FR-002**: Shared Account top nav (Profile | Security | Display) on all account area pages.
- **FR-003**: Security surface for linked social accounts (read-only list minimum; unlink only if AuthKit supports it cleanly).
- **FR-004**: End-user security activity list for the current user from `UserAction` (allowlisted auth types) or document deferral with rationale if kit data is insufficient.
- **FR-005**: Guest reset/OTP visual alignment with `guest_shell`; prefer config/layout over forking entire vendor pages.
- **FR-006**: Regression tests for existing tabs, content-width, password history, and AuthKit bootstrap.
- **FR-007**: English UI strings + locale key parity for new keys.

## Success Criteria

- **SC-001**: Users can move Profile ↔ Security ↔ Display without dead-ends or inconsistent chrome.
- **SC-002**: Existing AuthKit and account preference tests stay green.
- **SC-003**: No regression that reintroduces bespoke login/register routes.

## Assumptions

- Cursor / constitution rule: prefer `nowo-tech/auth-kit-bundle` + `user-kit-bundle`; AuthKit already integrated.
- Partial Unreleased work counts toward this spec; tasks mark shipped items `[x]` and remaining `[ ]`.

## Out of Scope

- Greenfield AuthKit rewrite or removing AuthKit.
- Enterprise SSO/SAML/OIDC.
- WebAuthn / passkeys / MFA (unless already provided by AuthKit and trivially wired).
- GDPR account export / anonymize (`043`).
- Admin identity audit timelines (`036` — admin-only).
