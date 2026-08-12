# Host Twig overrides (`templates/bundles/`)

Symfony resolves Twig templates from the **application** before the **bundle**. Paths under `templates/bundles/<BundleName>/…` replace the vendor file with the same relative path (for example `NowoUiKitBundle/partials/_pagination.html.twig` overrides `@NowoUiKitBundle/partials/_pagination.html.twig`).

This folder exists so Beacon can keep **product chrome** (shell, moss/sand tokens, Administration list pattern, FormKit themes, guest auth brand) while still using **nowo-tech** kits for behaviour, routes, JS hooks, and CSRF.

Related host shells (preferred over full page forks when possible): [`templates/kit/`](../kit/). Strategy summary also lives in [`docs/CONTRIBUTING.md`](../../docs/CONTRIBUTING.md) (“Kit Twig strategy”).

---

## Index

### Common

1. [Why overrides exist](#1-why-overrides-exist)
2. [Two layers: layout vs page fork](#2-two-layers-layout-vs-page-fork)
3. [Shared patterns](#3-shared-patterns)
4. [Checklist when upgrading kits](#4-checklist-when-upgrading-kits)
5. [What must not break](#5-what-must-not-break)

### Bundles

| Bundle | Role of overrides |
|--------|-------------------|
| [NowoUiKitBundle](#nowouikitbundle) | Product shell, burger, toasts, pagination |
| [NowoDashboardMenuBundle](#nowodashboardmenubundle) | Menus admin chrome + modal bodies |
| [NowoBreadcrumbKitBundle](#nowobreadcrumbkitbundle) | Breadcrumb admin list chrome |
| [NowoRoutingKitBundle](#noworoutingkitbundle) | `/_routing/` panel list + form |
| [NowoCookieConsentBundle](#nowocookieconsentbundle) | Consent admin tabs, forms, table |
| [NowoHttpLogBundle](#nowohttplogbundle) | HTTP log list / filter / detail |
| [NowoSiteBackupBundle](#nowositebackupbundle) | Backup panel tables + actions |
| [NowoAuthKitBundle](#nowoauthkitbundle) | Guest layout + a few security pages |
| [NowoPwaBundle](#nowopwabundle) | Install prompt / preferences links |
| [NelmioApiDocBundle](#nelmioapidocbundle) | Swagger UI inside app shell |
| [TwigBundle](#twigbundle) | HTTP error pages |

---

## Common

### 1. Why overrides exist

Vendor kits ship usable admin UIs with **UiKit defaults** (blue/slate tokens, Bootstrap-oriented form themes, standalone layouts). Beacon needs:

| Problem | Host fix |
|---------|----------|
| Kit `:root` / `nowo-ui.css` blue primary fights Beacon moss/sand | Remap under `.kit-admin` (`kit/_kit_admin_styles.html.twig`); avoid re-appending `nowo-ui.css` after host CSS |
| Kit pages use a standalone layout (no Administration sidebar) | `layout_template` → `templates/kit/*_layout.html.twig` extending `_kit_admin_layout.html.twig` |
| List/detail chrome differs from Menus / Issues admin | Host rewrite of index/show with `kit_admin_header_actions`, `.panel`, `btn-*`, `ui.table` / `_action_inner` |
| Bootstrap form theme / duplicate page titles | FormKit `beacon` / kit profiles + panel chrome; drop `bootstrap_5_layout` where forked |
| UiKit shell grid classes fight `app-shell` | Fork `_shell_*` to use Beacon `app-*` classes; keep only `data-nowo-ui-*` hooks |
| Burger / row actions painted with slate utilities | Fork burger; set `nowo_ui_kit.row_actions_display` and remap `.btn-sm` under `.kit-admin` |
| Flashes / pagination must match product UX | Fork `_toasts` / `_pagination` (Stimulus + Beacon classes) |

**Prefer** config + `templates/kit/` layout bridges. **Fork** a full page only when layout/CSS remaps are not enough. Prefer pushing reusable hooks **upstream** so forks can shrink later.

### 2. Two layers: layout vs page fork

```
config/packages/nowo_*.yaml
  layout_template: kit/…_layout.html.twig   ← host shell (sidebar, header, .kit-admin)
       │
templates/kit/_kit_admin_layout.html.twig   ← wrap via body_before / body_after (not body)
       │
templates/bundles/Nowo*Bundle/…             ← optional page/partial forks
vendor/nowo-tech/…/templates/…              ← original (behaviour source of truth)
```

Kit pages often define `{% block body %}` themselves. Host kit layouts therefore wrap with **`body_before` / `body_after`**, not by filling `body`. See comments in `kit/_kit_admin_layout.html.twig`.

Do **not** use `@!Nowo…` bang namespaces — kit TwigPathsPass does not register them.

### 3. Shared patterns

- **Administration list chrome (“Menus pattern”)**  
  Page title/intro from kit layout; primary/secondary CTAs in `kit_admin_header_actions`; results in `.panel`; row actions via `btn-ghost` / `btn-danger` + `@NowoUiKitBundle/partials/_action_inner.html.twig` and `nowo_ui_kit.row_actions_display`.

- **CSS load order**  
  Host remaps first (or re-include after a forced `nowo-ui.css` append). Re-loading vendor `nowo-ui.css` last reasserts blue tokens (header burger, primary buttons).

- **Modals**  
  Keep vendor modal **ids**, `data-*` hooks, and fetch URLs. Paint body/footer with Beacon `confirm-dialog` / UiKit modal macros (`shared/_confirm_dialog.html.twig` where applicable). Kit JS (`dashboard.js`, etc.) must keep working.

- **Forms**  
  Use Form Types + `form_start` / `form_end` / `form/_fields.html.twig` (see CONTRIBUTING REQ-TWIG-005). CSRF-only POSTs: `csrf_action_form()` / `CsrfOnlyType`.

- **Shared includes**  
  Tabs → `shared/_tabs.html.twig`; pagination helper → `kit/_pagination.html.twig` → UiKit `_pagination`.

### 4. Checklist when upgrading kits

1. `composer update` the kit(s); note changelog for Twig path / block / variable / form name changes.
2. Diff vendor templates vs host forks:
   ```bash
   diff -u vendor/nowo-tech/<pkg>/templates/... templates/bundles/NowoXxxBundle/...
   ```
3. Re-apply **behaviour** from vendor (new fields, CSRF names, Live Component actions, modal ids) into the host fork; keep Beacon chrome.
4. If the kit added blocks you can override, prefer shrinking the fork to `{% extends '@Nowo…/…' %}` + block overrides.
5. `bin/console assets:install` && `bin/console cache:clear`.
6. Smoke: Menus, Breadcrumbs, Cookie consent admin + public modal, Routing `/admin/_routing/`, HTTP log, Site Backup panel, AuthKit login/reset, PWA install prompt, API docs if Nelmio changed.
7. Fast AuthKit suite: `make kit-smoke`.

### 5. What must not break

| Preserve | Why |
|----------|-----|
| `data-nowo-ui-*`, `data-bk-*`, `btn-bk-*`, Live `#action` hooks | Kit JS / Stimulus / Live Components |
| Modal element **ids** and fetch `?_partial=1` URLs | Dashboard / Routing import & forms |
| CSRF token field names and route names | POST actions fail silently or 419 |
| Form variable names from controllers (`export_form`, …) | RoutingKit ≥ 1.4.0 and similar |
| `row_actions_display` + `_action_inner` contract | Icon vs text labels under kit CSS |
| `.kit-admin` wrapper / token remap | Visual regression to UiKit blue |
| Auth `auth_panel` / layout_template contract | Guest pages keep brand chrome |

---

## Bundles

### NowoUiKitBundle

**Path:** `NowoUiKitBundle/partials/`

| File | Why |
|------|-----|
| `_shell.html.twig`, `_shell_open.html.twig`, `_shell_close.html.twig` | Product chrome uses `app-shell` / `app-header` / `app-sidebar`. Vendor `nowo-ui-shell` grid fights Beacon layout. Keep `data-nowo-ui-*` only. |
| `_burger.html.twig` | Skip `ui.burger()` slate/blue button utilities; use `app-sidebar-toggle`. |
| `_toasts.html.twig` | Same UiKit toast contract + `|trans` for plain flashes + Stimulus `toast-stack`. |
| `_pagination.html.twig` | Beacon `table-pagination` pills (`«` / `»` + page numbers). |

**Upgrade notes:** Any change to shell data attributes, toast markup, or pagination variable shape in UiKit must be mirrored here. Prefer upstreaming Beacon-friendly options so these forks can shrink.

**Config:** `config/packages/nowo_ui_kit.yaml` (`css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: text`).

---

### NowoDashboardMenuBundle

**Path:** `NowoDashboardMenuBundle/dashboard/` (+ `components/`)

| File | Why |
|------|-----|
| `base.html.twig` | Extend host `kit/menu_dashboard_layout.html.twig`; **do not** append `nowo-ui.css` again. |
| `index.html.twig`, `show.html.twig`, `show_items_reorder.html.twig` | Administration list/detail/reorder chrome; CTAs in header; keep kit modal/JS/routes. |
| `_menu_dashboard_modals.html.twig`, `_*_partial.html.twig` | Beacon confirm-dialog panel/header/body/actions; preserve modal ids and form hooks. |
| `components/ItemFormLiveComponent.html.twig` | Live item form actions styled as Beacon dialogs; keep `live#action` hooks. |

**Problems solved:** Vendor dashboard looks like a standalone UiKit app; Beacon needs Menus inside Administration with consistent buttons, tables, and modals.

**Upgrade notes:** Diff every forked dashboard template after Menu bundle bumps. New item fields or modal partials in vendor must land in host partials. Sortable / CSRF / Live actions must stay aligned.

**Layout:** `nowo_dashboard_menu.dashboard.layout_template` → `kit/menu_dashboard_layout.html.twig`.

---

### NowoBreadcrumbKitBundle

**Path:** `NowoBreadcrumbKitBundle/dashboard/`

| File | Why |
|------|-----|
| `base.html.twig` | Same CSS order rule as Menus; host breadcrumb layout. |
| `collection/index.html.twig`, `item/index.html.twig` | Menus-pattern lists; wider actions column (`min-w-*`, nowrap text chips); `btn-bk-*` / `data-bk-*` unchanged. |

**Upgrade notes:** Watch collection/item modal URLs and row-action markup. Prefer layout-only if upstream adopts the same chrome hooks.

**Layout:** `kit/breadcrumb_dashboard_layout.html.twig`.

---

### NowoRoutingKitBundle

**Path:** `NowoRoutingKitBundle/panel/`

| File | Why |
|------|-----|
| `base.html.twig` | Host layout; no duplicate `nowo-ui.css`. |
| `index.html.twig` | List chrome + export / clear-cache / import / delete forms (RoutingKit ≥ 1.4.0 form vars). |
| `form.html.twig` | Create/edit form chrome aligned with Administration. |

**Upgrade notes:** Form variable renames (`export_form`, `clear_cache_form`, `import_form`, `delete_forms`) break the host index — sync from vendor changelog. Keep import modal id `modal-routing-import` in sync with kit JS if any.

**Layout:** `kit/routing_kit_panel_layout.html.twig`.

---

### NowoCookieConsentBundle

**Path:** `NowoCookieConsentBundle/admin/`

| File | Why |
|------|-----|
| `base.html.twig` | Re-include `kit/_kit_admin_styles.html.twig` **after** vendor `nowo-ui.css` so remaps win. |
| `_admin_area_nav.html.twig`, `config/_settings_section_tabs.html.twig` | Beacon `shared/_tabs` (Appearance / roles pattern), not vendor tab chrome. |
| `config/settings.html.twig`, `cookie_definition/form.html.twig` | FormKit/beacon theme; drop vendor `bootstrap_5_layout`; panel chrome. |
| `cookie_definition/index.html.twig`, `_table.html.twig` | Avoid duplicate h1; UiKit table + `row_actions_display`. |
| `_pagination.html.twig` | Delegate to `kit/_pagination.html.twig`. |

**Problems solved:** Bootstrap form theme and blue UiKit defaults on consent admin; inconsistent tabs vs rest of Beacon settings UI.

**Upgrade notes:** If upstream drops Bootstrap form themes and adopts host-friendly tabs, delete or shrink these forks. Public consent **modal** should stay vendor + host SCSS (`_cookie_consent.scss`) unless explicitly forked.

**Layout:** `kit/cookie_consent_admin_layout.html.twig`.

**Legal reminder:** public consent UX belongs with privacy/terms; do not add non-essential cookies without this bundle’s consent flow.

---

### NowoHttpLogBundle

**Path:** `NowoHttpLogBundle/admin/`

| File | Why |
|------|-----|
| `index.html.twig`, `show.html.twig` | Administration list/detail chrome; export/purge CTAs in header. |
| `_filter.html.twig` | Issues/admin filter standard: widgets + placeholders, Beacon `input` / `btn`. |

**Upgrade notes:** Filter field names and criteria shapes must match the admin controller. Detail blocks (headers/body) should follow vendor when new logged fields appear.

**Layout:** `kit/http_log_admin_layout.html.twig` (extra `.kit-admin.nowo-http-log` CSS for details/code).

---

### NowoSiteBackupBundle

**Path:** `NowoSiteBackupBundle/panel/`

| File | Why |
|------|-----|
| `index.html.twig` | UiKit table + icon row actions; panel POSTs need extra hidden `action` fields. |
| `history.html.twig` | Same host panel layout / chrome expectations. |

**Upgrade notes:** Preserve hidden `action` values and CSRF field names on create/restore/delete. Prefer layout-only if upstream table actions become configurable.

**Layout:** `kit/site_backup_panel_layout.html.twig` (setup uses `kit/site_backup_setup_layout.html.twig` — usually no page fork).

---

### NowoAuthKitBundle

**Path:** `NowoAuthKitBundle/`

| File | Why |
|------|-----|
| `layout.html.twig` | Guest shell + brand hero; vendor pages fill `auth_panel`. Loads AuthKit + password-toggle assets; cookie preferences bubble when enabled. |
| `security/reset_request.html.twig` | Host `form/_fields.html.twig` + brand title pattern. |
| `security/magic_login_confirm.html.twig` | Confirm flow in host auth panel. |
| `security/qr_login_show.html.twig` | QR challenge UI + Stimulus controller hooks / i18n. |

**Intended strategy (CONTRIBUTING):** prefer **layout only** and AuthKit YAML (`form_theme`, CSS). Security page forks should stay minimal; push block hooks upstream when possible (AuthKit 1.11+ `auth_panel`).

**Upgrade notes:** Diff forked `security/*` against vendor on every AuthKit bump (form types, route helpers `auth_kit_route_params()`, Stimulus values). Run `make kit-smoke`.

---

### NowoPwaBundle

**Path:** `NowoPwaBundle/pwa/`

| File | Why |
|------|-----|
| `install_prompt.html.twig` | Dismissible prompt; BEM root token; brand mark via `_brand_icon.html.twig` / SiteAppearance. |
| `install_links.html.twig` | Preferences install/uninstall with Beacon `btn-*` (not default PWA blue). |

**Intended strategy:** prefer YAML (`install_prompt.*` button classes, mark asset) + host SCSS. These forks exist for brand mark and button chrome — re-check after PWA bundle bumps whether YAML covers the need.

**Upgrade notes:** Keep `data-pwa-install-action` / dismiss keys / BEM structure required by bundle JS.

---

### NelmioApiDocBundle

**Path:** `NelmioApiDocBundle/SwaggerUi/index.html.twig`

Swagger UI inside Beacon `base.html.twig` (Administration shell) instead of Nelmio’s standalone page. Keeps intro copy, JSON link, and CSP-safe `swagger_data` embedding.

**Upgrade notes:** After Nelmio upgrades, compare asset helpers (`nelmioAsset`), swagger script init, and required blocks (`swagger_data`, `svg_icons`).

---

### TwigBundle

**Path:** `TwigBundle/Exception/error*.html.twig`

All mapped statuses extend `error/layout.html.twig` so error pages share Beacon error chrome (not Symfony’s default exception template).

| Files |
|-------|
| `error.html.twig` (fallback), `error400`, `error401`, `error403`, `error404`, `error408`, `error429`, `error500`, `error502` |

**Upgrade notes:** Rarely affected by kit upgrades; revisit if Symfony changes exception template names or if `error/layout.html.twig` blocks change.

---

## Quick reference: layout YAML

| Bundle config key | Host layout |
|-------------------|-------------|
| `nowo_dashboard_menu.dashboard.layout_template` | `kit/menu_dashboard_layout.html.twig` |
| `nowo_breadcrumb_kit.dashboard.layout_template` | `kit/breadcrumb_dashboard_layout.html.twig` |
| `nowo_routing_kit.web_ui.layout_template` | `kit/routing_kit_panel_layout.html.twig` |
| `nowo_cookie_consent.admin.layout_template` | `kit/cookie_consent_admin_layout.html.twig` |
| `nowo_http_log.web_ui.layout_template` | `kit/http_log_admin_layout.html.twig` |
| `nowo_site_backup.panel.layout_template` | `kit/site_backup_panel_layout.html.twig` |
| `nowo_site_backup.setup.layout_template` | `kit/site_backup_setup_layout.html.twig` |

Token remap and shared kit chrome: `kit/_kit_admin_styles.html.twig`, `kit/_pagination.html.twig`, Vite entry `kit-admin`.
