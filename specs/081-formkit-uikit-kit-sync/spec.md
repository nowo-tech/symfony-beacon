# Feature Specification: FormKit / UiKit kit sync

**Feature Branch**: `081-formkit-uikit-kit-sync`  
**Created**: 2026-08-05  
**Status**: Implemented (as-built)  

**Input**: Install and pin `nowo-tech/ui-kit-bundle` as the host admin/product UI kit; bump FormKit, AuthKit, RoutingKit, and UiKit consumers (DashboardMenu, BreadcrumbKit, CookieConsent, HttpLog, SiteBackup, …) so kits delegate CSS/macros/forms to UiKit + FormKit; keep Beacon visual identity via Tailwind tokens and `.kit-admin` remaps (not Bootstrap default).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - UiKit host + kit pins (Priority: P1)

As a maintainer, Composer requires `nowo-tech/ui-kit-bundle` **1.7.0** with host config `css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: text`, and pins FormKit **2.2.0**, plus UiKit-consuming kits. Baseline from this feature: AuthKit **1.15.0**, RoutingKit **1.3.0**, DashboardMenu **2.0.1**, BreadcrumbKit **2.1.3**, CookieConsent **1.6.0**, HttpLog **1.1.0**, SiteBackup **1.10.0**. Later pins (as of 2026-08-12): AuthKit **1.17.0**, RoutingKit **1.4.0**, DashboardMenu **2.1.1**, CookieConsent **≥ 1.6.3**, SiteBackup **1.10.1** — see CHANGELOG. Kit admin YAML uses `tailwind` + `ux_icon` (not legacy `custom` / `none`).

**Independent Test**: `composer show nowo-tech/ui-kit-bundle` reports 1.7.0; `debug:config nowo_ui_kit` shows `row_actions_display: text`; container boots (`lint:container`).

**Acceptance Scenarios**:

1. **Given** the pins above, **When** `composer update` those packages, **Then** the container boots and `lint:container` succeeds.
2. **Given** kit admin pages (Menu / Breadcrumb / Cookie / HttpLog / Routing / SiteBackup), **When** CSS loads, **Then** styles come from host kit layouts + `.kit-admin` token remap (Vite `kit-admin` stays Bootstrap-free); host kit `base` overrides MUST NOT re-append vendor `nowo-ui.css` after app CSS.
3. **Given** `row_actions_display: text`, **When** a kit list shows row actions, **Then** actions show text labels via `_action_inner` on `btn-sm btn-label` (kit CSS MUST NOT square-crush them) and stay on one horizontal line when the actions column is wide enough; `icon` / `icon_text` remain valid display modes.

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
3. **Given** Ops defaults, **When** section tabs switch, **Then** routes under `/admin/ops-defaults/{section}` stay active via shared `_tabs`.

---

### User Story 6 - Kit admin host bridges (Priority: P2)

As `ROLE_ADMIN`, kit dashboards keep Beacon Administration look: host `templates/kit/*_layout.html.twig` + `_kit_admin_styles` token remap + `kit_admin_header_actions` for primary CTAs. Host forks under `templates/bundles/Nowo*Bundle/` align Menu, Breadcrumb, RoutingKit, Http Log (and Cookie / SiteBackup where still UiKit-macro based) to that chrome. Prefer shrinking forks after upstream catches up (`docs/CONTRIBUTING.md`).

**Independent Test**: Smoke Menu, Breadcrumb, Cookie admin, HttpLog, RoutingKit, SiteBackup panel — chrome matches Administration lists; lists paginate; row actions honor `row_actions_display: text` without crushed labels; modals open under CSP.

**List / filter chrome (host convention)**:

