# Feature Specification: Form Save + Restore actions

**Feature Branch**: `078-form-save-restore-actions`

**Created**: 2026-08-03

**Status**: Specified (spec + checklist only; implementation deferred)

**Input**: User description: "All settings forms must have Save and Restore; standardize form action button texts and colors. Prefer buttons inside the panel/card."

**Related**: Complements standing form field-loop convention in `077-form-type-field-loop` (fields before actions; action chrome may remain in Twig).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consistent Save + Restore on settings forms (Priority: P1)

As an operator, when I edit a settings or preferences form, I always see a **Save** primary action and a **Restore** secondary action with the same wording across locales and screens, so I do not have to interpret per-page labels such as “Save display” or “Save appearance”.

**Why this priority**: Removes inconsistent CTAs (“Guardar interfaz”, “Guardar perfil”, “Guardar apariencia”, cancel links used as the only secondary action) and matches the Appearance pair already in product.

**Independent Test**: Open Preferences → Profile, Preferences → Display (any tab with a settings form), and Settings → Appearance; confirm both buttons use the shared Save / Restore labels and sit inside the same panel as the fields.

**Acceptance Scenarios**:

1. **Given** an in-scope settings form page, **When** the form shell renders, **Then** the action row contains Save (primary) and Restore (secondary) inside the same panel/card as the fields.
2. **Given** two different in-scope settings forms, **When** comparing visible button labels in the same locale, **Then** both use the same Save and Restore strings (not page-specific submit copy).

---

### User Story 2 - Restore resets to defaults or discards unsaved edits (Priority: P1)

As an operator, when I click Restore, the form returns to a known baseline: system/instance defaults when the feature defines them (Appearance pattern), otherwise the last persisted values (discard unsaved edits). Restore is not a navigation “Cancel” that merely leaves the page.

**Why this priority**: Operators expect Restore to undo local edits or reset branding defaults without hunting for a separate control.

**Independent Test**: On Appearance, change a color, click Restore, confirm defaults are applied and saved per existing Appearance behaviour; on a preferences form without instance defaults, change a field, click Restore, confirm persisted values are shown again (unsaved edits discarded).

**Acceptance Scenarios**:

1. **Given** a settings form with defined system/instance defaults (e.g. Appearance), **When** the operator submits Restore, **Then** stored values are reset to those defaults and the UI reflects them after the round-trip.
2. **Given** a settings form without instance defaults, **When** the operator submits Restore after editing fields, **Then** unsaved edits are discarded and the form shows the last persisted values (or equivalent documented discard behaviour).
3. **Given** a settings form that currently shows only Save + a cancel/back link, **When** this feature is applied, **Then** Restore is present as a form action; a cancel/back link may remain as navigation but MUST NOT replace Restore.

---

### User Story 3 - Shared action chrome for new settings forms (Priority: P2)

As a maintainer, when I add a new settings/preferences form, I reuse the shared action-row pattern (labels, button roles/colors, placement inside the panel) instead of inventing contextual submit strings or putting primary actions outside the card.

**Why this priority**: Keeps the standing convention discoverable next to `077` and avoids regressing the recent “actions inside panel” UI rule.

**Independent Test**: Review the shared action partial (or documented equivalent) and confirm a new settings template can include Save + Restore without hard-coding per-feature label keys.

**Acceptance Scenarios**:

1. **Given** a new in-scope settings Twig shell, **When** it is implemented under this convention, **Then** it uses the shared action row (or equivalent single pattern) with common Save / Restore labels and primary/secondary button styling.
2. **Given** a PR that adds a settings form, **When** reviewers check against this spec (`078`), **Then** they can reject page-specific Save labels and missing Restore without relying on tribal knowledge.

### Edge Cases

