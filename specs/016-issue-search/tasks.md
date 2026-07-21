# Tasks: 016-issue-search

**Input**: [plan.md](./plan.md), [spec.md](./spec.md)

**Prerequisites**: plan.md, spec.md; release filter from `014-releases`; priority filter from `015-issue-workflow`

## Phase 1: Repository SQL sort & filters

- [x] T001 Make `events_24h` / `events_7d` / `events_30d` SQL-sortable in `IssueListSort` (include in `SQL_FIELDS` / `isSqlSortable`)
- [x] T002 `IssueRepository::applySqlSort` — correlated event-count subqueries for occurrence windows + stable secondary order
- [x] T003 `IssueRepository` filters: `tag` (JSON_SEARCH MySQL / LIKE SQLite), `url` (LIKE payload), `user` (`Event.userIdentifier` EXISTS); keep `release`
- [x] T004 Extend `search` / `countSearch` signatures and `createFilteredQueryBuilder` for tag/url/user

## Phase 2: Controller & UI

- [x] T005 `IssueController::index` — read `tag`/`url`/`user` query params; always paginate via repository; remove `sortIssuesByOccurrence` path
- [x] T006 Add `tag`/`url`/`user` to saved-view query keys + Twig filter form / `filterQuery`
- [x] T007 Translations EN/ES for new filter placeholders

## Phase 3: Tests & docs

- [x] T008 `tests/Issues/IssueSearchScaleTest.php` — filters, SQL occurrence order across pages, `per_page` caps
- [x] T009 Changelog Unreleased bullets (English)
- [x] T010 Mark plan/tasks complete
