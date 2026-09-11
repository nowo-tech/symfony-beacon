# Implementation Plan: Admin / alerts create modals (`112`)

**Branch**: working tree (Unreleased)  
**Spec**: `specs/112-admin-create-modals/spec.md`

## Technical approach

1. **Shared pattern** (already used by admin users / roles):
   - Index/`settings` builds an unbound create `FormInterface` with `action` → create POST route.
   - Twig: `shared/_confirm_dialog.html.twig` with `custom_form`, `open_on_connect`, structured header/content/actions.
   - GET create route redirects to parent `?new=1` or `?new_threshold=1`.
   - Invalid POST re-renders parent via private `renderIndex` / settings builder with `openCreate: true`.

2. **Controllers**
   - `AdminGroupController` — `renderIndex` + `buildCreateForm` (`AdminGroupType`, prefix `admin_group`).
   - `AdminProjectController` — same for `ProjectType` (prefix `project`).
   - `ProjectThresholdRuleController` + `ProjectSettingsPageBuilder` — alerts panel embeds create form; GET new redirects to settings alerts.

3. **i18n**
   - Ensure `translations/form.*.yaml` blocks: `admin_group`, `project` (existing), `project_threshold_rule`.
   - Message intros: `groups.create_intro`, `admin_projects.create_intro`.

4. **QA / docs**
   - PHPUnit: `AdminGroupsTest`, `AdminProjectsTest`, threshold settings coverage.
   - E2E: existing `/admin/groups/new` and `/admin/projects/new` navigations follow redirect; kit-chrome uses `formOnPageOrDialog`.
   - Manual: update `e2e/manual/capture-screens.spec.ts` paths + `docs/manual/05-admin-and-ops.md` / projects chapter notes; recapture PNGs.

## Dependencies

- `086` confirm-dialog + `open_on_connect`
- `077` / FormKit field loop
- `111` screenshot regenerators

## Risks

- E2E that scopes forms to `main` only may miss dialog chrome — prefer `form[action$="…/new"]` or `formOnPageOrDialog`.
- Product `/projects/new` must remain dashboard-modal; do not conflate with admin projects create.
