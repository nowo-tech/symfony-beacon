# Roles and access control

Beacon uses **layered access**. Do not confuse Symfony Security `ROLE_*` strings (instance-wide) with project membership roles (`owner` / `full` / `admin` / `member` / `viewer`) or with dotted **instance permission keys**.

| Layer | Where stored | Values | Purpose |
|-------|--------------|--------|---------|
| **Instance (Security)** | `user.roles` JSON + implicit `ROLE_USER` + assigned `role.code` (`InstanceRole`) | `ROLE_USER`, `ROLE_ADMIN`, custom `ROLE_*` | Login gate, Administration UI, kit admin dashboards |
| **Instance RBAC catalog** | `permission` + `role` + `role_permission` + `role_user` (entity matrix) | Built-in `project.*` keys + optional custom dotted keys; `ROLE_PROJECT_*` mirror roles | Document / edit project capability catalog and mirror roles in Admin UI — **not** a substitute for Administration `ROLE_ADMIN` |
| **Project membership** | `project_membership.role` / group links | `owner`, `full`, `admin`, `member`, `viewer` | Per-project capabilities (Settings, triage, delete, …) |

Share-link grants are a third path (time-limited viewer session); see product Settings → share links. They are **not** Symfony roles.

Shared conventions (dotted keys, voters, catalog SoT, shared table names): workspace [`OTHER_FULL_SPECS_DETAILS.md`](../../../OTHER_FULL_SPECS_DETAILS.md) **REQ-RBAC**.

---

## Instance Security roles (`ROLE_*`)

Configured in `config/packages/security.yaml`. Controllers use `#[IsGranted(...)]`; kits use `access_roles`.

### `ROLE_USER`

- **Meaning:** Any signed-in Beacon account.
- **How it is granted:** Implicit. `User::getRoles()` always appends `ROLE_USER` even when `user.roles` is empty.
- **What it unlocks:**
  - Default `access_control` for authenticated app paths (`^/` → `ROLE_USER` after public exceptions).
  - Dashboard, projects list, Issues / Performance / Analytics / Notifications controllers, account preferences (`/account/*`).
- **What it does *not* unlock:** Administration hub, Appearance / Mailer / Mercure settings, kit CRUD (menus, breadcrumbs, cookie consent admin), instance user/group/project ops.

### `ROLE_ADMIN`

- **Meaning:** Instance operator (administration of the Beacon deployment), **not** “project owner”.
- **How it is granted:**
  - First registration (`nowo_auth_kit.registration_mode: first_user_only` → `registration_role: ROLE_ADMIN`).
  - Setup / SiteBackup admin provisioning.
  - `app:seed-demo` demo user (`make seed` / `make ready`). `make dogfood` uses `--skip-demo-user` and only grants existing admins.
  - Administration → Users → change role (cannot demote the last admin or yourself).
- **What it unlocks:**
  - `/admin` hub, Users, Groups, Roles, Permissions, Admin Projects, Appearance, Mailer, Mercure.
  - Kit admin UIs (`access_roles: [ROLE_ADMIN]`): dashboard menus, breadcrumb kit, cookie consent config.
  - Setup wizard after users already exist; platform-catalog redirect when catalogs are missing.
  - **Effective project access:** `ProjectAccessService` treats instance admins as **owner** on every project (unless “view as member” is active). Membership mutations that project admins cannot do (e.g. owner rows) are allowed for `ROLE_ADMIN`.
  - **All dotted permission keys** via `InstancePermissionVoter` (superuser shortcut for catalog / custom `isGranted` checks).
- **Listing caveat:** `/projects` only lists projects via **direct membership or linked groups**. Effective owner access alone does not put a project on that list. `make dogfood` (`app:seed-demo --skip-demo-user`) therefore grants every existing `ROLE_ADMIN` a direct membership on the **Symfony Beacon** project (`slug=symfony-beacon`) so it appears in the UI (without creating `admin@symfony-beacon.local`).

### Custom instance roles (RBAC)

Administration UI is **`ROLE_ADMIN`-gated**. There is **no** built-in `admin.*` permission catalog — instance operators are simply `ROLE_ADMIN`.

Day-to-day product access for `ROLE_USER` is **`ProjectRole` + `ProjectPermission`** (below). Members need **zero** instance permission grants.