1. **Tables**: Kit catalog and definition lists MUST use an explicit Beacon `panel` (or `section.panel`) card with a sand/ink token table (`border-[var(--color-sand)]`, `row-hover`, actions last / `text-end`). Empty state is an in-table row. Menu / Breadcrumb / Routing / Http Log host forks MAY use native table markup (Administration pattern) instead of `ui.table` macros; Cookie Consent may keep UiKit table macros until rewritten. Do **not** rely on `data-kit-split-filters` when the page already wraps the list in `panel`. Catalog indexes MUST NOT use `ui.list` as primary results chrome.
2. **Row actions**: Prefer host `btn-ghost` / `btn-danger` + `_action_inner` with `btn-sm btn-label` when display is `text` (or `btn-icon` when `icon`). Avoid `ui.action` / `nowo-ui-btn--sm` square clusters for text mode — kit CSS under `.nowo-ui-row-actions--text` MUST force auto width and **prefer a single horizontal line** (`flex-wrap: nowrap`; panel may `overflow-x-auto`). Actions columns MUST NOT use `w-0` when text labels would stack; Breadcrumb collection/item tables use a wider actions column (`min-w-*`) so several chips fit per row. Keep kit JS hooks (`btn-*-menu`, `btn-bk-*`, `data-token`, modal ids).
3. **Header CTAs**: Primary toolbars (Export / Import / New / Purge / …) SHOULD sit in `kit_admin_header_actions` beside the page heading (not a duplicate toolbar under the filter when the header slot exists).
4. **Multi-field filters** (Http Log): MUST follow product Issues/admin standard — `form_widget` + placeholders / `aria-label` (no visible labels), Tailwind grid, product `.input` + `btn-ghost` for Filter / Clear, form wrapped in `panel`. MUST NOT use `form_row` label chrome for these filters.
5. **RoutingKit panel**: Export / Clear cache / Import / New in header (secondary → primary); Import modal; host i18n; no duplicate intro under `kit_admin_intro`; host `panel/base` MUST NOT re-append `nowo-ui.css`.
6. **Breadcrumb kit layout**: hub intro `admin.hub.breadcrumbs` MUST translate with domain `messages`.

---

## Requirements *(mandatory)*

- **FR-001**: Pin (exact, as in `composer.json`) at least: `ui-kit-bundle` **1.7.0**, `form-kit-bundle` **2.2.0**, `auth-kit-bundle` **≥ 1.15.0** (host **1.17.0**), `routing-kit-bundle` **≥ 1.3.0** (host **1.4.0**), `dashboard-menu-bundle` **≥ 2.0.1** (host **2.1.1**), `breadcrumb-kit-bundle` **2.1.3**, `cookie-consent-bundle` **≥ 1.6.3**, `http-log-bundle` **1.1.0**, `site-backup-bundle` **≥ 1.10.0** (host **1.10.1**). Exact pins live in `composer.json` / CHANGELOG.
- **FR-002**: Host `nowo_ui_kit` MUST set `css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: text` (icon / icon_text remain supported). Product `base.html.twig` MAY load `nowo-ui.css` / orb JS from the `nowo_ui_kit` package; Beacon app CSS wins for product chrome. Kit dashboard `base` host overrides MUST NOT re-link `nowo-ui.css` after app CSS.
- **FR-003**: Host `nowo_form_kit` kit profiles MUST set kit catalogues and `auto_help` / `auto_placeholder: false` for kit-owned forms (see `docs/CONTRIBUTING.md`).
- **FR-004**: List pagination MUST converge on `kit/_pagination` / UiKit `_pagination`; `shared/_table_pagination.html.twig` remains a thin alias for product lists.
- **FR-005**: RoutingKit host `panel/form` override MUST render the Symfony form from 1.3+; MUST NOT keep a parallel hand-rolled field list that ignores `form`.
- **FR-006**: AuthKit fields rely on kit **≥ 1.14** (`help`/`placeholder: false`) plus host profile auto flags; AuthKit **1.15** encrypts OAuth secrets/tokens with Halite (run `doctrine:encrypt:database` on upgrade).
- **FR-007**: Authenticated product shell MUST compose UiKit `_shell_open` / `_shell_close` (or equivalent) with Beacon slots; flashes / tabs / pagination MUST prefer UiKit partials over duplicated Beacon-only markup.
- **FR-008**: Kit admin UIs MUST use `css_framework: tailwind` + `.kit-admin` token remap; Vite `kit-admin` MUST NOT import Bootstrap.
- **FR-009**: BreadcrumbKit **2.1+** schema (`path_pattern` / `match_attributes`) MUST be applied via MDK migration; re-seed platform breadcrumbs after upgrade.
- **FR-010**: Document pins, profiles, shell/pagination strategy, and upgrade smoke in `docs/CHANGELOG.md` / `docs/CONTRIBUTING.md` / `docs/UPGRADING.md`.
- **FR-011**: Kit admin list pages (Menu index/show, Breadcrumb collections/items, RoutingKit definitions, Http Log results; Cookie definitions until rewritten) MUST use an explicit Beacon `panel` list card with actions last; catalog indexes MUST NOT use `ui.list` as the primary results chrome. Administration-chrome forks use sand/ink token tables + `_action_inner` row actions. Breadcrumb actions columns MUST be wide enough (`min-w-*`, no `w-0`) for multiple text chips on one line; `.nowo-ui-row-actions--text` MUST use `flex-wrap: nowrap` (horizontal scroll via panel when needed).
- **FR-012**: Http Log admin filters MUST use widget+placeholder filter chrome (Issues/admin) inside a `panel` card; Filter / Clear use host `btn-ghost`; inputs use product `.input` (kit remap under `.kit-admin` still applies to any leftover `nowo-ui-input`).
- **FR-013**: Kit admin primary CTAs on Administration-chrome forks MUST use host `btn-primary` / `btn-ghost` / `btn-danger` (and `kit_admin_header_actions` when applicable). Surfaces still on UiKit macros MUST remap `nowo-ui-btn--*` / `bg-blue-600` to Beacon moss under `.kit-admin`.
- **FR-014**: Kit admin host `<style>` blocks MUST carry `csp_nonce()` when the app CSP uses style nonces. Dashboard Menu MUST NOT load CDN Stimulus (`stimulus_script_url` empty / Vite + `window.Stimulus`). Kit `<dialog class="nowo-ui-modal">` MUST portal via `kit-admin` (`showModal` bridge) under CSP.

