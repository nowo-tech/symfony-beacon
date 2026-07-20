# Feature Specification: Project Danger Zone

**Feature Branch**: `011-project-danger-zone`

**Created**: 2026-07-20

**Status**: Completed (shipped in v0.4.0; confirm-dialog UX hardened in later releases)

**Input**: User description: "Do I have options to delete a project and to empty its history? Both should open a warning modal; delete should require typing a confirmation text."

## Clarifications

### Session 2026-07-20

- **Q1 (clear history scope)**: Removes issues + events, performance transactions/spans, and daily analytics stats. Keeps the project, memberships, and API keys.
- **Q2 (permissions)**: Clear history — Owner or Admin. Delete project — Owner only.
- **Q3 (typed confirm)**: Delete requires typing the project **name** exactly (case-sensitive). Clear history uses a warning modal with an explicit confirm button (no typed phrase).
- **Q4 (UI placement)**: Danger zone section on the **project Settings** page (`/projects/{id}/settings`). (Project show redirects to Issues; management actions live in Settings.)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Clear project history (Priority: P1)

As a project owner or admin, I can empty telemetry history so the project stays but past issues/perf/analytics are gone.

**Independent Test**: Seed issues/events for a project; open Settings danger zone; confirm clear in modal; assert telemetry is gone and project/keys remain.

**Acceptance Scenarios**:

1. **Given** I am owner/admin on a project with history, **When** I open Clear history on Settings and confirm in the modal, **Then** issues/events/perf/stats for that project are removed and I stay on Settings with a success flash.
2. **Given** I am a member (not admin), **When** I view Settings, **Then** I do not see Clear history (or the action is denied).
3. **Given** the clear modal is open, **When** I cancel, **Then** no data is deleted.

### User Story 2 - Delete project with typed confirmation (Priority: P1)

As a project owner, I can permanently delete a project after typing its name in a warning modal.

**Independent Test**: As owner, open delete modal, type wrong name (submit disabled/rejected), type exact name, submit; assert project gone and redirect to dashboard.

**Acceptance Scenarios**:

1. **Given** I am the owner, **When** I open Delete project on Settings, **Then** a modal warns that deletion is permanent and asks me to type the project name.
2. **Given** the typed name does not match, **When** I try to submit, **Then** the delete does not proceed (client disables submit; server rejects mismatch).
3. **Given** I type the exact project name and confirm, **When** the form posts, **Then** the project and related data are removed and I am redirected to the dashboard with a success flash.
4. **Given** I am admin or member (not owner), **When** I view Settings, **Then** Delete project is not available (or denied).

## Requirements *(mandatory)*

- **FR-001**: Danger zone on **project Settings** with Clear history and Delete project (`project_clear_history`, `project_delete`).
- **FR-002**: Both actions open warning modals (native `<dialog>` via Stimulus `confirm-dialog`).
- **FR-003**: Delete requires CSRF + exact project name confirmation server-side.
- **FR-004**: Clear requires CSRF + explicit confirm; removes history only (`ProjectHistoryClearer`).
- **FR-005**: Delete uses DB cascades / entity remove so keys, memberships, and telemetry are gone with the project.

## Success Criteria

- **SC-001**: Owner can clear history and delete with typed name; non-owners cannot delete; non-admin members cannot clear.
- **SC-002**: Cancelling either modal leaves data unchanged.
- **SC-003**: Covered by `ProjectDangerZoneTest` (or equivalent) under `tests/`.
