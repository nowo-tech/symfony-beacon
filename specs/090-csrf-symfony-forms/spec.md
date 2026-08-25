# Feature Specification: CSRF via Symfony Forms

**Feature Branch**: `090-csrf-symfony-forms`  
**Created**: 2026-08-11  
**Status**: Implemented (shipped in **v1.7.0**, 2026-08-12; danger-zone empty form prefix test fix **v1.8.2**; Maintenance panel FormViews + danger/import constraints **v1.11.0** / `095`)
**Roadmap**: Phase 6.39  

**Input**: Product Twig MUST NOT hand-roll mutable HTML forms with raw `csrf_token()` hidden fields. Prefer Symfony Form Types (FormKit) and a shared CSRF-only form helper for single-action POSTs. Host GET filter UIs use shared `AbstractGetFilterType` / `GetFilterFormFactory` (no CSRF).

## Summary

Close the gap left by `077` (“hand-rolled HTML forms out of scope”). Host mutations use:

1. **Named Form Types** when the POST carries fields (triage, danger zone, Settings create/import, admin CRUD helpers, mentions, tour replay, typed confirms, …).
2. **`CsrfOnlyType` + `HiddenFieldsCsrfType` + `CsrfOnlyFormFactory` + Twig `csrf_action_form()` / `form/_csrf_action.html.twig`** for button-only (and small hidden-payload) POSTs.
3. **`AbstractGetFilterType` + `GetFilterFormFactory`** for GET search/filter bars (Issues, dashboard asides, admin lists, analytics, ops overview, releases compare).

Controllers validate mutable posts with `$form->isSubmitted() && $form->isValid()` (or equivalent `handleRequest`) instead of manual `isCsrfTokenValid` + request bags on migrated HTML forms.

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| F1 | Shared CSRF-only | **As of `101` / FormKit ≥ 2.4**: `Nowo\FormKitBundle\Form\Type\CsrfOnlyType`, `HiddenFieldsCsrfType`, `CsrfOnlyFormFactory`, Twig `csrf_action_form`, partial `templates/form/_csrf_action.html.twig`. (Originally shipped under `App\Shared\Form\*` in v1.7.0.) |
| F2 | Issues triage | `IssueStatusType`, `IssuePriorityType`, `IssueDuplicateType`, `IssueSavedViewType`, `IssueCommentType`; delete saved-view via `CsrfOnlyType` |
| F3 | Mentions | `MentionsMarkReadType`, `MentionsMarkAllReadType` on dashboard Mentions |
| F4 | Danger zone | `ProjectClearHistoryType` (block prefix `project_clear_history`; slide-to-confirm `105`), `ProjectDeleteType` (typed name; **empty** `getBlockPrefix()` → top-level `confirmation` / `_token`, not `project_delete[...]`) |
| F5 | Account tours | `AccountProductTourReplayType` on Display → Tours |
| F6 | Single-action POSTs | Locale switch, view-as disable, API key revoke/rotate, member activate/deactivate/remove, share and read-token revoke, notification and threshold toggles/deletes/tests, admin permission delete, admin project ingest/access toggles — via `CsrfOnlyType` / factory / `HiddenFieldsCsrfType` |
| F7 | Settings / admin fielded POSTs | Governance, API key / read-token / share / group / member add+role, config import (project + admin + instance), appearance theme picker, mailer test, group-member add, role permissions / role-user add, user role confirm, privacy anonymize (`TypeToConfirmType`), … |
| F8 | GET filters | Kit `GetFilterFormFactory` + kit `AbstractGetFilterType`; host `App\Shared\Form\AbstractGetFilterType` extends kit base with dashboard page/project/per_page helpers. Issues index, Assignments/Mentions/New-in-release/Alerts/Activity, dashboard project search, admin search / audit timeline, analytics, ops overview, release environment compare |
| UX | Dashboard titles | Product dashboard `h1` / intro weight reduced (`text-2xl font-semibold` + quieter intro; kit admin page-header aligned) |

