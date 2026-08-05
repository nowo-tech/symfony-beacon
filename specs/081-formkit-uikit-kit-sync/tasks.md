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