- **Storage:** Entity matrix (workspace REQ-RBAC-004) — tables `permission`, `role`, `role_permission`, `role_user` (`InstancePermission` / `InstanceRole` entities).
- **Permission catalog (PHP SoT):** `ProjectPermissionCatalog` (**8** `project.*`), seeded by `app:seed-platform`. Obsolete `admin.*` rows are removed on seed.
- **Seeded system roles** (`InstanceRoleCatalog`, `system=true`) mirror `ProjectRole` matrices (catalog / documentation in Administration → Roles):

  | Code | Mirrors | Permissions |
  |------|---------|-------------|
  | `ROLE_PROJECT_VIEWER` | `viewer` | `project.view` |
  | `ROLE_PROJECT_MEMBER` | `member` | view + `project.issues.triage` |
  | `ROLE_PROJECT_ADMIN` | `admin` | member set + members / api_keys / settings / notifications / share_links |
  | `ROLE_PROJECT_FULL` | `full` | all `project.*` including `project.delete` (not primary owner) |
  | `ROLE_PROJECT_OWNER` | `owner` | all `project.*` including `project.delete` + primary ownership |

  Assigning these InstanceRoles on a user does **not** replace project membership; product gates use `ProjectAccessService`.
  System roles cannot be deleted; custom roles with assigned users also cannot be deleted until unassigned.
- **Legacy cleanup:** `app:seed-platform` **removes** formerly seeded operator codes (`ROLE_SUPPORT`, `ROLE_OPS_VIEWER`, `ROLE_PLATFORM`, `ROLE_NAV_EDITOR`, `ROLE_PROJECT_OPS`) if present, and purges leftover `admin.*` permission rows.
- **Admin UI:** Administration → **Roles** / **Permissions** (project catalog + optional custom keys). Closed permission dialogs must not auto-open (`086` FR-003c).
- **Voter:** `InstancePermissionVoter` grants catalogued dotted keys for `ROLE_ADMIN` or an assigned InstanceRole that includes the key.
- **Translatable labels (REQ-RBAC-008):** Roles → `roles.catalog.*`. Permissions → `permission_translation` rows (create/edit modal **locale tabs**, `DEFAULT_LOCALE` first; **not** JSON), then `permissions.catalog.*`, then entity columns. Categories → YAML `permissions.category.<slug>.name|description` with create/edit **selector** of `InstancePermissionCategoryCatalog` slugs (not free text). Machine `key` / category slug are never translated.

Do not invent new Symfony `ROLE_*` values without storing them on the user JSON (`ROLE_ADMIN`) or creating an `InstanceRole`.

### Hierarchy

Symfony’s default role hierarchy is **not** customized in this repo. In practice:

- Checks for `ROLE_USER` succeed for any logged-in user (including admins), because `ROLE_USER` is always present on the user object.
- Checks for `ROLE_ADMIN` require that role to be stored on the account.
- Permission-key checks succeed for `ROLE_ADMIN` or matching RBAC grants.

---

## Project membership roles (not `ROLE_*`)

Enum: `App\Project\Enum\ProjectRole` (`owner` | `full` | `admin` | `member` | `viewer`).
Logical keys: `App\Project\Security\ProjectPermission` (checked via `ProjectAccess` / `ProjectAccessService::requirePermission()`). Seed metadata: `ProjectPermissionCatalog` → shared `permission` table for catalog UI; runtime access is still **per-project membership**, not `InstancePermissionVoter`.

| Permission key | viewer | member | admin | full | owner |
|----------------|:------:|:------:|:-----:|:----:|:-----:|
| `project.view` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `project.issues.triage` | | ✓ | ✓ | ✓ | ✓ |
| `project.members.manage` | | | ✓ | ✓ | ✓ |
| `project.api_keys.manage` | | | ✓ | ✓ | ✓ |
| `project.settings.manage` | | | ✓ | ✓ | ✓ |
| `project.notifications.manage` | | | ✓ | ✓ | ✓ |
| `project.share_links.manage` | | | ✓ | ✓ | ✓ |
| `project.delete` | | | | ✓ | ✓ |

`full` matches `owner` for every `project.*` key but is **not** primary owner: it cannot transfer ownership and does not count as the last-owner guard. Groups may only be `admin` / `member` / `viewer` (never `owner` or `full`). After ownership transfer, the former primary owner is demoted to `full`. A `full` member must be demoted before removal.

Membership UI: rows with role **`owner`** do not show edit-role or remove actions (project Settings and Administration → Projects). Hand off primary ownership only via **Transfer ownership**.

Helpers on `ProjectRole` / `ProjectAccess` (`canTriageIssues()`, `canManageSettings()`, `isPrimaryOwner()`, …) wrap that matrix. Resolved by `ProjectAccessService` from direct membership, group links, share grants, and the instance-`ROLE_ADMIN` shortcut (effective owner unless view-as-member).

Admin UI labels “Admin” / “User” on `/admin/users` map to **`ROLE_ADMIN` / no `ROLE_ADMIN`** (instance). Project role labels use the membership enum strings.

