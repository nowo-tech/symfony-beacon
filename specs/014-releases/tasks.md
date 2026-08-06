# Tasks: Releases

**Feature**: 014-releases  
**Status**: Complete

## Setup

- [x] T001 Add `firstRelease`, `lastRelease`, `lastEnvironment` to `Issue` entity + index
- [x] T002 Migration `Version20260721160000` for issue release columns

## Core

- [x] T003 Update release/environment on issue in `IssueEnvelopeWriter` (orchestrated by `ProcessEnvelopeHandler`; `085`; was `ProcessEnvelopeHandler::ingestEvent`)
- [x] T004 Add optional `release` filter to `IssueSearchRepository` search/count/createFilteredQueryBuilder (`083`; was `IssueRepository`)
- [x] T005 Wire `release` / `compare` in `IssueController::index` + `compareResult`
- [x] T006 Twig: release filter, badge, last release/env text, compare panel
- [x] T007 Translations EN/ES (`issues.filter.release`, `issues.badge.*`, `issues.compare.*`)

## Polish

- [x] T008 `tests/Functional/Issues/IssueReleasesTest.php`
- [x] T009 CHANGELOG Unreleased + UPGRADING note
- [x] T010 Write `plan.md` / mark tasks done
