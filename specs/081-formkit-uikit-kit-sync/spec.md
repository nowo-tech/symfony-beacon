# Feature Specification: FormKit / UiKit kit sync

**Feature Branch**: `081-formkit-uikit-kit-sync`  
**Created**: 2026-08-05  
**Status**: Implemented (as-built)  

**Input**: Install and pin `nowo-tech/ui-kit-bundle` as the host admin/product UI kit; bump FormKit, AuthKit, RoutingKit, and UiKit consumers (DashboardMenu, BreadcrumbKit, CookieConsent, HttpLog, SiteBackup, …) so kits delegate CSS/macros/forms to UiKit + FormKit; keep Beacon visual identity via Tailwind tokens and `.kit-admin` remaps (not Bootstrap default).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - UiKit host + kit pins (Priority: P1)

As a maintainer, Composer requires `nowo-tech/ui-kit-bundle` **1.7.0** with host config `css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: icon`, and pins FormKit **2.2.0**, AuthKit **1.15.0**, RoutingKit **1.3.0**, plus UiKit-consuming kits (DashboardMenu **2.0.1**, BreadcrumbKit **2.1.3**, CookieConsent **1.6.0**, HttpLog **1.1.0**, SiteBackup **1.10.0**, and related widgets). Kit admin YAML uses `tailwind` + `ux_icon` (not legacy `custom` / `none`).

**Independent Test**: `composer show nowo-tech/ui-kit-bundle` reports 1.7.0; `debug:config nowo_ui_kit` shows `row_actions_display: icon`; container boots (`lint:container`).

**Acceptance Scenarios**:

1. **Given** the pins above, **When** `composer update` those packages, **Then** the container boots and `lint:container` succeeds.
2. **Given** kit admin pages (Menu / Breadcrumb / Cookie / HttpLog / Routing / SiteBackup), **When** CSS loads, **Then** styles come from `asset('…', 'nowo_ui_kit')` / vendor `nowo-ui.css` with Beacon `--nowo-ui-*` remap under `.kit-admin` (Vite `kit-admin` stays Bootstrap-free).
3. **Given** `row_actions_display: icon`, **When** a kit list shows row actions, **Then** actions are icon-only (label visually hidden + `aria-label` / title).

---

### User Story 2 - FormKit kit profiles (Priority: P1)

As a maintainer, `nowo_form_kit` defines kit profiles (`auth_kit`, `dashboard_menu`, `breadcrumb_kit`, `http_log`, `cookie_consent`, `routing_kit`) with each kit’s `translation_domain` and `auto_help` / `auto_placeholder: false` (FormKit **≥ 2.2**), so AuthKit and other kit forms do not render raw missing help/placeholder keys.

**Independent Test**: Open AuthKit login and RoutingKit create form; labels translate; no raw `*.help` / `*.placeholder` keys appear under fields that only set labels.

**Acceptance Scenarios**:

1. **Given** host `auth_kit` / `routing_kit` profiles with `auto_help` and `auto_placeholder` false, **When** login or routing panel form renders, **Then** fields show labels without empty convention help/placeholder noise.
2. **Given** FormKit **2.2**, **When** a field omits `translation_domain`, **Then** it inherits the form-level domain via `FormOptionsTrait::addWithDefaults`.

---

### User Story 3 - Shared list pagination (Priority: P1)

As a member, product and kit list pages use the same pager chrome: `«` / `»` with page numbers via `kit/_pagination.html.twig` → UiKit `_pagination` (vendor **1.7+**; host override may add Beacon `table-pagination` classes).

**Independent Test**: Open a multi-page product list and a kit admin list (e.g. RoutingKit panel or Cookie Consent admin); both show showing-text + numeric pager with `«` / `»`.

**Acceptance Scenarios**:

1. **Given** `total_pages > 1`, **When** a list uses `shared/_table_pagination` or `kit/_pagination`, **Then** previous/next are `«` / `»` with translated aria-labels and page links render.
2. **Given** UiKit **1.7+**, **When** a kit template includes `@NowoUiKitBundle/partials/_pagination`, **Then** markup uses `ul.pagination` / `li.page-item` (host may layer Beacon classes).

---

### User Story 4 - RoutingKit panel FormKit form (Priority: P1)

As `ROLE_ADMIN`, **When** I create or edit a locale path on `/admin/_routing/`, **Then** the form is a Symfony FormKit type (`RoutePathDefinitionType`, profile `routing_kit`); host Twig may keep Beacon card chrome but MUST use `form_start` / `form_row` / `form_end` (not a hand-rolled HTML form).

**Independent Test**: Open `/admin/_routing/new`, submit a valid path, confirm save; CSRF and field labels use `NowoRoutingKitBundle`.

**Acceptance Scenarios**:

1. **Given** RoutingKit **≥ 1.3**, **When** the panel form page renders, **Then** Twig receives a `form` view and paints Type children (host override must not regress to named HTML inputs only).
2. **Given** the panel index, **When** there are multiple pages, **Then** pagination uses UiKit / `kit/_pagination` convention.

---

### User Story 5 - Product chrome via UiKit partials (Priority: P2)

As a signed-in member, the app shell uses UiKit `_shell_open` / `_shell_close` from `base.html.twig` (Beacon brand, section nav, theme/locale/user chrome in slots). Flashes use UiKit `_toasts` (host override); page loader keeps Beacon overlay markup and embeds UiKit `_thinking_orb`. Product tabs use `shared/_tabs` → UiKit `_tabs` (e.g. Ops defaults sections).

**Independent Test**: Log in; confirm app shell aside/header; trigger a flash; navigate between Ops defaults sections; page-loader orb appears on navigation.

