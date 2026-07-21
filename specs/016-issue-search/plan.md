# Implementation Plan: Issue Search

**Branch**: `016-issue-search` | **Date**: 2026-07-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/016-issue-search/spec.md`

## Summary

Extend the project issues list with tag / URL / user (event actor) filters alongside existing release and full-text search, and move 24h / 7d / 30d occurrence sorts from PHP in-memory ordering into SQL (paginated globally). Keep `per_page` caps at 10 / 25 / 50 / 100.

## Technical Context

**Language/Version**: PHP 8.3+ / Symfony 7

**Primary Dependencies**: Doctrine ORM, Twig

**Storage**: MySQL/MariaDB in Docker; SQLite for PHPUnit (`when@test`)

**Testing**: PHPUnit `DatabaseWebTestCase` — `tests/Issues/IssueSearchScaleTest.php`

**Target Platform**: Self-hosted Beacon (Docker Compose + FrankenPHP)

**Project Type**: Symfony web application

**Performance Goals**: First page under ~2s for ~10k issues in acceptance; occurrence sort must not load all matching rows into PHP

**Constraints**: English UI/docs/PHPDoc; AND semantics for combined filters; no external search appliance

**Scale/Scope**: Per-project issues list only

## Constitution Check

- English documentation / PHPDoc / Twig copy
- Prefer existing Issues patterns over new kits
- No Cursor commit attribution

## Project Structure

### Documentation (this feature)

```text
specs/016-issue-search/
├── plan.md
├── tasks.md
├── spec.md
└── checklists/requirements.md
```

### Source Code

```text
src/Issues/IssueListSort.php                 # occurrence fields are SQL-sortable
src/Issues/Repository/IssueRepository.php    # tag/url/user filters; SQL occurrence ORDER BY
src/Issues/Controller/IssueController.php    # wire filters; remove PHP sort path
templates/issue/index.html.twig              # tag/url/user inputs + saved-view keys
translations/messages.en.yaml
translations/messages.es.yaml
tests/Issues/IssueSearchScaleTest.php
docs/CHANGELOG.md
```

## Implementation Decisions

1. **Tag filter** — Match `event.payload` JSON: MySQL `JSON_SEARCH(payload, 'one', :tag)`; SQLite tests use `CAST(payload AS TEXT) LIKE`. Prefetch matching `issue_id`s (scoped by project) then `i.id IN (...)`.
2. **URL filter** — Substring `LIKE` on serialized payload (request URL appears in payload JSON).
3. **User filter** — `Event.userIdentifier` via EXISTS join to issue (actor from ingest); keep separate `assignee` filter for membership assignee.
4. **Release** — Unchanged (`lastRelease` / `firstRelease`).
5. **Occurrence sort** — Correlated `COUNT` subquery on `Event.receivedAt` windows as `HIDDEN` select + `ORDER BY`; secondary `lastSeen`, `id`. Remove `sortIssuesByOccurrence` and the fetch-all path in `IssueController`.
6. **Pagination** — Always `setMaxResults` / `setFirstResult` in repository; `per_page` whitelist unchanged.

## Complexity Tracking

| Deferred | Why |
|----------|-----|
| Full-text engine (MySQL FULLTEXT / external) | Spec allows LIKE/title search for v1 |
| Denormalized tag columns | Payload JSON search sufficient for v1 |
