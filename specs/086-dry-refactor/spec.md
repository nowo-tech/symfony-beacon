# Feature Specification: DRY refactor (PHP / Twig / DX)

**Feature Branch**: `086-dry-refactor`  
**Created**: 2026-08-07  
**Status**: Implemented (shipped in **v1.3.0**, 2026-08-07; confirm-dialog structured chrome **2026-08-09**; open-on-connect + dark scrim **2026-08-10**)  

**Input**: Internal maintainability pass after `083` / `085`: collapse duplicated PHP write-path helpers and Twig chrome shells; align native checkbox styling with Beacon form tokens; auto-start Compose via `make ensure-up` for exec-based Make targets. No new end-user product surface.

## Summary

Contributors need **one place** for repeated OTLP gate/map/dispatch, project bootstrap, issue JSON export shapes, confirm dialogs, admin list chrome, dashboard feed layouts, and platform checkbox paint. Success is measurable as: fewer copy-paste sites, PHPStan clean, Twig `strict_variables`-safe includes, and DX Make targets that start the stack when down (without rebuild/Vite).

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| D1 | OTLP | `OtlpIngestPipeline` + `OtlpSignalMapperInterface`; thin logs/traces/metrics controllers |
| D2 | OTLP | `OtlpAttributeCodec` (+ shared attribute/body helpers used by mappers) |
| D3 | Project | `ProjectFactory` + `ProjectApiKeyFactory` for create / bootstrap / demo paths |
| D4 | Issues | `IssueJsonNormalizer` shared by export / read API |
| D5 | Project | `AccessibleProjectFilter` for dashboard / list project filter resolution |
| D6–D13 | Cross-cutting | Encrypted secret form apply helper; Retention→Governance naming; flash keys; menu markers; AuthKit rate-limit base; `collectGroupIds`; CRUD guard; Slack/Teams hook destination context + action token consumer |
| D14–D15 | Shared | `SqlLikeEscaper`; `CreatedAtImmutableTrait`; `StrictFixtureReader`; AuthKit mail delivery helper |
| T1–T3 | Twig | `shared/_confirm_dialog.html.twig`; `admin/_list_page.html.twig`; `dashboard/_feed_layout.html.twig` |
| T4–T7 | Twig | Kit admin shell helpers; hub tiles; legal nav; CRUD forms; settings Mailer/Mercure chrome reuse |
| T8 | Twig | Confirm-dialog **structured chrome**: form-bearing embeds use `header_wrapper` + `content_wrapper` (`confirm-dialog__header` / `__content` / `__actions`); prefer default actions + `submit_disabled` over custom `{% block actions %}` |
| T9 | Twig / Stimulus | `open_on_connect` on `shared/_confirm_dialog.html.twig` emits `data-confirm-dialog-open-on-connect-value="true"` **only** for explicit true; omit otherwise (Stimulus Boolean treats empty/`null` attr as true) |
| T10 | SCSS / kit | Confirm-dialog `::backdrop` and kit `.modal-backdrop` use a **black** scrim (not `--color-ink` / `--beacon-ink`); darker opacity under `html[data-theme='dark']` |
| DX | Makefile | `ensure-up` prerequisite on targets that `docker compose exec` (no `--build` / no Vite) |
| UI | Forms | Platform `.checkbox` + FormKit `field_types.checkbox.attr.class: checkbox`; AuthKit / kit-admin / confirm-dialog selectors |
| UI | Password toggle | `.input-group.form-password-toggle` flex row, **gap 0.5rem**, eye on the right; `nowo_password_toggle` button_classes `input-group-text`; hide `::-ms-reveal` |
| QA | Static analysis | PHPStan 0 errors (bootstrap FrankenPHP ignores where needed) |

## Non-goals

- Changing OTLP HTTP contracts, auth, caps, or Envelope worker behavior
- New product routes or UX features beyond shared chrome / paint
- Replacing Spec Kit / module boundary rules from `083` / `085`
- Forcing `ensure-up` on `up` / `down` / `build` / logs / mailpit / git / secrets-scan

## User Scenarios & Testing *(mandatory)*

### User Story 1 - OTLP controllers stay thin (Priority: P1)

As a maintainer, logs / traces / metrics HTTP entrypoints share auth, body limits, quotas, rate limit, map, and Envelope dispatch through `OtlpIngestPipeline` + per-signal mappers.

**Independent Test**: `make phpstan` and OTLP PHPUnit suites green; controllers only select mapper + call pipeline.

**Acceptance Scenarios**:

1. **Given** a valid OTLP logs/traces/metrics POST, **When** ingest succeeds, **Then** behavior matches pre-refactor (200 ACK + async events for filtered records).
2. **Given** a change to shared gate logic, **When** edited in the pipeline/gateway, **Then** all three signals pick it up without three parallel edits.

### User Story 2 - Twig shells reduce copy-paste (Priority: P1)

As a contributor adding an admin list, confirm dialog, or dashboard feed, I include the shared partial instead of cloning markup.