## Assumptions

- FormKit **2.2** inherits form-level `translation_domain` into fields via `FormOptionsTrait::addWithDefaults` when the field omits the option.
- Settings Mailer / Mercure / Ops / Social credential Types extending `FormKitAbstractType` remain under `077` / `084-ops-env-to-db`.
- Legal/cookie **public** modal stays on `nowo-tech/cookie-consent-bundle` (`ui_theme: tailwind`); this feature does not rewrite legal copy.
- Host Twig forks listed in CONTRIBUTING are accepted interim bridges, not a permanent pattern — prefer layout-only overrides.
- Product multi-field filters (Issues, analytics with labels, audit timeline) keep their own chrome; **Http Log** aligns with Issues/admin (no labels), not analytics/audit.

## Amendment (Cookie Consent public-only, 2026-08-11)

Cookie Consent **≥ 1.6.3**: host `render_routes` whitelist limits consent markup to public shells (`legal_*`, `nowo_auth_kit_*`, `nowo_site_backup_setup*`, `guest_locale_switch`, `app_home_redirect`); authenticated product/admin routes MUST NOT render the modal. Auto-open uses `route_targeting_mode: only` on AuthKit entry routes. Twig guard: `nowo_cookie_consent_should_render()`. Session cookie inventory name MUST match `beacon.session_cookie_name` (`087` session amendment).

## Amendment (Kit admin tables + Http Log filters, 2026-08-12)

Host forks aligned to Beacon kit list/filter chrome:

- Menu / Breadcrumb collection indexes: `ul` catalogs → `panel nowo-ui-table-wrap` tables; Breadcrumb items: actions column last; Menu/Breadcrumb layouts drop `kit_admin_split_filters` (explicit panel in page Twig).
- RoutingKit panel index: same table card; Import modal; toolbar order; host `NowoRoutingKitBundle.*` keys for Export/Import/yes/no/confirm/actions; no duplicate `panel.subtitle` under hub intro.
- Breadcrumb dashboard layout: `admin.hub.breadcrumbs|trans({}, 'messages')`.
- Demo fixture ES labels for breadcrumb collection routes: `Migas de pan` (align trail with nav).
- Http Log `_filter`: Issues-style widgets + placeholders; `http_log` FormKit profile uses `nowo-ui-input` + Tailwind utilities.

