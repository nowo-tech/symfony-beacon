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
2. **Given** FormKit-merged fields, **When** a field omits `translation_domain`, **Then** `FormOptionsMerger` applies the active profile’s domain (do not hardcode `translation_domain: form` on host Form Types).

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
4. **Multi-field filters** (Http Log / product): MUST follow product Issues/admin standard — FormKit profile `filter` (no FormKit labels), Tailwind grid, product `.input` + `btn-ghost` for Filter / Clear, form wrapped in `panel`. Paint fields with `form_row` via `{% include 'form/_fields.html.twig' %}` or an equivalent `not field.rendered` loop (`077`); the Beacon theme omits label chrome when `label` is false. MUST NOT hand-roll `form_widget` + `form_help` pairs as the default pattern.
5. **RoutingKit panel**: Export / Clear cache / Import / New in header (secondary → primary); Import modal; host i18n; no duplicate intro under `kit_admin_intro`; host `panel/base` MUST NOT re-append `nowo-ui.css`.
6. **Breadcrumb kit layout**: hub intro `admin.hub.breadcrumbs` MUST translate with domain `messages`.

---

## Requirements *(mandatory)*

- **FR-001**: Pin (exact, as in `composer.json`) at least: `ui-kit-bundle` **≥ 1.7.0** (host **1.8.0** after `101`), `form-kit-bundle` **≥ 2.3.0** (host **2.4.1** after `101` / v1.23.1), `auth-kit-bundle` **≥ 1.15.0** (host **1.17.0**), `routing-kit-bundle` **≥ 1.3.0** (host **1.4.0**), `dashboard-menu-bundle` **≥ 2.0.1** (host **2.1.1**), `breadcrumb-kit-bundle` **2.1.3**, `cookie-consent-bundle` **≥ 1.6.3** (host **1.9.0** after `101`), `http-log-bundle` **1.1.0**, `site-backup-bundle` **≥ 1.10.0** (host **1.13.0**). Exact pins live in `composer.json` / CHANGELOG.
- **FR-002**: Host `nowo_ui_kit` MUST set `css_framework: tailwind`, `icon_set: ux_icon`, `row_actions_display: text` (icon / icon_text remain supported). Product `base.html.twig` MAY load `nowo-ui.css` / orb JS from the `nowo_ui_kit` package; Beacon app CSS wins for product chrome. Kit dashboard `base` host overrides MUST NOT re-link `nowo-ui.css` after app CSS.
- **FR-003**: Host `nowo_form_kit` kit profiles MUST set kit catalogues and `auto_help` / `auto_placeholder: false` for kit-owned forms (see `docs/CONTRIBUTING.md`). Product GET filters MUST use profile `filter` via `AbstractGetFilterType` (`#[FormKitConfig('filter')]`) — not `beacon` alone.
- **FR-003a**: Product FormKit profile `filter` contract: **label never** (`defaults.label: false` — Types MUST NOT pass `label` / FormKit label keys); **placeholder always** (`auto_placeholder: true` → `translations/form.*.yaml` `{block_prefix}.{field}.placeholder`); **help always** (`auto_help: true` → `{block_prefix}.{field}.help`) unless the field sets `help: false` (FormKit merger unsets it); **required always false** (`defaults.required: false`) except **`per_page`** (`required: true` — page-size select). Hidden fields MUST use `addHiddenFilterField()` (`placeholder` + `help: false` in options). MUST NOT leave `help: false` on `HiddenType` from profile defaults alone. Choice empty options use `addFilterSelect()` (auto `{prefix}.{field}.placeholder` for the empty choice; not pre-`trans()`); pass `placeholder: false` when the select has **no** empty option. Twig MUST paint filter fields with `form_row` via `{% include 'form/_fields.html.twig' %}` or an equivalent `not field.rendered` loop (`077`) — prefer that over hand-rolled `form_widget` + `form_help`. Optional visible captions stay in Twig/`messages` outside FormKit labels. Host MUST register snake-case `search` → `SearchType` in `nowo_form_kit.type_map` when using `addNamedField(..., 'search', ...)`.
- **FR-003b**: Host Form Types MUST NOT set `translation_domain` on FormKit-merged fields when the profile already supplies it. Overrides only for `false` (avoid double-`trans`), another catalogue (kit domain, or rare `choice_translation_domain: messages` for shared UI choice keys), or raw `$builder->add()` / compounds outside `mergeFieldOptions`. MUST NOT set `translation_domain: messages` on host field chrome (labels / placeholders / help) — put those keys in `translations/form.*.yaml`.
- **FR-003c**: Product FormKit profile `beacon` (`FormKitAbstractType`) contract: non-empty `getBlockPrefix()`; **auto** `label` / `placeholder` / `help` via FormKit → `{block_prefix}.{field}.{label|placeholder|help}` in `translations/form.*.yaml`; opt out with `help: false` and/or `placeholder: false` (and checkbox fields SHOULD pass `placeholder: false`). MUST NOT invent i18n chrome by stuffing `label` / `placeholder` into `attr` or by hard-coding locale prose. MUST NOT pass `|trans` output into options that still have a translation domain (double-translate). Twig MUST use `form_row` + `_fields` / unrendered loop (`077`).
- **FR-004**: List pagination MUST converge on `kit/_pagination` / UiKit `_pagination`; `shared/_table_pagination.html.twig` remains a thin alias for product lists.
- **FR-005**: RoutingKit host `panel/form` override MUST render the Symfony form from 1.3+; MUST NOT keep a parallel hand-rolled field list that ignores `form`.
- **FR-006**: AuthKit fields rely on kit **≥ 1.14** (`help`/`placeholder: false`) plus host profile auto flags; AuthKit **1.15** encrypts OAuth secrets/tokens with Halite (run `doctrine:encrypt:database` on upgrade).
- **FR-007**: Authenticated product shell MUST compose UiKit `_shell_open` / `_shell_close` (or equivalent) with Beacon slots; flashes / tabs / pagination MUST prefer UiKit partials over duplicated Beacon-only markup.
- **FR-008**: Kit admin UIs MUST use `css_framework: tailwind` + `.kit-admin` token remap; Vite `kit-admin` MUST NOT import Bootstrap.
- **FR-009**: BreadcrumbKit **2.1+** schema (`path_pattern` / `match_attributes`) MUST be applied via MDK migration; re-seed platform breadcrumbs after upgrade.
- **FR-010**: Document pins, profiles, shell/pagination strategy, and upgrade smoke in `docs/CHANGELOG.md` / `docs/CONTRIBUTING.md` / `docs/UPGRADING.md`.
- **FR-011**: Kit admin list pages (Menu index/show, Breadcrumb collections/items, RoutingKit definitions, Http Log results; Cookie definitions until rewritten) MUST use an explicit Beacon `panel` list card with actions last; catalog indexes MUST NOT use `ui.list` as the primary results chrome. Administration-chrome forks use sand/ink token tables + `_action_inner` row actions. Breadcrumb actions columns MUST be wide enough (`min-w-*`, no `w-0`) for multiple text chips on one line; `.nowo-ui-row-actions--text` MUST use `flex-wrap: nowrap` (horizontal scroll via panel when needed).
- **FR-012**: Http Log admin filters MUST use label-less FormKit filter chrome (Issues/admin: placeholders + help, `form_row` / theme) inside a `panel` card; Filter / Clear use host `btn-ghost`; inputs use product `.input` (kit remap under `.kit-admin` still applies to any leftover `nowo-ui-input`).
- **FR-013**: Kit admin primary CTAs on Administration-chrome forks MUST use host `btn-primary` / `btn-ghost` / `btn-danger` (and `kit_admin_header_actions` when applicable). Surfaces still on UiKit macros MUST remap `nowo-ui-btn--*` / `bg-blue-600` to Beacon moss under `.kit-admin`.
- **FR-014**: Kit admin host `<style>` blocks MUST carry `csp_nonce()` when the app CSP uses style nonces. Dashboard Menu MUST NOT load CDN Stimulus (`stimulus_script_url` empty / Vite + `window.Stimulus`). Kit `<dialog class="nowo-ui-modal">` MUST portal via `kit-admin` (`showModal` bridge) under CSP.

