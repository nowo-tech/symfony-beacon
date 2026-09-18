# Administration

Requires **`ROLE_ADMIN`**. The admin area combines Beacon instance CRUD with nowo-tech kit panels (HTTP log, menus, routing, breadcrumbs, cookie consent, site backup, and related ops screens).

Open **Administration** from the operator navigation (hub cards mirror the sidebar groups).

## Hub

**What it is.** Card index of admin modules grouped as Overview, Access, Instance, and Navigation & legal.

**What it contributes.** A single map of operator tools so you do not need to memorize routes. Each card opens a dedicated configuration surface described below.

![Administration hub](images/admin.png)

## Identity and access

### Users

**What it is.** Directory of accounts on this instance (roles, status, search).

**What it contributes.** Create and manage who can sign in when self-registration is closed or insufficient. Complements AuthKit without a custom `SecurityController`.

![Users](images/admin-users.png)

#### New user

**What it is.** Create-user form (identity, roles, initial credentials as configured). Companion shot continues the same viewport when the form is taller than 900px.

**What it contributes.** Provisions accounts for teammates and service operators under instance policy.

![New user](images/admin-users-new.png)

![New user (continued)](images/admin-users-new-2.png)

#### User activity

**What it is.** Activity view for a selected user (or the user-activity panel in admin). Companion shot continues the same viewport.

**What it contributes.** Operator visibility into last activity and related signals (pairs with UserKit online / last-activity features).

![User activity](images/admin-user-activity.png)

![User activity (continued)](images/admin-user-activity-2.png)

### Groups

**What it is.** Groups used to grant project access to many users at once.

**What it contributes.** Scales membership: attach a group to a project instead of editing every user. **New group** opens as a modal (name + optional description); edit remains a full page.

![Groups](images/admin-groups.png)

![New group](images/admin-groups-new.png)

### Projects (admin)

**What it is.** Instance-wide project directory for admins (members, group links, delete).

**What it contributes.** Cross-project governance beyond what a single project owner sees. **New project** opens as a modal; edit remains a full page.

![Projects](images/admin-projects.png)

![New project (admin)](images/admin-projects-new.png)

### Roles

**What it is.** Instance roles and their permission matrices.

**What it contributes.** Defines reusable capability sets (for example admin vs member) assigned to users. See [ROLES.md](../product/ROLES.md).

![Roles](images/admin-roles.png)

![New role](images/admin-roles-new.png)

### Permissions

**What it is.** Catalog of permission / capability keys and editable labels.

**What it contributes.** Source of truth for what roles can grant; keeps matrices understandable in the UI.

![Permissions](images/admin-permissions.png)

![New permission](images/admin-permissions-new.png)

## Operations

### Ops overview

**What it is.** Cross-project operational dashboard (spikes, open issues, failed deliveries).

**What it contributes.** Instance health at a glance before diving into a single project or the HTTP log.

![Ops overview](images/admin-ops.png)

### Mailer

**What it is.** Encrypted Mailer DSN and From address for transactional email (AuthKit mail, resets, magic links).

**What it contributes.** Makes email-dependent auth flows work in production; local demos often point at Mailpit.

![Mailer](images/admin-mailer.png)

### Appearance

**What it is.** Instance branding: brand name, accents, status colours, and page surfaces.

**What it contributes.** Operator-controlled look of the product shell (distinct from each user’s day/night toggle in [Theme and language](07-appearance.md)).

![Appearance](images/admin-appearance.png)

### Instance config

**What it is.** Core instance configuration knobs exposed to admins.

**What it contributes.** Central place for server-level defaults that projects may inherit (quotas, feature flags, related settings).

![Instance config](images/admin-instance-config.png)

### Ops defaults

**What it is.** Default operational parameters applied when projects leave fields empty.

**What it contributes.** Consistent retention / rate-limit baselines across many projects without editing each one.

![Ops defaults](images/admin-ops-defaults.png)

### Mercure

**What it is.** Optional Mercure hub settings for live issue alerts.

**What it contributes.** Enables real-time UI updates when a Mercure hub is available; safe to leave unused when not configured.

![Mercure](images/admin-mercure.png)

### Social login

**What it is.** OAuth provider credentials (Google / GitHub / Microsoft, as enabled).

**What it contributes.** Optional AuthKit social sign-in without hard-coding secrets in templates. **New** opens the create flow for an additional provider entry.

![Social login](images/admin-social-login.png)

![New social login](images/admin-social-login-new.png)

## Kits and tooling

### HTTP request log

**What it is.** Admin UI for `nowo-tech/http-log-bundle` (request/response audit, async persist).

**What it contributes.** Forensics and support: who hit which routes, with filters suited to operators.

![HTTP request log](images/admin-http-log.png)

### Dashboard menus

**What it is.** Tree menus for dashboard / admin navigation (`nowo-tech/dashboard-menu-bundle`).

**What it contributes.** Data-driven menus with i18n and permissions instead of hard-coded Twig-only nav.

![Dashboard menus](images/admin-menus.png)

### Routing kit

**What it is.** Dual-locale / routable path rules (`nowo-tech/routing-kit-bundle`).

**What it contributes.** Keeps public paths consistent (`/foo` and `/{locale}/foo`) and offers an admin panel for route rules.

![Routing kit](images/admin-routing.png)

![New route rule](images/admin-routing-new.png)

### Breadcrumb collections

**What it is.** Breadcrumb trails stored and edited via `nowo-tech/breadcrumb-kit-bundle`.

**What it contributes.** Consistent, i18n-aware breadcrumbs across admin and product pages.

![Breadcrumb collections](images/admin-breadcrumbs.png)

![New breadcrumb collection](images/admin-breadcrumbs-new.png)

### Cookie consent

**What it is.** Admin configuration for `nowo-tech/cookie-consent-bundle` (categories, optional consent logging).

**What it contributes.** Required whenever non-essential cookies or third-party scripts appear. Public pages: [Legal and privacy](06-legal-and-privacy.md).

![Cookie consent admin](images/admin-cookie-consent.png)

### Legal pages

**What it is.** CKEditor 5 editor for the public legal notice, privacy policy, terms, and cookie page (`nowo-tech/ckeditor5-editor-bundle` via FormKit). One document per page and locale.

**What it contributes.** The operator publishes identification and policies for this deployment without forking the repository. Built-in seed stays live until a locale is saved. Saving strips scripts and iframes. **Restore built-in text** drops that override. This does not replace cookie-consent configuration. Counsel must still approve the copy before production.

![Legal pages](images/admin-legal.png)

![Edit legal notice](images/admin-legal-edit.png)

### Maintenance

**What it is.** Maintenance-mode and related operator controls.

**What it contributes.** Temporarily takes the UI offline (or into a maintenance state) for upgrades without tearing down the stack.

![Maintenance](images/admin-maintenance.png)

### Site backup

**What it is.** SiteBackup panel for instance backup / restore workflows (and history of runs).

**What it contributes.** Durable operator backups distinct from per-project Data settings. The setup wizard in chapter 00 is the cold-install sibling of this tooling.

![Site backup](images/admin-site-backup.png)

![Site backup history](images/admin-site-backup-history.png)

## Next steps

- [Legal pages](06-legal-and-privacy.md)  
- [PRODUCTION.md](../PRODUCTION.md)  