**Independent Test**: Grep shows confirm dialogs include `shared/_confirm_dialog`; admin users/groups/projects use `_list_page`; feed panels use `_feed_layout`.

**Acceptance Scenarios**:

1. **Given** `strict_variables`, **When** a dialog omits optional modifiers, **Then** Twig defaults (`dialog_modifier|default`) keep render green.
2. **Given** a destructive Settings action, **When** the confirm dialog opens, **Then** Stimulus `confirm-dialog` still portals/behaves as before.

### User Story 2b - Form confirm dialogs use structured chrome (Priority: P2)

As a member or admin, dialogs that collect fields (change role, add member, anonymize type-to-confirm, mark duplicate, new project) show a clear header / body / footer rhythm — not flat title+fields+actions with uneven margins from the flat-panel CSS fallback.

**Independent Test**: Open `/admin/users` → edit role; project Settings → add member / edit member role / edit group role; anonymize — each form dialog renders `confirm-dialog__header`, `confirm-dialog__content`, and `confirm-dialog__actions` (or equivalent structured markup as in `dashboard/home` new-project).

**Acceptance Scenarios**:

1. **Given** a form-bearing embed of `shared/_confirm_dialog.html.twig`, **When** it opens, **Then** it passes `header_wrapper: true` and `content_wrapper: true` (or custom_form markup with those three regions).
2. **Given** last-admin / last-owner guards, **When** submit must stay disabled, **Then** the embed uses `submit_disabled` (and disabled fields) instead of a custom `{% block actions %}`.
3. **Given** a body-only confirm (no visible fields), **When** rendered, **Then** it MAY stay flat (avoid `header_wrapper` alone with only a hidden CSRF between header and footer).

### User Story 2c - Confirm dialogs stay closed and dark-themed (Priority: P2)

As an admin on `/admin/permissions` (many per-row edit dialogs) or any surface with optional `open_on_connect`, closed dialogs must not auto-open. Modal backdrops must read as a dark overlay in light **and** dark themes.

**Independent Test**: `GET /admin/permissions` has zero `[data-confirm-dialog-open-on-connect-value]` nodes; `?new=1` / `?edit={uuid}` has exactly one `"true"`. Same pattern for `/admin/roles` create (`?new=1`) and role-detail edit (`?edit=1`). In dark theme, open a confirm dialog and confirm `::backdrop` is black (not a light wash).

**Acceptance Scenarios**:

1. **Given** an embed passes `open_on_connect: false` / `null` / omits the var, **When** HTML is rendered, **Then** the open-on-connect data attribute is absent.
2. **Given** `open_on_connect: true` (or equivalent), **When** Stimulus connects, **Then** exactly that dialog opens.
3. **Given** `html[data-theme='dark']`, **When** a confirm or kit Bootstrap modal opens, **Then** the scrim uses black at higher opacity than light theme (not `--color-ink` / `--beacon-ink`).
4. **Given** `GET /admin/roles/new` or `GET /admin/roles/{uuid}/edit`, **When** followed, **Then** the list/detail page opens with the create/edit modal (`open_on_connect`) instead of a full-page form route.

### User Story 3 - Make exec targets start the stack (Priority: P2)

As a developer, `make test` / `make phpstan` / `make shell` (and other exec targets) call `ensure-up` so a stopped `php` container is started with `docker compose up -d` only.

**Independent Test**: With stack down, `make ensure-up` starts services; with stack up, it is a no-op; `make up` still builds + Vite.

**Acceptance Scenarios**:

1. **Given** Compose is down, **When** I run `make console`, **Then** the stack starts without `--build` and without Vite.
2. **Given** Compose is up, **When** I run `make ensure-up`, **Then** no restart storm occurs.

### User Story 4 - Checkboxes match platform tokens (Priority: P2)

As a member, native checkboxes use Beacon moss/sand/surface tokens (not browser accent blue), including AuthKit / confirm-dialog / kit-admin checks. FormKit profile `checkbox.attr.class` is `checkbox` (not Tailwind utilities that do not paint native boxes).

**Independent Test**: After `make vite-build`, open login (remember-me), a confirm dialog with a check, and a kit admin form — checked/unchecked colors follow theme tokens.

**Acceptance Scenarios**:

1. **Given** a Symfony CheckboxType under the host form theme, **When** it renders, **Then** the input has class `checkbox` (theme and/or FormKit).
2. **Given** a raw HTML checkbox in RoutingKit import or similar, **When** it uses `class="checkbox"` (or `.nowo-ui-check`), **Then** platform SCSS applies.

### User Story 5 - Password toggle stays usable (Priority: P2)

As a member on AuthKit login/register/reset, the show/hide control stays **to the right** of the password field with a **horizontal gap**; the input does not wrap the eye below the field. Layout chrome is owned by host SCSS + `nowo_password_toggle.yaml` (not Tailwind flex utilities on the container that fight absolute/merge experiments).

**Independent Test**: Open `/login`, confirm eye sits right of the password input with visible gap; focus the field — outer group width stays stable aside from password-manager extension icons (NordPass / Bitwarden), which the host cannot control.

