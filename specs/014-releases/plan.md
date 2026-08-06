# Implementation Plan: Releases

**Branch**: `014-releases`  
**Date**: 2026-07-21

## Summary

Denormalize release and environment context onto `Issue` during Envelope ingest, expose release filtering and a “New in release” badge on the issues list, and support comparing issue sets across two environments via `lastEnvironment`.

## Technical approach

| Area | Choice |
|------|--------|
| Storage | Nullable columns on `issue`: `first_release` (120), `last_release` (120), `last_environment` (80); index `(project_id, last_release)` |
| Write path | `IssueEnvelopeWriter` after event persist (orchestrated by thin `ProcessEnvelopeHandler`; `085`); normalize trim / empty→null / truncate |
| Filter | `IssueSearchRepository` optional `release` → `lastRelease = :release OR firstRelease = :release` (`083`) |
| Compare | When `environment` + `compare` query params set, load sets by `lastEnvironment`, classify onlyA / onlyB / both (cap 50) |
| UI | Issues index filters + compare panel + badge |

## Files

- `src/Issues/Entity/Issue.php`
- `migrations/Version20260721160000.php`
- `src/Ingest/MessageHandler/ProcessEnvelopeHandler.php` (orchestration)
- `src/Issues/Service/IssueEnvelopeWriter.php` (release/environment denormalize; `085`)
- `src/Issues/Repository/IssueSearchRepository.php`
- `src/Issues/Controller/IssueController.php`
- `templates/issue/index.html.twig`
- `translations/messages.en.yaml` / `messages.es.yaml`
- `tests/Functional/Issues/IssueReleasesTest.php`
- `docs/CHANGELOG.md` / `docs/UPGRADING.md`