## Amendment (Dashboard Menu Beacon chrome, 2026-08-12)

Host rewrites `@NowoDashboardMenuBundle/dashboard/{index,show,show_items_reorder}` to Administration product chrome (`panel`, sand/ink table rows, actions last, `kit_admin_header_actions`). Row actions MUST use host `btn-ghost` / `btn-danger` + `_action_inner` (`btn-sm btn-label` in text mode) so `nowo_ui_kit.row_actions_display` applies without square-crush. Kit business hooks preserved: modal ids, `btn-edit-menu` / `btn-delete-menu` / `btn-*-item`, CSRF `data-token`, `__nowoDashboardMenuConfig`, Sortable form ids. Layout shell remains `kit/menu_dashboard_layout.html.twig`.

## Amendment (Kit Administration chrome sync, 2026-08-12)

Extend Menu Administration chrome to Breadcrumb, RoutingKit, and Http Log host forks:

- **Config**: `nowo_ui_kit.row_actions_display: text`; kit CSS `.btn-sm.btn-label` / `.nowo-ui-row-actions--text` auto-width + `flex-wrap: nowrap` (no 28rem wrap cap; panel may scroll).
- **Breadcrumb**: host `dashboard/base` (no double `nowo-ui.css`); `collection/index` + `item/index` → panel tables, header CTAs, `_action_inner` text actions; actions column `min-w-[22rem]` / `min-w-[18rem]` (collections / items) so Ítems/JSON/Editar/Eliminar stay one line; keep `btn-bk-*` / modal hooks.
- **RoutingKit**: host `panel/base` + `index` / `form` → header CTAs, panel table, text row actions, Import modal; modal body portals on layout.
- **Http Log**: `admin/index|_filter|show` → header export/purge, panel results table, filters in `panel` with `.input` / `btn-ghost`.
- **CSP / modals** (cross-cutting): `ContentSecurityPolicySubscriber` `style-src-elem` (nonce) + `style-src-attr` (CSSOM); host kit `<style nonce>`; Dashboard Menu `stimulus_script_url: ''` + Vite Stimulus; `kit-admin` dialog portal + config JSON island boot before deferred kit `dashboard.js`.

Supersedes the earlier “UiKit table macros + `ui.btn` + icon-only” list convention for Menu / Breadcrumb / Routing / Http Log host forks (Cookie Consent / SiteBackup may still use UiKit macros until rewritten).

## Related

- `064-routing-kit` — RoutingKit install; this feature advances panel FormKit + pagination.
- `077-form-type-field-loop` — host Form Type field loop; FormKit remains preferred for attrs.
- `090-csrf-symfony-forms` — host CSRF via Symfony Forms / `CsrfOnlyType`.
- `084-ops-env-to-db` — Ops defaults UI (section tabs + FormKit Types).
- `080-dashboard-aside-panels` / `079-dashboard-assignments` — list pagination convention.
- `056-setup-wizard` — SiteBackup host layouts stay Tailwind + UiKit tokens.
- `037-authkit-identity-migration` — AuthKit pin / FormKit `auth_kit` profile.
- `086-dry-refactor` — platform `.checkbox` + FormKit checkbox class; password-toggle gap/eye chrome; shared Twig shells (confirm / admin list / feed); does not change FormKit package pins.
- `004-issues` — product filter chrome (widget + placeholder) that Http Log host filters mirror.

## Out of Scope

- Replacing every kit Twig host fork with pure vendor templates in this release (shrink over time).
- Changing FormKit/AuthKit/UiKit/RoutingKit internals beyond consuming Packagist releases.
- Migrating guest/login layout fully onto UiKit shell (AuthKit keeps `guest_shell` / layout override).
- Legal notice / privacy copy rewrites (remind operators to keep English legal pages current when adding tracking).
- Forcing analytics/audit filters to drop labels (those surfaces keep labeled fields by design).