**Acceptance Scenarios**:

1. **Given** an authenticated session, **When** any app page renders, **Then** layout includes UiKit shell open/close with Beacon slot content (not a raw duplicate `app-shell` markup tree).
2. **Given** a Symfony flash, **When** the page renders, **Then** toasts come from `@NowoUiKitBundle/partials/_toasts` (host may override under `templates/bundles/NowoUiKitBundle/`).
3. **Given** Ops defaults, **When** section tabs switch, **Then** routes under `/settings/ops-defaults/{section}` stay active via shared `_tabs`.

---

### User Story 6 - Kit admin host bridges (Priority: P2)

As `ROLE_ADMIN`, kit dashboards keep Beacon look: host `templates/kit/*_layout.html.twig` + `_kit_admin_styles` token remap. Temporary host forks under `templates/bundles/Nowo*Bundle/` exist for pagination / icon row actions / FormKit filter loop where upstream does not yet match Beacon (Menu index, Breadcrumb collection/item indexes, Cookie pagination + definition table, HttpLog index/filter/show, RoutingKit panel, SiteBackup panel index/history). Prefer shrinking forks after upstream catches up (`docs/CONTRIBUTING.md`).

**Independent Test**: Smoke Menu, Breadcrumb, Cookie admin, HttpLog, RoutingKit, SiteBackup panel — chrome matches Beacon; lists paginate; row actions are icons.

## Requirements *(mandatory)*

- **FR-001**: Pin (exact, as in `composer.json`) at least: `ui-kit-bundle` **1.7.0**, `form-kit-bundle` **2.2.0**, `auth-kit-bundle` **1.15.0**, `routing-kit-bundle` **1.3.0**, `dashboard-menu-bundle` **2.0.1**, `breadcrumb-kit-bundle` **2.1.3**, `cookie-consent-bundle` **1.6.0**, `http-log-bundle` **1.1.0**, `site-backup-bundle` **1.10.0**.
- **FR-002**: Host `nowo_ui_kit` MUST set `css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: icon`. Product `base.html.twig` MAY load `nowo-ui.css` / orb JS from the `nowo_ui_kit` package; Beacon app CSS wins for product chrome.
- **FR-003**: Host `nowo_form_kit` kit profiles MUST set kit catalogues and `auto_help` / `auto_placeholder: false` for kit-owned forms (see `docs/CONTRIBUTING.md`).
- **FR-004**: List pagination MUST converge on `kit/_pagination` / UiKit `_pagination`; `shared/_table_pagination.html.twig` remains a thin alias for product lists.
- **FR-005**: RoutingKit host `panel/form` override MUST render the Symfony form from 1.3+; MUST NOT keep a parallel hand-rolled field list that ignores `form`.
- **FR-006**: AuthKit fields rely on kit **≥ 1.14** (`help`/`placeholder: false`) plus host profile auto flags; AuthKit **1.15** encrypts OAuth secrets/tokens with Halite (run `doctrine:encrypt:database` on upgrade).
- **FR-007**: Authenticated product shell MUST compose UiKit `_shell_open` / `_shell_close` (or equivalent) with Beacon slots; flashes / tabs / pagination MUST prefer UiKit partials over duplicated Beacon-only markup.
- **FR-008**: Kit admin UIs MUST use `css_framework: tailwind` + `.kit-admin` token remap; Vite `kit-admin` MUST NOT import Bootstrap.
- **FR-009**: BreadcrumbKit **2.1+** schema (`path_pattern` / `match_attributes`) MUST be applied via MDK migration; re-seed platform breadcrumbs after upgrade.
- **FR-010**: Document pins, profiles, shell/pagination strategy, and upgrade smoke in `docs/CHANGELOG.md` / `docs/CONTRIBUTING.md` / `docs/UPGRADING.md`.

## Assumptions

- FormKit **2.2** inherits form-level `translation_domain` into fields via `FormOptionsTrait::addWithDefaults` when the field omits the option.
- Settings Mailer / Mercure / Ops / Social credential Types extending `FormKitAbstractType` remain under `077` / `084-ops-env-to-db`.
- Legal/cookie **public** modal stays on `nowo-tech/cookie-consent-bundle` (`ui_theme: tailwind`); this feature does not rewrite legal copy.
- Host Twig forks listed in CONTRIBUTING are accepted interim bridges, not a permanent pattern — prefer layout-only overrides.

## Related

- `064-routing-kit` — RoutingKit install; this feature advances panel FormKit + pagination.
- `077-form-type-field-loop` — host Form Type field loop; FormKit remains preferred for attrs.
- `084-ops-env-to-db` — Ops defaults UI (section tabs + FormKit Types).
- `080-dashboard-aside-panels` / `079-dashboard-assignments` — list pagination convention.
- `056-setup-wizard` — SiteBackup host layouts stay Tailwind + UiKit tokens.
- `037-authkit-identity-migration` — AuthKit pin / FormKit `auth_kit` profile.
- `086-dry-refactor` — platform `.checkbox` + FormKit checkbox class; password-toggle gap/eye chrome; shared Twig shells (confirm / admin list / feed); does not change FormKit package pins.

## Out of Scope

- Replacing every kit Twig host fork with pure vendor templates in this release (shrink over time).
- Changing FormKit/AuthKit/UiKit/RoutingKit internals beyond consuming Packagist releases.
- Migrating guest/login layout fully onto UiKit shell (AuthKit keeps `guest_shell` / layout override).
- Legal notice / privacy copy rewrites (remind operators to keep English legal pages current when adding tracking).