## Assumptions

- FormKit-merged field `translation_domain` comes from the active profile via `FormOptionsMerger` (not a host `FormKitAbstractType` default).
- Settings Mailer / Mercure / Ops / Social credential Types extending `FormKitAbstractType` remain under `077` / `084-ops-env-to-db`.
- Legal/cookie **public** modal stays on `nowo-tech/cookie-consent-bundle` (`ui_theme: tailwind`); this feature does not rewrite legal copy.
- Host Twig forks listed in CONTRIBUTING are accepted interim bridges, not a permanent pattern — prefer layout-only overrides.
- Product GET filters (Issues, dashboard asides, admin search/audit, analytics, ops overview, releases) extend `AbstractGetFilterType` (profile `filter`). Twig MAY keep caption spans in `messages` (e.g. analytics / audit); FormKit itself MUST NOT emit field labels. Product filter Twig paints via `form_row` + `_fields` / unrendered loop. **Http Log** host filters mirror that chrome under `.kit-admin` (kit Type may lag host product Types).

## Amendment (Cookie Consent public-only, 2026-08-11)

Cookie Consent **≥ 1.6.3**: host `render_routes` whitelist limits consent markup to public shells (`legal_*`, `nowo_auth_kit_*`, `nowo_site_backup_setup*`, `guest_locale_switch`, `app_home_redirect`); authenticated product/admin routes MUST NOT render the modal. Auto-open uses `route_targeting_mode: only` on AuthKit entry routes. Twig guard: `nowo_cookie_consent_should_render()`. Session cookie inventory name MUST match `beacon.session_cookie_name` (`087` session amendment).