**Acceptance Scenarios**:

1. **Given** `button_classes` include `input-group-text`, **When** the page loads, **Then** the eye is a flex sibling on the right with `gap: 0.5rem`.
2. **Given** a password manager injects an icon on focus, **When** comparing usable text width, **Then** any shrink is from the extension, not from the eye dropping to a new row.

## Requirements *(mandatory)*

- **FR-001**: OTLP logs/traces/metrics MUST share `OtlpIngestPipeline` (and gateway limits) with signal-specific mappers only.
- **FR-002**: Project create / API key mint paths MUST prefer `ProjectFactory` / `ProjectApiKeyFactory` over duplicated entity bootstrap.
- **FR-003**: Confirm dialogs, admin CRUD list chrome, and dashboard feed panels MUST use the shared Twig shells listed in Scope.
- **FR-003b**: Form-bearing confirm dialogs MUST use structured chrome (`header_wrapper` + `content_wrapper`, or explicit `confirm-dialog__header` / `__content` / `__actions`). Flat-panel CSS remains a fallback for body-only confirms, not the preferred pattern for field dialogs. Prefer shell `submit_disabled` / `submit_label` / `cancel_label` over duplicating `{% block actions %}`.
- **FR-003c**: `shared/_confirm_dialog.html.twig` MUST emit `data-confirm-dialog-open-on-connect-value` only for explicit true values. Passing `null` / empty / false MUST omit the attribute (Stimulus Boolean: empty attribute ⇒ true).
- **FR-003d**: Confirm-dialog `::backdrop` and kit admin `.modal-backdrop` MUST use a black scrim; MUST NOT derive overlay color from `--color-ink` / `--beacon-ink`. Dark theme MUST use a higher opacity than light.
- **FR-004**: `make ensure-up` MUST start Compose when `php` does not respond to `exec`, and MUST NOT rebuild or run Vite.
- **FR-005**: Native product/kit checkboxes MUST use platform `.checkbox` (or documented selectors); FormKit `field_types.checkbox.attr.class` MUST be `checkbox`.
- **FR-006**: Password toggle MUST keep the eye on the right with a horizontal gap; host YAML MUST NOT rely on Tailwind `flex`/`gap-*` container classes that fight SCSS layout.
- **FR-007**: Document as-built helpers in `docs/ARCHITECTURE.md`, `docs/CONTRIBUTING.md`, `docs/CHANGELOG.md`, and this spec.

## Assumptions

- Follow-up unit tests for pipeline/resolvers may land after this slice; behavior covered by existing OTLP / UI suites.
- Checkbox / password-toggle paint requires a frontend rebuild (`make vite-build`) for operators who ship static `public/build/`.
- Browser password-manager extensions (e.g. NordPass) may inject icons inside the password field on focus; that is outside Beacon control.
- Structured confirm chrome (T8 / FR-003b) is markup-only; no Stimulus or SCSS API change required when embeds already use the shared partial flags.
- Backdrop paint (T10) requires `make vite-build` for product SCSS; kit modal backdrop lives in Twig `_kit_admin_styles` (no Vite for that slice).

## As-built call sites (T8, 2026-08-09)

| Surface | Template | Structured flags |
|---------|----------|------------------|
| Admin user role | `templates/admin/users/index.html.twig` | `header_wrapper` + `content_wrapper`; `submit_disabled: isLastAdmin` |
| Admin user anonymize | same | `header_wrapper` + `content_wrapper` |
| Add project member | `templates/project/settings.html.twig` | `header_wrapper` + `content_wrapper` |
| Edit member role | same | `header_wrapper` + `content_wrapper`; `submit_disabled: isLastOwner` |
| Edit group role | same | `header_wrapper` + `content_wrapper` |
| Mark duplicate / new project / admin project delete | `issue/show`, `dashboard/home`, `admin/projects/show` | already structured (reference) |

## Related

- Predecessors: `083-module-boundaries`, `085-architecture-convergence`
- OTLP contracts: `067-otlp-ingest`, `070-otlp-traces`, `074-otlp-metrics`
- Twig / forms: `011-project-danger-zone`, `002-identity-project` (members/roles UI + `project.*` catalog i18n + product `project_grants` / Settings surface; Administration is `ROLE_ADMIN`-only), `077-form-type-field-loop`, `081-formkit-uikit-kit-sync`
- Product roles reference: `docs/product/ROLES.md`
- Roadmap **6.35**
- Sibling hosts (same chrome): open-agendesk-v2 / symfony-frankenphp-boilerplate-v2 platform form CSS

## Amendment (`IngestProjectAccessGate`, 2026-08-11)

- Follow-on DRY for Envelope + OTLP credential/governance: shared `App\Ingest\Service\IngestProjectAccessGate` (`authorizeCredentials` + `assertIngestAllowed`). Complements D1 (`OtlpIngestPipeline`); HTTP contracts unchanged. Cross-links: `003-ingest`, `067-otlp-ingest`.