## Non-goals / intentional exceptions

- **AJAX header CSRF** (`X-CSRF-TOKEN`) for theme sync, content-width sync, product-tour mark, Web Push (`account_*` token ids) — not HTML forms; keep `csrf_token()` in data attributes + `isCsrfTokenValid` on those controllers.
- **AuthKit logout** query `_csrf_token` (kit contract).
- **AuthKit magic-login confirm**: Form CSRF upstream in AuthKit ≥ 1.17 (`MagicLoginConfirmType`).
- Kit vendor Twig under `templates/bundles/Nowo*Bundle/` that still uses modal `data-token="{{ csrf_token(…) }}"` (Menu / Breadcrumb delete modals, …) — shrink with upstream / `081` host forks.
- Redesigning confirm-dialog chrome (`086`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - CSRF-only action button (Priority: P1)

As a maintainer, I render a single POST action (revoke, toggle, mark-read) with Symfony Form CSRF instead of a raw `<form>` + `csrf_token()`.

**Independent Test**: Open a migrated page; inspect markup for `form_start` / Symfony `_token` field name; POST without token → rejected; POST with valid form → action succeeds.

**Acceptance Scenarios**:

1. **Given** a CSRF-only action, **When** the page renders, **Then** Twig uses `csrf_action_form(...)` or a controller-built `CsrfOnlyType` / `HiddenFieldsCsrfType` view and `form/_csrf_action.html.twig` (or `form_start`/`form_end`), not a hand-rolled hidden `csrf_token('…')`.
2. **Given** an invalid/missing CSRF token, **When** I POST, **Then** the mutation is rejected (`isValid()` false / 422/redirect with error — no silent success).

### User Story 2 - Fielded triage / danger / Settings forms (Priority: P1)

As a project member or admin, status / priority / duplicate / saved-view / clear / delete / Settings create-import POSTs go through named Form Types.

**Independent Test**: `IssueWorkflowTest`, `ProjectDangerZoneTest`, Settings/admin mutation tests still pass; invalid CSRF fails.

**Acceptance Scenarios**:

1. **Given** issue show/index, **When** I change status or priority or mark duplicate or save/delete a view, **Then** the controller handles a Symfony Form Type and persists only when valid.
2. **Given** Settings danger zone, **When** I clear history or delete with typed name, **Then** `ProjectClearHistoryType` / `ProjectDeleteType` validate CSRF (+ confirmed slide for clear / name match for delete).
3. **Given** Settings create/import panels (API key, share, governance, config import, …), **When** I submit, **Then** a named Form Type backs the POST (no hand-rolled `_token` + raw bags).

### User Story 3 - Mentions + tour replay (Priority: P2)

As a member, mark-read / mark-all-read and product-tour replay use Form Types.

**Independent Test**: Mentions mark-read POSTs; Account → Tours replay POST.

**Acceptance Scenarios**:

1. **Given** unread mentions, **When** I mark one or all read, **Then** forms are `MentionsMarkReadType` / `MentionsMarkAllReadType`.
2. **Given** Display → Tours, **When** I replay, **Then** `AccountProductTourReplayType` backs the POST (`057`).

### User Story 4 - GET filter forms (Priority: P2)

As a member, list filters submit via GET Form Types sharing `AbstractGetFilterType` (CSRF disabled).

**Independent Test**: Issues index and a dashboard aside filter render FormKit widgets; changing query params filters results without a CSRF field.

**Acceptance Scenarios**:

1. **Given** a list with filters, **When** the page renders, **Then** the filter is built with `GetFilterFormFactory` / a Type extending `AbstractGetFilterType` (FormKit profile `filter`).
2. **Given** Issues/admin/dashboard multi-field filters, **When** chrome is painted, **Then** FormKit emits no field labels; placeholders/help come from `translations/form.*.yaml`; Twig paints via `form_row` + `_fields` / unrendered loop (`077`); optional Twig captions in `messages` only where the surface opts in (e.g. analytics); fields are not required except page-size `per_page` when present.

## Requirements *(mandatory)*

- **FR-001**: New host **mutable** POST UIs MUST use a Symfony Form Type (FormKit preferred) or `CsrfOnlyType` / `HiddenFieldsCsrfType` / `csrf_action_form()`. MUST NOT add new Twig `csrf_token('…')` hidden fields for product/admin host HTML forms.
- **FR-002**: Kit `CsrfOnlyType` MUST enable CSRF, accept a unique `csrf_token_id`, and use an empty block prefix so field names stay simple for single-action posts (FormKit ≥ 2.4).
- **FR-003**: Controllers / page builders MUST create CSRF-only forms via kit `CsrfOnlyFormFactory` (`createNamed` for nested `csrf_only[_token]`, `create` for flat) or `createForm(CsrfOnlyType::class|HiddenFieldsCsrfType::class, …)` with a stable per-action `csrf_token_id` matching the previous token id when migrating.
- **FR-004**: Named Types for issue triage / danger / mentions / tour replay / Settings / admin MUST live under the owning module (`Issues`, `Project`, `Identity`, `Shared`, …) and follow `077` field-loop rules when the template renders multiple fields.
- **FR-005**: Functional tests that previously posted `_token` + raw fields MUST submit Symfony form fields (including CSRF) after migration.
- **FR-006**: Host Settings create/import and admin fielded POSTs listed in Scope F7 MUST be on Form Types before Status Done (as-built: done). Kit vendor modal delete tokens MAY lag (`081`).
- **FR-007**: Host GET multi-field / search filters MUST extend `App\Shared\Form\AbstractGetFilterType` (or reuse `AdminSearchType` / kit `SearchQueryType` / `DashboardProjectSearchType`) with FormKit profile `filter`, and be created via kit `GetFilterFormFactory` where controllers build the view. Contract: never FormKit label; always placeholder (except hidden / Twig-owned search); always help unless `help: false`; **required false** except **`per_page`** (`required: true`); Twig `form_row` + `_fields` / loop (`077`). See `081` FR-003a / filter amendment.
- **FR-008**: AJAX preference / push endpoints MAY keep header CSRF (`X-CSRF-TOKEN`); they are not required to use HTML Form Types.

## Success Criteria

- **SC-001**: Migrated host HTML mutable surfaces have no Twig `csrf_token(` hidden fields; forms use Symfony CSRF.
- **SC-002**: `IssueWorkflowTest`, `ProjectDangerZoneTest`, mentions/locale/admin mutation tests pass.
- **SC-003**: Maintainers find the convention in this spec (`090`) when reviewing form PRs; `077` Out of Scope points here.
- **SC-004**: Host list filters use shared GET Form Types (`F8`) without inventing per-page plain HTML filter maps.

## Assumptions

- FormKit (`nowo-tech/form-kit-bundle`) remains the host Form Type base (`081`, `086` FormKit parity). Product GET filters use profile `filter` (`081` amendment 2026-08-13); product settings/POST use `beacon` (`081` FR-003c).
- Token id strings stay stable across migration so bookmarks/docs that mention ids remain valid where possible.
- Http Log kit filters follow Issues/admin filter chrome under `081` (host fork); analytics Twig captions are optional chrome on top of label-less FormKit fields painted with `form_row`.

## Amendment (GET filter `required` / search type_map, 2026-08-13)

Aligns with `081` FR-003a: `AbstractGetFilterType::mergeFieldOptions()` defaults every filter field to `required: false`; **`per_page`** MUST pass `required: true` (`addDashboardPerPage` / Issues index). Host registers `nowo_form_kit.type_map.search` → `SearchType` for snake-case `addNamedField(..., 'search', …)`.

**As of FormKit ≥ 2.4 / `105`**: that `type_map.search` entry is **built-in**; host MUST NOT re-register it.

**Security posture (non-regression)**:

- GET filters KEEP `csrf_protection: false` + `method: GET` — intentional for idempotent list UIs (`F8` / US4). Mutable host POSTs remain on Symfony Form CSRF (`FR-001`…`FR-006`).
- Making filter fields optional (`required: false`) is **not** an authorization change: it only drops HTML/Symfony “field required” constraints. Controllers MUST continue to enforce membership / roles before applying query filters; empty params MUST fall back to existing safe defaults (no cross-project leakage via missing `required`).
- Registering `SearchType` in `type_map` does not alter CSRF or access control.

## Amendment (Prefixed Form field selectors, 2026-08-13)

Product `beacon` Types use non-empty `getBlockPrefix()`. Automated browsers (Playwright DomCrawler / PHPUnit) MUST submit and assert against prefixed names and Symfony ids, e.g. `project_governance[retention_days]` / `#project_governance_retention_days`, `project_share_create[days]`, `project_read_token_create[label]`, `admin_group_member_add[email]`. Unprefixed `name="days"` / `#retention_days` selectors are obsolete after the FormKit migration.

## Amendment (FormKit owns CSRF / GET helpers, 2026-08-15)

FormKit **≥ 2.4** (`101`): move `CsrfOnlyType`, `HiddenFieldsCsrfType`, `SearchQueryType`, `CsrfOnlyFormFactory`, `GetFilterFormFactory`, and the generic `AbstractGetFilterType` base into `nowo-tech/form-kit-bundle`. Host deletes the App duplicates.

| Concern | Rule |
|---|---|
| Nested CSRF | `CsrfOnlyFormFactory::createNamed($action, $csrfTokenId, …)` → `csrf_only[_token]` |
| Flat CSRF | `CsrfOnlyFormFactory::create($action, $csrfTokenId, …)` → top-level `_token` |
| Twig | `csrf_action_form(..., named: bool)` still supported; maps to `createNamed` / `create` |
| Dashboard filters | Host `App\Shared\Form\AbstractGetFilterType` extends kit base; keeps `addDashboardPageAndProject` / `addDashboardPerPage` |

## Amendment (FormKit 2.5.1 + slide-to-confirm, 2026-08-25 / `105`)

- Drop host `nowo_form_kit.type_map.search` — snake-case `search` → `SearchType` is built-in since FormKit **2.4.0**.
- `ProjectClearHistoryType` uses `addSlideToConfirmField()` (`mapped: false` is the type default). Trusted browsers trust/revoke stay CSRF-only (`CsrfOnlyFormFactory`).
- Pin FormKit **2.5.1**. See `specs/105-authkit-security-kits/` and `011`.

## Related

- `077-form-type-field-loop` — field loop; deferred hand-rolled migration → this spec
- `011-project-danger-zone` — clear / delete Types
- `004-issues` / `015-issue-workflow` — triage Types
- `057-product-tour` — replay Type
- `080-dashboard-aside-panels` — Mentions mark-read Types + title weight polish
- `081-formkit-uikit-kit-sync` — FormKit pins / kit forks / kit filter chrome
- `101-kit-csp-shared-helpers` — FormKit ≥ 2.4 owns CSRF / GET factories; UiKit peers
- `105-authkit-security-kits` — FormKit 2.5.1 `addSlideToConfirmField`; drop host `type_map.search`
- `087-security-audit-hardening` — CSRF as standing security control
- `089-project-config-export` — project/admin import Types

## Out of Scope

- Changing FormKit / AuthKit CSRF internals beyond consuming Packagist releases
- Requiring Symfony Forms for every third-party kit admin modal until host forks / upstream catch up
- Redesigning confirm-dialog chrome (`086`)
- Moving AJAX header CSRF onto HTML forms
