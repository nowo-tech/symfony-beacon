# Tasks: Release Health

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)  
**Status**: Complete

## Setup

- [x] T001 Write `plan.md` for `028-release-health`
- [x] T002 Expand and complete `tasks.md`
- [x] T003 Review `014-releases`, project nav, and issue-list filter semantics

## Backend

- [x] T004 Add `IssueRepository` helpers for distinct releases, first-release counts, and release-scoped issue queries
- [x] T005 Add `EventRepository` helper for distinct project release versions
- [x] T006 Add `ProjectReleaseHealthController` with membership enforcement and release-compare summary

## UI and i18n

- [x] T007 Add `Releases` tab to `templates/project/_nav.html.twig`
- [x] T008 Build `templates/project/releases.html.twig` with picker, empty states, issue-list links, and environment-compare deep link
- [x] T009 Add English and Spanish translation keys for the new panel

## Tests and docs

- [x] T010 Add PHPUnit functional coverage for release health access, counts, empty states, and compare
- [x] T011 Update `docs/ROADMAP.md` (5.4 Done) and `docs/CHANGELOG.md` Unreleased
- [x] T012 Mark spec artifacts complete (`spec.md` status, checklist)
