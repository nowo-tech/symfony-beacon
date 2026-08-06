# Feature Specification: Form Type field loop rendering

**Feature Branch**: `077-form-type-field-loop`

**Created**: 2026-08-03

**Status**: Done (F0.1 + F2A admin Form Types; F2B structure closed via `083` / `085`)

**Input**: User description: "In Twig form templates, render fields with a loop in the same order as the Form Type so new Type fields appear automatically; manage field styles only in the Type; only paint a field if it was not already painted earlier. Apply to all Symfony forms and record this as a spec condition."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New Type field appears without Twig edits (Priority: P1)

As a maintainer, when I add a field to a Symfony Form Type used by an account/settings/admin form, the corresponding Twig page shows that field in Type definition order without listing it by name in the template (unless the template intentionally rendered it earlier for a custom layout).

**Why this priority**: Avoids silent omissions (e.g. profile `phone` missing from an explicit `form_row` list while `form_end` dumps it after the submit button).

**Independent Test**: Add a temporary field to `AccountProfileType`, open Preferences → Profile, confirm it appears with other inputs before the submit actions; remove the temporary field afterward.

**Acceptance Scenarios**:

1. **Given** a host Twig template that uses the shared unrendered-field loop after `form_start`, **When** a new field is added to the Form Type, **Then** that field is visible in the form body before action buttons without editing the Twig field list.
2. **Given** a template that already called `form_row` / `form_widget` for some children, **When** the unrendered-field loop runs, **Then** those children are not rendered again.

---

### User Story 2 - Field presentation owned by the Type (Priority: P1)

As a maintainer, I configure labels, help text, `attr`, `row_attr`, and related presentation on the Form Type (preferably via FormKit), not by hard-coding per-field Tailwind classes around each `form_row` in Twig.

**Why this priority**: Keeps one source of truth for field chrome and matches `nowo-tech/form-kit-bundle` usage.

**Independent Test**: Inspect a Form Type for `attr` / `row_attr` / FormKit helpers and confirm the Twig template does not wrap each field with duplicate style classes beyond form shell / section chrome.

**Acceptance Scenarios**:

1. **Given** a Symfony Form Type in this app, **When** a field needs CSS classes or HTML attributes, **Then** those options are set on the Type (or FormKit profile), not invented ad hoc next to each named `form_row` in Twig.
2. **Given** section chrome (headings, fieldset legends, help copy, submit row), **When** the template is reviewed, **Then** that chrome may remain in Twig, while individual field widgets still come from Type order / FormKit.

---

### User Story 3 - Custom layout without dropping new fields (Priority: P2)

As a maintainer, when a form needs a special layout (color picker grid, quiet-hours grid, dialog chrome), I may paint specific fields early, then must still run the unrendered-field loop so any other Type children appear.

**Why this priority**: Appearance, notifications, and threshold forms need grids without regressing the auto-paint rule.

**Independent Test**: On a template with manual `form_row` for a subset of fields, add an unrelated Type field and confirm the catch-all loop paints it once before actions.

**Acceptance Scenarios**:

1. **Given** a template that manually renders a subset of fields for layout, **When** the page renders, **Then** every remaining Type child that was not already painted is rendered exactly once via the shared loop (or equivalent `not field.rendered` iteration).
2. **Given** AuthKit / vendor form templates that this repo does not own, **When** applying this convention, **Then** only host overrides under `templates/` are required to comply; pure vendor pages are out of scope unless overridden.

### Edge Cases

- Hidden CSRF / `_token` children: must not be double-rendered; Symfony marks them rendered via `form_end` / rest as today.
- Compound / embedded forms: loop top-level children; nested types keep their own themes.
- Single-widget custom UIs (e.g. issue assignee): may use `form_widget` for a named child, then still run the unrendered loop for any additional Type fields.
- Hand-rolled HTML `<form>` pages without a Symfony Form Type are out of scope for this feature (separate migration).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Host Twig templates that render a Symfony `FormView` MUST iterate fields in Form Type child order and MUST paint a child only when it has not already been rendered (`not field.rendered`).
- **FR-002**: Host form templates MUST use a shared partial (or equivalent single pattern) for the unrendered-field loop so the convention stays consistent across account, settings, notifications, admin, dashboard, and issue UIs.
- **FR-003**: After any intentional early `form_row` / `form_widget` calls for custom layout, the template MUST still invoke the unrendered-field loop before primary submit/cancel actions so newly added Type fields appear automatically.
- **FR-004**: Per-field presentation (`label`, `help`, `attr`, `row_attr`, placeholders, constraints messaging hooks) MUST be defined on the Form Type / FormKit configuration, not duplicated as per-field style wrappers in Twig (form shell, section headings, and action button rows may remain in Twig). For **settings / preferences** shells, the action row MUST follow `078-form-save-restore-actions` (shared Save + Restore labels, primary/ghost styling, actions inside the panel).
- **FR-005**: `form_end` MUST NOT be relied on as the only way to surface newly added fields (avoids fields appearing after the submit button).
- **FR-006**: New host form pages added after this feature MUST follow FR-001–FR-005; this requirement is a standing project condition for Symfony form Twig.

### Key Entities

- **Form Type**: PHP class (typically FormKit-based) that defines field order and presentation options.
- **Form view child**: A named field (or compound) exposed to Twig; `rendered` tracks whether it was already painted.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every host Twig template that calls `form_start` for an app Form Type uses the shared unrendered-field loop (or an equivalent `not rendered` iteration) before action controls.
- **SC-002**: Adding one optional text field to `AccountProfileType` makes it visible on the profile edit form without editing `profile.html.twig` field names (loop already present).
- **SC-003**: No host form template lists every Type field by name as the sole rendering mechanism without a trailing unrendered-field loop.
- **SC-004**: Maintainers can locate the convention in this spec (`077`) when reviewing form PRs.

## Assumptions

- FormKit (`nowo-tech/form-kit-bundle` **≥ 2.2**) remains the preferred way to set field options and defaults. Host kit profiles set `auto_help` / `auto_placeholder: false` where kits only supply labels (`081-formkit-uikit-kit-sync`).
- Global form theme (`templates/form/beacon_theme.html.twig`) continues to supply baseline widget/row markup; Types supply field-specific attrs.
- Kit-owned AuthKit security pages are only in scope where this repo already overrides them under `templates/bundles/` (AuthKit **≥ 1.14** disables convention help/placeholder on login/register/reset forms).
- Admin user/group/project create-edit forms use Form Types + this loop (F2A done). F2B layout (Issue controller split / AdminProject in Project) delivered under `083` / `085`.

## Related

- `078-form-save-restore-actions` — standing convention for Save + Restore action chrome on settings forms (texts, colors, panel placement).
- `081-formkit-uikit-kit-sync` — FormKit profile flags, AuthKit/UiKit/RoutingKit pins.

## Out of Scope

- Migrating every non-Symfony `<form>` in admin CRUD to Form Types
- Changing FormKit bundle internals
- Redesigning appearance color-picker UX beyond complying with the catch-all loop rule
- Standardizing Save / Restore labels and restore semantics (see `078`)
