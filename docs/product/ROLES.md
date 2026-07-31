# Roles and access control

Beacon uses **two separate role systems**. Do not confuse Symfony Security `ROLE_*` strings (instance-wide) with project membership roles (`owner` / `admin` / `member` / `viewer`).

| Layer | Where stored | Values | Purpose |
|-------|--------------|--------|---------|
| **Instance (Security)** | `user.roles` JSON + implicit `ROLE_USER` | `ROLE_USER`, `ROLE_ADMIN` | Login gate, Administration UI, kit admin dashboards |
| **Project membership** | `project_membership.role` / group links | `owner`, `admin`, `member`, `viewer` | Per-project capabilities (Settings, triage, delete, …) |

Share-link grants are a third path (time-limited viewer session); see product Settings → share links. They are **not** Symfony roles.

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
  - `/admin` hub, Users, Groups, Admin Projects, Appearance, Mailer, Mercure.
  - Kit admin UIs (`access_roles: [ROLE_ADMIN]`): dashboard menus, breadcrumb kit, cookie consent config.
  - Setup wizard after users already exist; platform-catalog redirect when catalogs are missing.
  - **Effective project access:** `ProjectAccessService` treats instance admins as **owner** on every project (unless “view as member” is active). Membership mutations that project admins cannot do (e.g. owner rows) are allowed for `ROLE_ADMIN`.
- **Listing caveat:** `/projects` only lists projects via **direct membership or linked groups**. Effective owner access alone does not put a project on that list. `make dogfood` (`app:seed-demo --skip-demo-user`) therefore grants every existing `ROLE_ADMIN` a direct membership on the **Symfony Beacon** project (`slug=symfony-beacon`) so it appears in the UI (without creating `admin@symfony-beacon.local`).

Beacon does **not** define other custom Symfony roles (`ROLE_SUPER_ADMIN`, etc.). Do not invent new `ROLE_*` values without updating this doc, `security.yaml`, and admin role UI.

### Hierarchy

Symfony’s default role hierarchy is **not** customized in this repo. In practice:

- Checks for `ROLE_USER` succeed for any logged-in user (including admins), because `ROLE_USER` is always present on the user object.
- Checks for `ROLE_ADMIN` require that role to be stored on the account.

---

## Project membership roles (not `ROLE_*`)

Enum: `App\Shared\ProjectRole` (`owner` | `admin` | `member` | `viewer`).

| Role | Typical capabilities |
|------|----------------------|
| `owner` | Full project control including delete / transfer ownership |
| `admin` | Manage members, API keys, settings (not delete project) |
| `member` | Triage issues, use product surfaces |
| `viewer` | Read-only |

Resolved by `ProjectAccessService` from direct membership, group links, share grants, and the instance-`ROLE_ADMIN` shortcut above.

Admin UI labels “Admin” / “User” on `/admin/users` map to **`ROLE_ADMIN` / no `ROLE_ADMIN`** (instance). Project role labels use the membership enum strings.

---

## Quick reference for contributors

| Need | Prefer |
|------|--------|
| “Must be logged in” | `#[IsGranted('ROLE_USER')]` or firewall `access_control` |
| “Instance operator only” | `#[IsGranted('ROLE_ADMIN')]` |
| “May triage / open Settings on this project” | `ProjectAccessService` + `ProjectRole` helpers |
| New admin menu item | Seed `required_role: ROLE_ADMIN` (see `DashboardMenuDemoSeeder`) |

Related: [ARCHITECTURE.md](../ARCHITECTURE.md) (module map), [INSTALL.md](../INSTALL.md) (seed / first admin), `config/packages/security.yaml`, `src/Project/Service/ProjectAccessService.php`.
