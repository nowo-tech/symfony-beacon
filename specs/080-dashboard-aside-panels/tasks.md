# Tasks: Dashboard aside panels (`080`)

**Status**: Done (2026-08-03)

- [x] T001 Persist `issue_mention` + migration `Version20260803140000`; wire `IssueCommentCreator`
- [x] T002 Summary / Activity / Mentions / Alerts / New-in-release controllers + Twig
- [x] T003 Menu + breadcrumb seeders (parent `dashboard_home`; hide_when_single_root)
- [x] T004 i18n (en + locales), CHANGELOG, ROADMAP 6.30
- [x] T005 Functional test (`DashboardAsidePanelsFunctionalTest`) — routes, mentions read, breadcrumbs
- [x] T006 Standardize list pagination (`PagePagination` + `_table_pagination`) on Activity + Alerts (Assignments / Mentions / New in release already compliant) — FR-011 / SC-005
- [x] T007 Mentions `unread` filter via `form_row` + Twig/`messages` caption (`077` / `081` Twig consolidation)
