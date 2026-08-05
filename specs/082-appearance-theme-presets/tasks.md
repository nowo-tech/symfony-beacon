# Tasks: 082-appearance-theme-presets

**Status**: Done (as-built)

- [x] `AppearanceThemePresets` catalogue (light + dark) with mode-scoped `apply()` / `matchLightId()` / `matchDarkId()`
- [x] Migrations: `theme_id` (`…120000`), `footer_fixed` (`…130000`), `corner_style` (`…140000`), `border_strength` (`…150000`), `theme_id_dark` + dark-id split (`…160000`)
- [x] Entity + provider CSS variables for palette, corners, borders, footer
- [x] Tabbed controller routes: Themes / Brand / Layout / Colors (+ Colors subtabs); Themes shows both modes on one page
- [x] Twig Themes cards + Brand/Layout/Colors forms; i18n for all shipped locales
- [x] Instance config export/import keys for themes + layout
- [x] Tests: AppearanceSettings, AppearanceThemePresets, corner/border helpers
- [x] Docs: ROADMAP 6.32, CHANGELOG Unreleased, UPGRADING migration note, this spec