## Amendment (Cookie Consent guest skin + bottom-left, 2026-08-15)

Public modal stays on vendor Twig (`ui_theme: tailwind`); Beacon MUST NOT fork `cookie_consent.tailwind.html.twig` for cosmetics.

**As shipped in v1.15.1**: skin + layout lived in host `assets/styles/_cookie_consent.scss` (CSP style nonces drop vendor injected CSS).

**As of `101` / CookieConsent ≥ 1.9**: skin ships in kit `nowo-cookie-consent.css` (originally linked from layouts with `data-nowo-cookie-consent-css`).

**As of `103` / v1.20.0**: kit skin is **bundled into Vite `app` CSS**; layouts set `data-nowo-cookie-consent-external-css="true"` and MUST NOT `<link>` `/bundles/nowocookieconsent/nowo-cookie-consent.css` (ad blockers strip that path). A thin host `_cookie_consent.scss` bridge is allowed for footer clearance + position fallbacks; Tailwind `@source`s vendor CookieConsent Twig. Seeded DB profile (`055` / `CookieConsentDemoSeeder`): **bottom left**, equal-weight buttons (unchanged).

Product doc: [`docs/product/LEGAL-AND-COOKIES.md`](../../docs/product/LEGAL-AND-COOKIES.md). Identity surface note: `002` amendments (2026-08-15 / 2026-08-17). Spec: `103-cookie-consent-vite-e2e-security`.

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

## Amendment (Product FormKit `filter` profile, 2026-08-13)

Host product GET filters converge on FormKit profile **`filter`** (`config/packages/nowo_form_kit.yaml`) via `App\Shared\Form\AbstractGetFilterType` + `#[FormKitConfig('filter')]` (as of `101`, host class extends kit `Nowo\FormKitBundle\Form\AbstractGetFilterType` and keeps dashboard helpers):

