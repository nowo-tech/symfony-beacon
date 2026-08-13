# Implementation Plan: CSRF via Symfony Forms

**Branch**: `090-csrf-symfony-forms` | **Date**: 2026-08-12 | **Spec**: [spec.md](./spec.md)  
**Status**: Implemented (as-built)

## Summary

Replace host hand-rolled `<form method="post">` + Twig `csrf_token()` with FormKit Types: shared CSRF-only helpers for button actions, named Types for fielded POSTs, and shared GET filter Types for list chrome. Keep AJAX header CSRF and AuthKit logout / kit modal patterns as documented exceptions.

## Technical Context

**Language/Version**: PHP 8.4 / Symfony 8  
**Primary Dependencies**: `nowo-tech/form-kit-bundle` (≥ 2.2), Symfony Form + Security CSRF  
**Storage**: N/A (no new entities)  
**Testing**: PHPUnit Functional (workflow / danger zone / admin mutations) + Unit Twig helpers as needed  
**Target Platform**: Self-hosted Beacon (Docker / FrankenPHP)  
**Project Type**: Modular Symfony app (`src/{Identity,Project,Issues,…}`)  
**Constraints**: Stable `csrf_token_id` strings across migration; English UI; prefer kits over reinventing auth CSRF  
**Scale/Scope**: Host product + Administration Twig; kit vendor forks tracked under `081`

## Constitution Check

- [x] Spec-first: `specs/090-csrf-symfony-forms/`
- [x] Canonical stack / Docker-first
- [x] English docs/PHPDoc/UI; functional tests updated for form submit shape
- [x] No new `env(…): '…'` defaults in `parameters.yaml`
- [x] Prefer `nowo-tech/*` (FormKit) for form chrome
- [x] No Cursor / agent attribution

## Technical approach

| Slice | Approach |
|-------|----------|
| CSRF-only POST | `CsrfOnlyType` + `CsrfOnlyFormFactory` + Twig `csrf_action_form` / `_csrf_action` |
| Hidden payload POST | `HiddenFieldsCsrfType` (e.g. member set-active) via factory / page builder |
| Fielded POST | Named module Types extending `FormKitAbstractType`; Twig `077` field loop |
| Settings page | `ProjectSettingsPageBuilder` builds per-row CSRF views for revoke/toggle/delete |
| GET filters | `AbstractGetFilterType` (CSRF off, GET) + `GetFilterFormFactory`; profile `filter` (`081`); `required` false except `per_page`; host `type_map.search` |
| AJAX | Leave `X-CSRF-TOKEN` + `isCsrfTokenValid` on preference / push controllers |
| Kits | Do not block Done on Menu/Breadcrumb modal `data-token`; document under `081` |

## Project Structure

```text
specs/090-csrf-symfony-forms/
├── spec.md
├── plan.md
├── tasks.md
└── checklists/requirements.md

src/Shared/Form/
├── CsrfOnlyType.php
├── HiddenFieldsCsrfType.php
├── CsrfOnlyFormFactory.php
├── AbstractGetFilterType.php
├── GetFilterFormFactory.php
├── AdminSearchType.php
└── SearchQueryType.php

src/Shared/Twig/CsrfActionTwigExtension.php
templates/form/_csrf_action.html.twig

src/{Issues,Project,Identity,Notifications,Analytics,Ops,Shared}/Form/*Type.php
```

**Structure Decision**: Shared helpers under `App\Shared\Form`; domain Types stay in owning modules (constitution module map).

## Testing

- `IssueWorkflowTest`, `ProjectDangerZoneTest`, admin/group/project mutation functionals submit Symfony fields
- Smoke: POST without CSRF rejected; GET filters still filter; optional filter fields do not bypass membership checks
- No new DB migrations

## Complexity Tracking

None — reuses FormKit and existing controllers; no alternate runtime.
