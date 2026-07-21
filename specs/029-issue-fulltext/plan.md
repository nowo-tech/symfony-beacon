# Implementation Plan: Issue Full-Text Search

**Branch**: `029-issue-fulltext`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Upgrade issue list `q` from `LIKE` to MySQL `FULLTEXT` on `title` + `culprit` (BOOLEAN MODE). SQLite/tests keep `LIKE` fallback so suites stay green.

## Technical Context

| Area | Decision |
|------|----------|
| MySQL | `MATCH(title, culprit) AGAINST (:q IN BOOLEAN MODE)` with `+token*` |
| SQLite / other | `title LIKE %q% OR culprit LIKE %q%` |
| Index | `idx_issue_title_culprit_ft` FULLTEXT; migration skips non-MySQL |
| Min token | Document InnoDB `innodb_ft_min_token_size` (often 3) |
| Pagination / sorts | Unchanged SQL path from `016` |

## Constitution Check

- Spec-first (`029`); English docs / PHPDoc
- No new search product (Elasticsearch out of scope)
