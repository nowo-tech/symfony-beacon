# Feature Specification: Identity/Project

**Feature Branch**: `002-identity-project`  
**Created**: 2026-07-19  
**Status**: Completed (as-built; dashboard create modal, group-link policy, CSRF on API keys; dual public locale routes — 2026-07-21; AuthKit `unlocalized: serve` + SiteBackup setup locale — 2026-07-31; `project.*` permission catalog + i18n; product `project.*` Twig gating + controller 403; `admin.*` catalog removed — 2026-08-10; project `code` + membership `active` + config portability — 2026-08-11 / `089`)  

## Summary

Identity uses [`nowo-tech/auth-kit-bundle`](https://packagist.org/packages/nowo-tech/auth-kit-bundle) for login, first-user registration, remember-me, and **locale-in-path** AuthKit routes. Password UX uses PasswordToggle + PasswordStrength on AuthKit forms. Account enable/disable uses [`nowo-tech/user-kit-bundle`](https://packagist.org/packages/nowo-tech/user-kit-bundle) with **session invalidation when an account is disabled**. Projects, memberships (`owner` / `full` / `admin` / `member` / `viewer`), and API keys live under `src/Project`. Account preferences and admin appearance live under Identity.

**Access layers**: Instance Administration is `ROLE_ADMIN`-gated (no built-in `admin.*` catalog). Product users (`ROLE_USER`) access projects via **per-project** `ProjectRole` → `ProjectPermission` matrix (`project.*` keys). The `project.*` catalog seeds into the shared `permission` table for Administration UI; runtime project checks use `ProjectAccessService` / `requirePermission()` (and related helpers), **not** `InstancePermissionVoter` / `is_granted('project.*')`. Twig helpers hide forbidden UI; controllers MUST still 403. See `docs/product/ROLES.md`.

**Public locale routing**: Login, register, logout, password reset, magic login use AuthKit `locale.in_path: both` + `unlocalized: serve` (canonical `/{_locale}/…`; bare `*_unlocalized` serves `DEFAULT_LOCALE` without redirect). Legal pages keep `/{_locale}/legal/…` for every locale (bare `/legal/…` → `/{DEFAULT_LOCALE}/legal/…`). Setup uses SiteBackup **≥ 1.7.0** `setup.locale` with the same `both` + `serve` model (`/setup` vs `/{_locale}/setup`) — see `056-setup-wizard`. **`.env.dist` ships `en`; this project's `.env` uses `es`.** Authenticated app-shell URLs stay unprefixed; preferred locale is stored on the user. Optional DB/JSON locale paths for `#[Routable]` app controllers use RoutingKit (`064-routing-kit`); AuthKit/SiteBackup stay outside that kit.

**Not in scope of this completed feature (see Phase 5):** passwordless **magic login links**, project **viewer** role product flows, or signed share links to a project/issue — tracked in `026-magic-links-viewer`. **SSO/OIDC** remains roadmap Later (separate from magic links).

Prefer AuthKit / UserKit / AuditKit / RoutingKit over hand-rolled auth or locale CRUD when extending this area (see workspace nowo-tech kit guidance).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Auth and first admin (Priority: P1)

As an operator, I bootstrap the first admin via registration, then login with remember-me.

**Acceptance Scenarios**:

1. **Given** anonymous bare `/login` (or `/`), **When** requested with AuthKit `unlocalized: serve`, **Then** bare `/login` serves `DEFAULT_LOCALE` (no forced redirect to `/{DEFAULT_LOCALE}/login`); other locales use `/{_locale}/login`.
2. **Given** empty DB, **When** `/{_locale}/register` succeeds, **Then** the first user is `ROLE_ADMIN` and further registration is closed (`first_user_only`).
3. **Given** login, **When** remember-me is used, **Then** the session persists per AuthKit config.
4. **Given** locales `en` / `es` (or other enabled locales), **When** the guest visits `/{_locale}/login` or uses the public locale switcher, **Then** the UI language matches the path locale; switching language uses bare `*_unlocalized` for `DEFAULT_LOCALE` and prefixed URLs for others.
5. **Given** bare `/legal/privacy` (and siblings), **When** requested, **Then** each redirects to the same path under `/{DEFAULT_LOCALE}/legal/…`. Bare AuthKit paths serve default locale; bare `/setup` serves default locale via SiteBackup `setup.locale` (`056`).
6. **Given** an enabled user with an active session, **When** an admin disables the account, **Then** subsequent requests using that session are rejected (UserKit `invalidate_sessions_on_disable: true`).

### User Story 2 - Projects, keys, members (Priority: P1)

As an authenticated user, I manage projects from `/dashboard`, open Issues as the project home, and configure keys/members under Settings.

**Acceptance Scenarios**:

1. **Given** the dashboard, **When** I create a project, **Then** creation is a **modal** next to the project search (not a sidebar nav item); `GET /projects/new` redirects to `/dashboard?new=1` and opens the modal.
2. **Given** I create a project, **When** I open `/projects/{id}`, **Then** I am redirected to Issues.
3. **Given** I have a manage/delete grant on the project (admin/owner matrix), **When** I open `/projects/{uuid}/settings`, **Then** I can manage API keys (create POST requires **CSRF**), **direct members** (add by email with roles), **linked groups** (admin/member), and DSN helpers that include **public and secret** key material for **active** keys only (revoked/inactive keys show public id + inactive badge, never a copyable DSN). Member/group role and add-member modals use structured `confirm-dialog` chrome (`086` FR-003b). Mutations enforce named `ProjectPermission` keys via `ProjectAccessService::requirePermission()` (see FR-013).
4. **Given** membership roles owner/full/admin/member/viewer (direct or via group; owner/full are never via group), **When** a non-member opens a project URL, **Then** access is denied (403); the dashboard lists only accessible projects. **Inactive** direct memberships (`active=false`, `089`) grant no product access and MUST NOT appear as accessible projects. Effective capabilities follow the `ProjectRole` → `ProjectPermission` matrix (`project.view`, `project.issues.triage`, members/share/api keys/settings/notifications, `project.delete` for owner and **full** — see `docs/product/ROLES.md`).
5. **Given** a **viewer** or **member** (view/triage only), **When** they open Issues, **Then** the Settings nav tab is hidden (`project_can_open_settings`); **When** they request `/projects/{uuid}/settings` directly, **Then** the response is **403** (`requireSettingsSurface`). Triage-only forms (comments, saved-view save/delete) are hidden without `project.issues.triage` and POST returns 403.
6. **Given** I can open Settings with a partial manage matrix (e.g. admin without `project.delete`), **When** I view Settings, **Then** panels/cards I lack are hidden (`canManageSettings` / `canManageApiKeys` / `canManageNotifications` / `canManageShareLinks` / `canDeleteProject` / `isPrimaryOwner`) and forged POSTs to those routes still return **403** (FR-013). Add-member / role / remove controls follow `assignableRoles` and owner-row rules (FR-002).
7. **Given** a project admin who is **not** a member of group G, **When** they try to link G to the project, **Then** the action is denied; owners and instance `ROLE_ADMIN` may link any group; project admins may only link groups they belong to.

### User Story 3 - Account and admin surfaces (Priority: P2)

As a user, I update profile/security/display preferences; as admin, I reach Appearance.

**Acceptance Scenarios**:

1. **Given** `/account/profile`, `/account/security`, `/account/display`, **When** I save Display prefs, **Then** preferred collapsed issue panels are stored on the user (`preferredCollapsedIssuePanels` / `IssuePanelIds`).
2. **Given** `ROLE_ADMIN`, **When** I open `/admin` and `/admin/appearance`, **Then** admin hubs load.
3. **Given** public legal/cookie surfaces, **When** non-essential cookies apply, **Then** cookie consent and legal pages remain available at `/{_locale}/legal/…` and bare `/legal/…` (redirect to `DEFAULT_LOCALE`); see `docs/product/LEGAL-AND-COOKIES.md`.
4. **Given** `app:seed-platform` has run, **When** I open `/admin/permissions`, **Then** the built-in catalog includes **Project membership** (`project.*`, 8 keys) with categories `access` / `issues` / `collaboration` / `integration` / `settings` / `danger`; translated labels via `permissions.catalog.*` / `permissions.category.*` (see `docs/product/ROLES.md`). Leftover `admin.*` rows are absent.
5. **Given** `app:seed-platform` has run, **When** I open `/admin/roles`, **Then** five system roles exist (`ROLE_PROJECT_VIEWER`, `ROLE_PROJECT_MEMBER`, `ROLE_PROJECT_ADMIN`, `ROLE_PROJECT_FULL`, `ROLE_PROJECT_OWNER`) whose matrices mirror `ProjectRole` → `ProjectPermission`; legacy operator codes (`ROLE_SUPPORT`, `ROLE_OPS_VIEWER`, `ROLE_PLATFORM`, `ROLE_NAV_EDITOR`, `ROLE_PROJECT_OPS`) are absent.
6. **Given** `/admin/permissions` with many edit dialogs, **When** the page loads without `?edit=` / `?new=`, **Then** no edit/create dialog auto-opens (`086` FR-003c).
7. **Given** `/admin/roles`, **When** I create or edit a role, **Then** the form is a confirm-dialog modal on the list/detail page (`GET /admin/roles/new` → `?new=1`; `GET …/edit` → overview `?edit=1`); not a dedicated full-page form (`086` US 2c).

## Requirements *(mandatory)*

- **FR-001**: AuthKit owns login/register/password UX; app does not maintain a parallel SecurityController login.
- **FR-002**: Project Settings is the management surface for actors with Settings-surface grants; project show routes to Issues; actors with `project.members.manage` add/remove **direct** members, **activate/deactivate** memberships (`089`), and assign roles (`full` / `admin` / `member` / `viewer` where applicable — and `owner` only via **Transfer ownership**, not the member edit/remove controls). Member rows with role `owner` MUST NOT show edit-role or remove actions (Settings and Admin → Projects). Server-side: the last **active** owner cannot be removed, demoted, or deactivated; a **full** member cannot be removed until demoted. Capability checks MUST use `ProjectRole` / `ProjectPermission` (or equivalent helpers on `ProjectAccess`).
- **FR-003**: API keys support labels and safe public identifiers for operators; creating a key MUST validate CSRF; DSNs MUST include secret when present (`https://public:secret@host/{projectUuid}`). Mutations MUST require `project.api_keys.manage`. Settings MUST NOT re-embed the full DSN/secret on ordinary GET — create/rotate MUST flash a one-shot DSN banner (`_beacon_last_api_key_dsn`) cleared from session on that render. **Revoked / inactive** keys MUST NOT render a copyable DSN, secret, or clipboard-copy control (public key + inactive badge only).
- **FR-004**: Account Display preferences include default collapsed issue panels. New users MUST persist concrete locale (`%default_locale%`), theme, contrast, and motion defaults; legacy null columns heal on `/account/display`.
- **FR-005**: Kits may include dashboard-menu, breadcrumb-kit, form-kit, cookie-consent, PWA, and RoutingKit (as configured in the app).
- **FR-006**: Project data is membership-scoped: dashboard lists only accessible projects; controllers enforce `ProjectAccessService` (**active** direct membership **or** linked group **or** share grant). Prefer `requirePermission(ProjectPermission::…)` over raw role rank where a named capability exists.
- **FR-006b** (`089`): Each `Project` MUST have a unique `code` (slug-like portability key; backfilled from `slug`). Direct `ProjectMembership` MUST support `active` (default true). Project Settings config export/import MUST require `project.settings.manage` and MUST NOT create users; see `089-project-config-export`.
- **FR-007**: Admins manage **user groups**; projects may link groups with `admin`/`member` role so all group users gain access. Owner role is direct-user only. Linking policy: instance admin or project **owner** may link any group; project **admin** only groups they belong to.
- **FR-008**: New project UX is dashboard-modal (search row), not Dashboard sidebar menu.
- **FR-009**: Disabling a user account MUST invalidate existing sessions (`nowo_user_kit` account_status).
- **FR-010**: Public auth and setup surfaces MUST support dual bare + `/{_locale}/…` paths with AuthKit/SiteBackup `unlocalized: serve` for `DEFAULT_LOCALE`. Legal bare paths redirect to `/{DEFAULT_LOCALE}/legal/…`. Unauthenticated security entry points resolve to AuthKit login (bare or prefixed per locale mode).
- **FR-011**: Guest locale switching on public dual-path pages MUST prefer path URLs (`*_unlocalized` for default locale); authenticated dashboard URLs MUST NOT require a `_locale` path segment.
- **FR-012**: Permission catalogs seeded by `app:seed-platform` into the shared `permission` table:
  1. **Project membership** — `App\Project\Security\ProjectPermission` + `ProjectPermissionCatalog` (`project.view`, `project.issues.triage`, `project.members.manage`, `project.api_keys.manage`, `project.settings.manage`, `project.notifications.manage`, `project.share_links.manage`, `project.delete`; categories `access` / `issues` / `collaboration` / `integration` / `settings` / `danger`). Runtime access MUST be resolved per project via `ProjectAccessService` / `ProjectRole` matrix — **not** via assigning `project.*` on an InstanceRole as a substitute for membership. Administration surfaces stay `ROLE_ADMIN`-gated (no built-in `admin.*` keys).
  2. Seed MUST upsert system InstanceRoles from `InstanceRoleCatalog` that mirror `ProjectRole` matrices: `ROLE_PROJECT_VIEWER`, `ROLE_PROJECT_MEMBER`, `ROLE_PROJECT_ADMIN`, `ROLE_PROJECT_FULL`, `ROLE_PROJECT_OWNER` (only `project.*` keys). These document the capability sets in Administration → Roles; runtime product access remains per-project membership.
  3. Seed MUST remove legacy operator InstanceRole codes (`ROLE_SUPPORT`, `ROLE_OPS_VIEWER`, `ROLE_PLATFORM`, `ROLE_NAV_EDITOR`, `ROLE_PROJECT_OPS`) if present, and purge leftover `admin.*` permission rows.
  4. UI labels MUST resolve via REQ-RBAC-008 translators (`roles.catalog.*`, `permissions.catalog.*`, `permissions.category.*`) with entity/translation fallbacks; machine keys stay untranslated. Product reference: `docs/product/ROLES.md`.
- **FR-013**: Product HTTP enforcement for named capabilities (insufficient → **HTTP 403** `AccessDeniedHttpException`):
  | Surface | Service call / key |
  |---------| | ------------------ |
  | Issues / Performance / Analytics / Releases (read) | `requireAccess` / `requireMembership` (`project.view`) |
  | Triage, comments, saved-view mutations | `requireTriage` / `project.issues.triage` |
  | Settings page GET | `requireSettingsSurface()` (any of members/api_keys/settings/notifications/share_links/delete) |
  | Governance, read tokens, clear history, issue/event export, **project config export/import** (`089`) | `project.settings.manage` |
  | Members / group links / **activate·deactivate membership** (`089`) | `project.members.manage` |
  | Transfer ownership | `requirePrimaryOwner()` (exact `Owner`; not `full`) |
  | API keys create/rotate/revoke | `project.api_keys.manage` |
  | Notification destinations / thresholds | `project.notifications.manage` (`ProjectChildEntityGuard` included) |
  | Share links create/revoke | `project.share_links.manage` |
  | Delete project | `project.delete` |
- **FR-014**: Dual UI + HTTP gating (Twig hide is **not** a security boundary; FR-013 always applies):
  1. **Nav tab** — Settings tab only when `project_can_open_settings(project)` (`templates/project/_nav.html.twig`). Direct GET without Settings-surface grants → **403** (`requireSettingsSurface`).
  2. **Settings panels/cards** (`templates/project/settings.html.twig`) — hide by `ProjectAccess` helpers when `membership`/`access` is in the view:
     | Panel / control | Twig gate | Controller (POST / sensitive) |
     |-----------------|-----------|-------------------------------|
     | Governance / quota / retention | `canManageSettings` | `project.settings.manage` |
     | Project config export/import (`089`) | `canManageSettings` | `project.settings.manage` |
     | API keys / DSN | `canManageApiKeys` | `project.api_keys.manage` |
     | Read API tokens | `canManageSettings` | `project.settings.manage` |
     | Members add / role / remove / activate·deactivate (non-owner rows) | `assignableRoles` + FR-002 | `project.members.manage` |
     | Group links | `assignableGroupRoles` / members manage | `project.members.manage` |
     | Share links | `canManageShareLinks` | `project.share_links.manage` |
     | Notifications / thresholds | `canManageNotifications` | `project.notifications.manage` (+ `ProjectChildEntityGuard`) |
     | Clear history | `canManageSettings` | `project.settings.manage` |
     | Transfer ownership | `isPrimaryOwner` | `requirePrimaryOwner()` |
     | Delete project | `canDeleteProject` | `project.delete` |
  3. **Helpers** — `project_grants(project, 'project.…')`, `project_can_open_settings(project)`, `project_access(project)` via `ProjectPermissionTwigExtension`.
  4. Product MUST NOT use `is_granted('project.…')` for these checks (wrong layer: instance voter).
  5. Issues triage UI (comments, status, saved views) MUST hide without `project.issues.triage` and POST MUST 403 via `requireTriage`.

## Success Criteria

- **SC-001**: First-boot registration + login + project membership flows are covered by tests.
- **SC-002**: Operators can copy a DSN (with secret) from the one-shot create/rotate banner and manage keys without leaving Settings; ordinary Settings GET never embeds the secret (`ProjectApiKeyVisibilityTest`).
- **SC-003**: Dashboard create-project modal and group-link restrictions are covered by functional tests.
- **SC-004**: Dual public locale routing (AuthKit/SiteBackup `both` + `serve`, legal bare→default, functional tests) is covered.
- **SC-005**: Platform seed + Admin permissions UI cover **8** built-in `project.*` keys and **5** system project-mirror roles (`ROLE_PROJECT_VIEWER` / `MEMBER` / `ADMIN` / `FULL` / `OWNER`); leftover `admin.*` rows and legacy operator InstanceRoles are removed; `ProjectPermission` / `InstanceRoleCatalog` / `AdminInstanceRbacTest` assert catalog keys, role matrices, and closed dialogs on `/admin/permissions`.
- **SC-006**: Viewer cannot open Settings (403) and does not see the Settings nav tab; Settings panels and mutation forms are Twig-gated per FR-014 and controller-enforced per FR-013; owner/admin Settings show public key only on ordinary GET (DSN only in one-shot flash) covered (`ProjectApiKeyVisibilityTest`); Twig extension unit-tested (`ProjectPermissionTwigExtensionTest`).

## Amendment (`089` Identity boundary, 2026-08-11)

- Disabled user creation for admin project-config import lives in Identity `PortableUserProvisioner` (not Project). Batch email lookup: `UserRepository::findIndexedByEmails`. Details: `089-project-config-export` N+1 amendment.

## Amendment (session + cookie consent, 2026-08-11)

- Session cookie name: `beacon.session_cookie_name` → `SYMFONY_BEACON_SESSID` by default (`087`).
- Session without Remember me: **1 day**; Remember me: **30 days** (AuthKit + firewall lifetimes aligned).
- Cookie Consent modal: public-only `render_routes` whitelist (`081` Cookie Consent amendment); not on authenticated product shells.
- Locale switch / guest locale POSTs: Symfony `csrf_action_form()` (`090`).
- Settings member/group add Forms (`ProjectMemberAddType` / `ProjectGroupAddType`) use FormKit profile `beacon` + `form` catalogue prefixes; Twig `form_row` + `_fields` (`081` FR-003c / `077`).

## Amendment (cookie consent guest skin + bottom-left, 2026-08-15)

- Public consent chrome is owned by host `assets/styles/_cookie_consent.scss` (category cards, Beacon primary/ghost buttons, overlay `--pos-y-*` / `--pos-x-*`). Vendor JS style inject is unreliable under CSP style nonces — do not rely on it for layout or buttons.
- Default seeded profile (`CookieConsentDemoSeeder` / `cookie_consent.default.json`): consent + preferences modals at **bottom left**, equal-weight action buttons, `box` / `wide`.
- Product reference: [`docs/product/LEGAL-AND-COOKIES.md`](../../docs/product/LEGAL-AND-COOKIES.md). Related kit pin / public-only rules: `081` Cookie Consent amendments.

See product README, [`docs/product/ROLES.md`](../../docs/product/ROLES.md), [`docs/CONTRIBUTING.md`](../../docs/CONTRIBUTING.md), and constitution.