---

## Enforcing `project.*` in controllers (403)

Product HTTP actions must call `ProjectAccessService` **before** reading sensitive data or mutating state. Prefer permission keys over raw role ranks:

| Surface | Prefer |
|---------|--------|
| Open Issues / Performance / Analytics / Releases | `requireAccess()` / `requireMembership()` (`project.view`) |
| Triage / comments / saved-view mutations | `requireTriage()` or `requirePermission(…, ProjectPermission::ISSUES_TRIAGE)` |
| Settings page GET | `requireSettingsSurface()` (any manage/delete grant) |
| Governance, read tokens, clear history, export | `requirePermission(…, ProjectPermission::SETTINGS_MANAGE)` |
| Members / group links | `requirePermission(…, ProjectPermission::MEMBERS_MANAGE)` |
| Transfer ownership | `requirePrimaryOwner()` (exact `Owner`; not `full`) |
| API keys create/rotate/revoke | `requirePermission(…, ProjectPermission::API_KEYS_MANAGE)` |
| Notification destinations / thresholds | `requirePermission(…, ProjectPermission::NOTIFICATIONS_MANAGE)` |
| Share links | `requirePermission(…, ProjectPermission::SHARE_LINKS_MANAGE)` |
| Delete project | `requirePermission(…, ProjectPermission::DELETE)` |

Insufficient access raises `AccessDeniedHttpException` → **HTTP 403**. Do **not** rely on Twig alone for security.

`ProjectChildEntityGuard::requireManagedChild()` enforces `project.notifications.manage` for notification/threshold child routes.

## Twig UI gating (`ProjectPermissionTwigExtension`)

Service: `App\Project\Twig\ProjectPermissionTwigExtension` (auto-registered). Resolves the **current user** via Security and `ProjectAccessService::resolveAccess()`.

| Twig function | Returns | Use for |
|---------------|---------|---------|
| `project_grants(project, 'project.…')` | `bool` | Buttons, forms, section panels |
| `project_access(project)` | `ProjectAccess\|null` | Rare: need role object in Twig |
| `project_can_open_settings(project)` | `bool` | Settings **nav tab** (`templates/project/_nav.html.twig`) |

Examples:

```twig
{% if project_grants(project, 'project.issues.triage') %}
    {# comment form / saved-view save #}
{% endif %}

{% if project_can_open_settings(project) %}
    {# Settings tab in project nav #}
{% endif %}
```

On Settings templates that already receive `membership` (`ProjectAccess`), prefer the typed helpers (`membership.canManageSettings`, `membership.canManageApiKeys`, `membership.canManageNotifications`, `membership.canManageShareLinks`, `membership.canDeleteProject`, `membership.isPrimaryOwner`, …) so **panels/cards** stay aligned with the matrix. Spec `002` FR-014 lists the panel → Twig → controller map.

**Rules of thumb**

1. Hide UI with Twig (`project_grants` / `canManage*` / Settings tab via `project_can_open_settings`) so users never see forbidden tabs, cards, or forms.
2. Enforce the same key again in the controller/manager with `requirePermission` (or `requireSettingsSurface` for the Settings GET, `requirePrimaryOwner` for transfer) so forged URLs/POSTs return **403**.
3. Never call `is_granted('project.…')` for product access — that hits instance `InstancePermissionVoter`, which is the wrong layer.

## Quick reference for contributors

| Need | Prefer |
|------|--------|
| “Must be logged in” | `#[IsGranted('ROLE_USER')]` or firewall `access_control` |
| “Full instance operator” | `#[IsGranted('ROLE_ADMIN')]` |
| “Partial Administration capability” | Not used — Administration stays `ROLE_ADMIN` only |
| “May triage / Settings / API keys on this project” | `ProjectAccessService::requirePermission(…, ProjectPermission::…)` + Twig `project_grants` |
| “Open Settings surface” | `requireSettingsSurface()` + `project_can_open_settings(project)` |
| New project capability | Add a `project.*` key on `ProjectPermission` + matrix row + Twig/controller gates; catalog seed via `ProjectPermissionCatalog` |

Related: [ARCHITECTURE.md](../ARCHITECTURE.md) (module map), [INSTALL.md](../INSTALL.md) (seed / first admin), `config/packages/security.yaml`, `src/Project/Service/ProjectAccessService.php`, `src/Project/Security/ProjectPermission.php`, `src/Project/Twig/ProjectPermissionTwigExtension.php`, `src/Identity/Security/InstancePermissionVoter.php`, `src/Identity/Security/Permission.php`.