- Forms with both Restore and a cancel/back link: Restore discards/resets; cancel/back navigates away without requiring a reset submit.
- Create / one-shot dialogs (new project, new user): out of scope — may keep contextual primary labels (Create) without Restore.
- Filters, issue triage, danger-zone confirms, AuthKit login/register (unless host-overridden as settings), PWA install links: out of scope.
- Hand-rolled HTML settings forms (e.g. project governance): in scope for the same Save + Restore labels and placement when they are settings shells; wiring Restore semantics follows FR-004.
- CSRF / Form Type field loop (`077`): action row remains after the unrendered-field loop; Restore MUST NOT cause fields to render after the buttons.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: In-scope settings form shells MUST render an action row with **Save** (primary submit) and **Restore** (secondary submit that signals restore/reset, e.g. `name="reset"`) **inside** the same panel/card as the fields (bordered action row consistent with other Beacon settings panels).
- **FR-002**: Save and Restore button labels MUST come from shared translation keys `common.save` and `common.restore` in every locale shipped by this repo (English source: “Save” / “Restore”; Spanish: “Guardar” / “Restaurar”). Page-specific keys such as `preferences.display.submit` or `appearance.reset` MUST NOT remain as the visible labels on in-scope forms once this feature is implemented.
- **FR-003**: Host templates MUST use a shared Twig partial (or one equivalent pattern) for the settings action row so Save/Restore chrome stays consistent; a cancel/back navigation control MUST NOT substitute for Restore.
- **FR-004**: When Restore is submitted, the server MUST apply an explicit restore path: reset entity/settings to system or instance defaults when the feature defines them; otherwise discard unsaved edits and re-present last persisted values. Controllers MUST NOT treat Restore as a no-op that only re-renders the posted invalid state without documenting discard/default behaviour.
- **FR-005**: Button styling MUST be standardized: Save uses primary action styling (`btn-primary`); Restore uses secondary/ghost styling (`btn-ghost`). In-scope forms MUST NOT use a third-party default primary color (e.g. stock blue) for these actions.
- **FR-006**: New in-scope settings form pages added after this feature MUST follow FR-001–FR-005; this is a standing project condition alongside `077` for settings form Twig.

### Key Entities

- **Settings form shell**: Account preferences, admin instance settings, and project settings editors listed in Scope (below).
- **Save action**: Primary submit that persists the current valid form data.
- **Restore action**: Secondary submit that resets to defaults or discards unsaved edits per FR-004.
- **Shared action row**: Common Twig pattern for Save + Restore inside the panel.

## Scope

### In scope

- Account: profile, security (password change form), display preferences tabs that submit a settings Form Type.
- Admin settings: appearance, mailer DSN form, mercure, ops defaults, social login create/edit.
- Project: notification destination form, threshold rule form, governance settings form.

### Out of scope

- List filters, analytics filters, saved-view controls
- Create dialogs and one-shot “add” forms (project, user, API key, member, group link)
- Issue triage, comments, assignee/priority mini-forms, duplicate dialogs
- Danger zone / anonymize / delete confirms
- Pure AuthKit vendor security pages unless already overridden as host settings UI
- PWA install / uninstall links and install prompt
- Migrating every hand-rolled admin CRUD create form to Form Types (separate from `077`)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every in-scope settings form template shows both Save and Restore inside the panel, using `common.save` / `common.restore` (or their translations) as visible labels.
- **SC-002**: Appearance Restore continues to reset branding defaults; at least one preferences form without instance defaults demonstrates discard-unsaved Restore behaviour.
- **SC-003**: No in-scope settings form uses a unique Save label key as the only primary CTA, or Cancel alone as the only secondary action in place of Restore.
- **SC-004**: Maintainers can locate the convention in this spec (`078`) and the cross-reference from `077` when reviewing settings form PRs.

## Assumptions

- English remains the source language for UI strings and docs; other locales follow existing translation files.
- Form field rendering continues to follow `077` (unrendered-field loop before the action row).
- Appearance’s existing `reset` request handling is the reference implementation for “defaults” Restore.
- FormKit remains preferred for field options; action buttons stay in Twig chrome.

## Out of Scope

(See Scope → Out of scope above.)