| Concern | Rule |
|---|---|
| label | Never (`defaults.label: false`); Twig captions in `messages` remain optional chrome |
| placeholder | Always — catalogue `translations/form.*.yaml` → `{block_prefix}.{field}.placeholder` |
| help | Always — catalogue `{prefix}.{field}.help` (`auto_help: true`); opt out with `help: false` |
| required | Always `false` (`defaults.required: false`), except **`per_page`** (`required: true` in field options) |
| domain | Profile `translation_domain: form` via merger; do not repeat on merged fields |
| search type | Host `nowo_form_kit.type_map.search` → Symfony `SearchType` (not in FormKit built-in map); use `addNamedField(..., 'search', …)` |
| CSRF / method | `csrf_protection: false`, `method: GET` (intentional — list filters are idempotent; mutable CSRF stays on POST FormKit / `CsrfOnlyType` under `090`) |
| Security note | Optional filter fields (`required: false`) are **form UX only** — they MUST NOT weaken authn/authz. Controllers still enforce project/role access; empty query values use existing safe defaults. This contract does **not** change access control or POST CSRF. |

As-built:

- Helpers on `AbstractGetFilterType`: `addHiddenFilterField`, `addFilterSelect` (empty-option placeholder auto; `placeholder: false` when no empty option), dashboard page/project/`addDashboardPerPage` (`required: true` on `per_page`; `row_attr` on Type for grid cells).
- Migrated Types include Issues index, dashboard Assignments/Mentions/New-in-release/Alerts/Activity, admin search/audit/ops, analytics, release focus/environment compare, `SearchQueryType` / `AdminSearchType` / `DashboardProjectSearchType`.
- Member alert preference Types extend `FormKitAbstractType` (`beacon`); nested event matrix compounds set domain once outside the merger.
- Cursor rule: `.cursor/rules/formkit-profiles.mdc`.
- Analytics / Issues / admin / dashboard filters: placeholders + help under `{block_prefix}.*` in `form.*.yaml`; Twig captions in `messages` where needed; fields via `form_row` + `_fields` / loop (no FormKit labels).

**Canonical standard**: The table above (plus FR-003a) is the product GET-filter standard. New exceptions MUST update FR-003a, this amendment, and `.cursor/rules/formkit-profiles.mdc` together — do not invent one-off `required` / label rules in a single Type.

## Amendment (Product `beacon` FormKit + Twig `form_row`, 2026-08-13)

Host product POST / settings forms on profile **`beacon`** and product GET filters on **`filter`** share Twig painting rules and catalogue ownership:

| Concern | `beacon` | `filter` |
|---|---|---|
| Base | `FormKitAbstractType` | `AbstractGetFilterType` + `#[FormKitConfig('filter')]` |
| Block prefix | Non-empty (`getBlockPrefix()`) | Non-empty (same) |
| Label | Auto → `{prefix}.{field}.label` in `form` | Never (FormKit); optional Twig/`messages` captions |
| Placeholder / help | Auto → `{prefix}.{field}.placeholder\|help`; opt out `placeholder: false` / `help: false` | Always auto unless opted out (hidden / Twig-owned) |
| Domain | Profile `form` via merger — do not set `messages` on field chrome | Same |
| Twig | `form_row` + `{% include 'form/_fields.html.twig' %}` / `not field.rendered` before actions (`077`) | Same (theme skips label when false) |
| Forbidden | i18n strings in `attr.placeholder` / hard-coded labels; hand-rolled `form_widget`+`form_help` as default | Passing `label` on Type fields; inventing placeholder keys that duplicate auto |

Theme notes (`templates/form/beacon_theme.html.twig`): `hidden_row` has no grid wrapper; `form_label` honors custom `label_attr.class`; **`color_row`** paints color inputs with swatch + live hex span (`data-color-hex-for`); **`checkbox_row`** uses platform `.checkbox-label` chrome.

As-built settings Types using `beacon` + form catalogue prefixes include project governance (`project_governance`), share create (`project_share_create`), read-token create (`project_read_token_create`), member add (`project_member_add`), group add (`project_group_add`), API key create (`project_api_key_create`), issue comment/priority/assignee (`issue_*`), admin group member add (`admin_group_member_add`), plus account/preferences/admin Forms already on FormKit.

**Canonical standard**: FR-003a + FR-003c + this amendment + `.cursor/rules/formkit-profiles.mdc`. Update all three when adding exceptions.

## Amendment (Twig `form_row` consolidation wave, 2026-08-13)

Host Twig catch-up after Types already on `beacon` / `filter`:

- Appearance Colors → theme `color_row` + `_fields`; Themes apply keeps CSRF-only paint (theme cards own `apply_theme`).
- Mentions `unread` → `form_row` with Twig/`messages` caption (filter profile still `label: false` on the Type).
- Admin role permissions matrix + role edit `_return`; AuthKit magic-login confirm host override; Dashboard Menu `_section` / `_modal` flags, reorder `tree`, import rest → `form_row` / `_fields`.
- E2E selectors updated for block-prefix field names/ids (`project_governance_*`, `project_share_create[*]`, `project_read_token_create[*]`, …).

**Standing `form_widget` exceptions** (interactive chrome; full list in `077`): member-alert Live `pref-switch` rows; issue duplicate combobox query; form theme block internals.

## Related

- `064-routing-kit` — RoutingKit install; this feature advances panel FormKit + pagination.
- `077-form-type-field-loop` — host Form Type field loop; FormKit remains preferred for attrs.
- `090-csrf-symfony-forms` — host CSRF via Symfony Forms / kit `CsrfOnlyType` (FormKit ≥ 2.4 / `101`); GET filters on host `AbstractGetFilterType` → kit base.
- `101-kit-csp-shared-helpers` — PhoneInput 1.3 / CookieConsent 1.9 / FormKit 2.4 / UiKit 1.8 CSP + shared helpers upstream.
- `105-authkit-security-kits` — FormKit **2.5.1** `addSlideToConfirmField`; drop host `type_map.search`.
- `nowo-tech/pwa-bundle` — Preferences install links = vendor + `_components.scss` BEM (no `install_links.html.twig` host fork as of 2026-08-17).
- `084-ops-env-to-db` — Ops defaults UI (section tabs + FormKit Types).
- `080-dashboard-aside-panels` / `079-dashboard-assignments` — list pagination convention.
- `056-setup-wizard` — SiteBackup host layouts stay Tailwind + UiKit tokens.
- `037-authkit-identity-migration` — AuthKit pin / FormKit `auth_kit` profile.
- `086-dry-refactor` — platform `.checkbox` + FormKit checkbox class; password-toggle gap/eye chrome; shared Twig shells (confirm / admin list / feed); does not change FormKit package pins.
- `091-member-push-preferences` — member alert preference forms on `FormKitAbstractType` (`beacon`).
- `004-issues` — product filter chrome (`form_row` + placeholders/help) that Http Log host filters mirror.
- `022-analytics-perf-ci` — analytics filter UI; FormKit `filter` + `analytics_filter.*` help/placeholder.
- `018-project-governance` — settings governance on `beacon` + `project_governance.*` form catalogue.

## Out of Scope

- Replacing every kit Twig host fork with pure vendor templates in this release (shrink over time). **PWA `install_links` fork was removed 2026-08-17** (vendor template + host SCSS BEM + Preferences hint beside `nowo_pwa_install_links()`). Keep `install_prompt` only while brand mark needs Twig.
- Changing FormKit/AuthKit/UiKit/RoutingKit internals beyond consuming Packagist releases (e.g. merger clearing `attr.placeholder` when only profile `placeholder: false` is set — host uses `addHiddenFilterField` instead).
- Migrating guest/login layout fully onto UiKit shell (AuthKit keeps `guest_shell` / layout override).
- Legal notice / privacy copy rewrites (remind operators to keep English legal pages current when adding tracking).
- Moving all Twig filter captions into FormKit labels (filters stay label-less at FormKit; captions stay in Twig/`messages` where desired).

## Amendment (FormKit 2.5.1 slide helper, 2026-08-25 / `105`)

FormKit **2.5.1** ships `addSlideToConfirmField()`. Host product Types MUST use that helper (Clear history `danger` profile) instead of raw `$builder->add()` for sliders. Snake-case `search` → `SearchType` is built-in since **2.4.0** — do not keep `nowo_form_kit.type_map.search`. Cursor rule `.cursor/rules/formkit-profiles.mdc` mentions the helper. See `specs/105-authkit-security-kits/`.
