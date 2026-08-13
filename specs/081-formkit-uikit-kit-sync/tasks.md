# Tasks: 081-formkit-uikit-kit-sync

**Status**: Done (as-built)

- [x] Add host `nowo-tech/ui-kit-bundle` **1.7.0** + `nowo_ui_kit.yaml` (`tailwind` / `ux_icon` / `row_actions_display: icon`)
- [x] Release/consume upstream FormKit **2.2.0**, AuthKit **1.15.0** (Halite OAuth secrets), UiKit **1.7.0**, RoutingKit **1.3.0**
- [x] Bump UiKit consumers: DashboardMenu **2.0.1**, BreadcrumbKit **2.1.3** (+ MDK migration), CookieConsent **1.6.0**, HttpLog **1.1.0**, SiteBackup **1.10.0**, related widgets
- [x] Beacon Composer exact pins + `composer update` for the packages above
- [x] `nowo_form_kit.yaml`: kit profiles + `auto_help` / `auto_placeholder: false` + `routing_kit`
- [x] Kit YAML `css_framework: tailwind` + `icon_set: ux_icon`; `_kit_admin_styles` token remap
- [x] Shared pagination: `kit/_pagination` → UiKit; product `_table_pagination` alias
- [x] Product chrome: `base.html.twig` UiKit `_shell_open`/`_shell_close`; `_toasts` / `_thinking_orb` / `_tabs` bridges; host UiKit partial overrides as needed
- [x] RoutingKit host `panel/form.html.twig` → Symfony/FormKit `form_*`; panel index pagination
- [x] Interim host forks: Menu / Breadcrumb / Cookie pagination+table / HttpLog filter loop / SiteBackup panel lists
- [x] Security follow-ups: OutboundUrlGuard A+AAAA; SiteBackup omit `.env`; PRODUCTION failed-queue + encrypt migrate notes
- [x] Docs: CHANGELOG, CONTRIBUTING, UPGRADING, ROADMAP 6.31; this spec + related specs (`037`, `064`, `077`, `079*`, `080`)
- [x] Kit admin tables: Menu / Breadcrumb catalogs → `panel nowo-ui-table-wrap` + UiKit table; items actions last; drop `kit_admin_split_filters` when panel is explicit
- [x] RoutingKit panel index: table card, Import modal, toolbar order, host i18n Export/Import/yes/no/confirm, no duplicate intro
- [x] Breadcrumb layout intro `admin.hub.breadcrumbs` via `messages`; fixture ES trail label `Migas de pan`
- [x] Http Log filters: Issues/admin label-less FormKit filter chrome (`form_row` / placeholders); FormKit `http_log` → `nowo-ui-input` + Tailwind; `ui.btn` CTAs
- [x] Pin bumps post-sync: AuthKit **1.17**, RoutingKit **1.4**, DashboardMenu **2.1.1**, CookieConsent **≥ 1.6.3**, SiteBackup **1.10.1** (see CHANGELOG); FR-001 floors updated
- [x] Dashboard Menu host rewrite: `index` / `show` / `show_items_reorder` → Administration chrome; kit JS/modals preserved
- [x] `nowo_ui_kit.row_actions_display: text` + kit CSS `btn-label` / `--text` auto-width (no square-crush)
- [x] Breadcrumb host rewrite: `dashboard/base` + `collection/index` + `item/index` → Administration chrome / header CTAs / text row actions
- [x] Breadcrumb actions column: wider `min-w-*` + kit `.nowo-ui-row-actions--text` nowrap so multiple text chips stay on one line
- [x] RoutingKit host rewrite: `panel/base` + `index` / `form` → Administration chrome / header CTAs / text row actions / Import modal portals
- [x] Http Log host rewrite: `admin/index|_filter|show` → header export/purge, panel results, filters in `panel` + `.input` / `btn-ghost`
- [x] CSP kit admin: style nonces; `style-src-elem` / `style-src-attr`; empty `stimulus_script_url`; `kit-admin` `showModal` + config boot island
- [x] Product FormKit profile `filter` on `AbstractGetFilterType` (label never / placeholder always / help always unless `help: false` / required false except `per_page`); helpers `addHiddenFilterField` / `addFilterSelect` / `addDashboardPerPage`
- [x] Migrate host GET filter Types to FormKit helpers + `form` catalogue (`issue_index_filter`, `dashboard_*_filter`, `analytics_filter`, release compare/focus, …)
- [x] Do not hardcode `translation_domain: form` on `FormKitAbstractType`; domain from profile merger
- [x] Analytics filter help keys; Twig paints with `form_row` + `_fields` (prefer over hand-rolled `form_help`); never `help: false` on `HiddenType` from profile alone
- [x] Host `nowo_form_kit.type_map.search` → `SearchType`; `AbstractGetFilterType::mergeFieldOptions` defaults `required: false` (override for `per_page`)
- [x] Spec amendment: filter contract = product standard; CSRF/authz non-regression note (`090` + this amendment)
- [x] Cursor rule `.cursor/rules/formkit-profiles.mdc`
- [x] Spec amendment: product `beacon` FR-003c + Twig `form_row` / `_fields` for filters and settings; `addFilterSelect` auto empty-option placeholder
- [x] Settings Types (`project_governance`, share/token/member/group add) on `beacon` + `form` catalogue; Twig `_fields` loop
- [x] Twig `form_row` consolidation wave: Appearance `color_row`, Mentions unread caption via `form_row`, role permissions/edit, magic-login confirm, Menu flags/reorder/import
- [x] Document standing `form_widget` exceptions (Live alert switches, issue duplicate combobox, theme internals) in `077` / this amendment
- [x] Playwright selectors for prefixed settings fields (`project_governance_*`, share/token create)
