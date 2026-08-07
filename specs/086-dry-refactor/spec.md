# Feature Specification: DRY refactor (PHP / Twig / DX)

**Feature Branch**: `086-dry-refactor`  
**Created**: 2026-08-07  
**Status**: Implemented (shipped in **v1.3.0**, 2026-08-07)  

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
- **FR-004**: `make ensure-up` MUST start Compose when `php` does not respond to `exec`, and MUST NOT rebuild or run Vite.
- **FR-005**: Native product/kit checkboxes MUST use platform `.checkbox` (or documented selectors); FormKit `field_types.checkbox.attr.class` MUST be `checkbox`.
- **FR-006**: Password toggle MUST keep the eye on the right with a horizontal gap; host YAML MUST NOT rely on Tailwind `flex`/`gap-*` container classes that fight SCSS layout.
- **FR-007**: Document as-built helpers in `docs/ARCHITECTURE.md`, `docs/CONTRIBUTING.md`, `docs/CHANGELOG.md`, and this spec.

## Assumptions

- Follow-up unit tests for pipeline/resolvers may land after this slice; behavior covered by existing OTLP / UI suites.
- Checkbox / password-toggle paint requires a frontend rebuild (`make vite-build`) for operators who ship static `public/build/`.
- Browser password-manager extensions (e.g. NordPass) may inject icons inside the password field on focus; that is outside Beacon control.

## Related

- Predecessors: `083-module-boundaries`, `085-architecture-convergence`
- OTLP contracts: `067-otlp-ingest`, `070-otlp-traces`, `074-otlp-metrics`
- Twig / forms: `011-project-danger-zone`, `077-form-type-field-loop`, `081-formkit-uikit-kit-sync`
- Roadmap **6.35**
- Sibling hosts (same chrome): open-agendesk-v2 / symfony-frankenphp-boilerplate-v2 platform form CSS