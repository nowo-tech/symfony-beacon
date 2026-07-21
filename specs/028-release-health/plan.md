# Implementation Plan: Release Health

**Branch**: `028-release-health`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented

## Summary

Add a project-scoped **Release health** panel at `/projects/{uuid}/releases` so members can pick a release, see how many issues were first seen there, jump into the existing issue list release filter, compare two releases, and deep-link to the existing environment compare from `014-releases`.

## Technical Approach

| Area | Choice |
|------|--------|
| Route | New authenticated project page: `project_releases` at `/projects/{id}/releases` |
| Access | Reuse `ProjectAccessService::requireMembership()` |
| Release source | Merge distinct releases from `Issue.firstRelease` / `Issue.lastRelease` plus `Event.releaseVersion` for catalog completeness |
| New issue counts | Add `IssueRepository` helpers keyed by `firstRelease` |
| New issue sample | Query latest issues where `firstRelease = :release` for a small preview list |
| Release compare | Compare issue sets where `firstRelease = :release OR lastRelease = :release`; classify only-A / both / only-B |
| Environment compare | Deep-link to existing `IssueController` environment compare (`environment` + `compare`) |
| UI | Add `Releases` tab to `templates/project/_nav.html.twig`; new Twig page with release picker, compare summary, empty states, and issue-list links |
| I18n | English UI copy with `messages.en.yaml` and `messages.es.yaml` keys |
| Testing | PHPUnit functional tests for member access, empty state, release counts, issue-list deep links, and release compare |

## Files

- `src/Project/Controller/ProjectReleaseHealthController.php`
- `src/Issues/Repository/IssueRepository.php`
- `src/Issues/Repository/EventRepository.php`
- `templates/project/_nav.html.twig`
- `templates/project/releases.html.twig`
- `translations/messages.en.yaml`
- `translations/messages.es.yaml`
- `tests/Project/ReleaseHealthControllerTest.php`
- `specs/028-release-health/tasks.md`
- `docs/ROADMAP.md`
- `docs/CHANGELOG.md`

## Notes

- Reuse existing `014-releases` query semantics for the issue-list deep link (`release=...`).
- Do not add write/mutation actions; this is read-only project telemetry UI.
