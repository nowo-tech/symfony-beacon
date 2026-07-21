# Feature Specification: Magic Links and Viewer Role

**Feature Branch**: `026-magic-links-viewer`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Add passwordless magic-link login for users; optional signed share links to open a project or a specific issue; introduce a read-only **viewer** project role.

**Related**: Prefer [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) / Symfony Security login-link capabilities over a bespoke auth stack. SSO/OIDC remains a separate Later item on the roadmap.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Magic link login (Priority: P1)

As a user, I request a one-time login link by email and sign in without typing a password (within a short lifetime and limited uses).

**Why this priority**: Reduces friction for operators who already receive Beacon emails; aligns with AuthKit password-reset link patterns.

**Independent Test**: Request a login link for a known user; open the link; assert authenticated session and single-use or lifetime expiry behaviour.

**Acceptance Scenarios**:

1. **Given** an enabled account, **When** I request a magic login link, **Then** I receive (or in test: the system issues) a time-limited link that signs me in when opened.
2. **Given** an expired or already-consumed link, **When** I open it, **Then** sign-in fails with a clear message and no session is created.
3. **Given** a disabled account, **When** a magic link is requested or used, **Then** access is denied consistently with password login.
4. **Given** login throttling, **When** magic-link requests are abused, **Then** rate limits apply (same order of protection as password login).

---

### User Story 2 - Viewer role (Priority: P1)

As a project owner/admin, I grant a user **viewer** access so they can inspect issues and analytics but cannot mutate triage, settings, keys, or memberships.

**Why this priority**: Stakeholders need read-only access without full **member** write capabilities.

**Independent Test**: Assign viewer; assert GET issue/analytics succeed; assert POST status/comment/settings denied.

**Acceptance Scenarios**:

1. **Given** a viewer membership (direct or via group policy if groups support viewer), **When** they open Issues, issue detail, Performance, or Analytics, **Then** pages load read-only.
2. **Given** a viewer, **When** they attempt resolve/reopen/assign/comment/priority/duplicate/merge, export (if restricted), or Settings mutations, **Then** the action is denied (403 or UI without controls).
3. **Given** an owner, **When** they assign roles, **Then** viewer appears alongside owner / admin / member with clear labelling.
4. **Given** “view as member” for instance admins, **When** viewer ships, **Then** documentation clarifies the difference (session demotion vs lasting membership role).

---

### User Story 3 - Signed share link to a project or issue (Priority: P2)

As a project admin/owner, I create a time-limited signed link that opens a specific project home or issue for an authenticated **viewer** (or creates a constrained session), without sharing passwords.

**Why this priority**: Useful for incident war-rooms; secondary to first-class viewer membership and magic login.

**Independent Test**: Create share link for an issue; open as anonymous and as logged-in user; assert access rules and expiry.

**Acceptance Scenarios**:

1. **Given** a valid share link to an issue, **When** an eligible recipient opens it, **Then** they land on that issue with read-only capabilities for the linked project scope.
2. **Given** an expired or revoked share link, **When** opened, **Then** access is denied with a clear message.
3. **Given** share link creation, **When** an admin creates one, **Then** lifetime and scope (project vs single issue) are explicit and auditable.
4. **Given** a share link, **When** used, **Then** it MUST NOT grant Settings, API key, or membership management access.

### Edge Cases

- Magic links must not leak whether an email exists beyond existing AuthKit practices (consistent responses where required).
- Viewer cannot escalate by guessing admin URLs.
- Share links never embed long-lived API secrets or Envelope credentials.
- Last owner rules unchanged; viewer cannot be the sole owner.
- Legal/privacy: share links and magic login emails are operational mail (no new tracking cookies); update LEGAL docs if new third-party IdP appears (SSO is out of scope here).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST support requesting and consuming a time-limited magic login link for enabled users.
- **FR-002**: Magic login MUST respect account disable and login throttling policies.
- **FR-003**: Project membership MUST include a **viewer** role that is read-only for product surfaces (Issues, Performance, Analytics, read of Settings summary if shown).
- **FR-004**: Viewer MUST NOT mutate issues, memberships, API keys, governance, notifications, or danger-zone actions.
- **FR-005**: Owners/admins MUST be able to assign and revoke the viewer role like other membership roles.
- **FR-006**: System SHOULD support creating revocable, time-limited signed links scoped to a project or a single issue for read-only access (P2).
- **FR-007**: Share-link and magic-link actions MUST be auditable (`user_action` or equivalent).
- **FR-008**: Prefer AuthKit / Security login-link integration; do not invent a parallel credential store.

### Key Entities

- **Magic login token**: Time-limited, bounded-use credential for passwordless sign-in.
- **ProjectRole viewer**: Read-only membership rank below member.
- **Share link**: Signed URL with scope (project | issue), expiry, and optional revoke flag.

## Success Criteria *(mandatory)*

- **SC-001**: A user can complete passwordless login via magic link in functional tests.
- **SC-002**: Viewer can open issue detail but cannot change status in functional tests.
- **SC-003**: Expired magic and share links fail closed.
- **SC-004**: UPGRADING documents the new role and any migration; SECURITY notes token lifetime defaults.

## Assumptions

- Magic login is optional alongside password login (not a replacement required for all users).
- Group-linked access may support viewer if group roles already allow admin/member; owner remains direct-only.
- Instance `ROLE_ADMIN` effective owner behaviour stays; viewer is a project-scoped role.

## Out of scope

- **SSO / SAML / OIDC** (roadmap Later; separate spec).
- Public anonymous issue boards without authentication.
- Embedding Envelope ingest credentials in share links.
- Native PagerDuty login.
