# Implementation Plan: GDPR User Export and Anonymize

**Branch**: `043-gdpr-user-export` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/043-gdpr-user-export/spec.md`

## Summary

Self-service (and admin) **JSON account export** plus **app-owned anonymize** that scrubs identifiers, disables login (UserKit), clears credentials/social/push, and invalidates the current session. Document that project ingest events remain project data. Do **not** use `nowo-tech/anonymize-bundle` as the production executor (dev/test dumps only if added later).

## Technical Context

**Language/Version**: PHP 8.5 / Symfony 8.1  
**Primary Dependencies**: AuthKit social accounts, UserKit `EnabledUserTrait`, existing `UserActionRecorder`  
**Storage**: Optional `app_user.anonymized_at`; no parallel PII store  
**Testing**: PHPUnit functional (`GdprAccountExportTest`)  
**Target Platform**: Self-hosted FrankenPHP  
**Project Type**: web-service  
**Performance Goals**: Export synchronous for typical single-user payload  
**Constraints**: No other users' PII/secrets in export; never export password hashes; block anonymize when sole project owner or last instance admin  
**Scale/Scope**: Account + memberships metadata only (not full event purge)

## Constitution Check

- English docs / PHPDoc / UI default locale — pass  
- Prefer kits: UserKit disable; AuthKit social cleanup; **not** anonymize-bundle runtime — pass (roadmap)  
- Legal pages remain (`docs/product/LEGAL-AND-COOKIES.md`) — pass  
- No drive-by refactors — pass  

## Project Structure

### Documentation (this feature)

```text
specs/043-gdpr-user-export/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/account-export.md
└── tasks.md
```

### Source Code

```text
src/Identity/Service/AccountDataExporter.php
src/Identity/Service/AccountAnonymizer.php
src/Identity/Controller/AccountPrivacyController.php
src/Identity/Controller/AdminUserController.php   # export + anonymize actions
src/Identity/Entity/User.php                      # anonymizedAt
src/Identity/UserActionType.php                   # UserAnonymized, AccountExported
migrations/Version20260731140000.php
templates/account/privacy.html.twig
templates/account/_profile_tabs.html.twig
docs/product/LEGAL-AND-COOKIES.md, CHANGELOG, ROADMAP, UPGRADING, docs/dev/DATABASE.md
tests/Functional/Identity/GdprAccountExportTest.php
```

## Complexity Tracking

| Violation | Why needed | Simpler alternative rejected because |
|-----------|------------|--------------------------------------|
| App-owned anonymize vs kit | Roadmap forbids runtime anonymize-bundle | Kit is dump-oriented, not account lifecycle |
