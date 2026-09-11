# Tasks: Admin / alerts create modals (`112`)

## Phase 1: Threshold create modal

- [x] T001 Settings → Alerts embeds create dialog; GET `…/threshold-rules/new` → `?new_threshold=1`
- [x] T002 FormKit `project_threshold_rule.*` catalogue (replace raw / `thresholds.form` keys)
- [x] T003 PHPUnit / E2E + manual shot `project-threshold-rules-new`

## Phase 2: Admin group create modal

- [x] T004 `AdminGroupController` redirect + `renderIndex` / `buildCreateForm`
- [x] T005 `templates/admin/groups/index.html.twig` confirm-dialog create
- [x] T006 FormKit `admin_group.*` + `groups.create_intro`
- [x] T007 `AdminGroupsTest` + capture `admin-groups-new`

## Phase 3: Admin project create modal

- [x] T008 `AdminProjectController` redirect + `renderIndex` / `buildCreateForm`
- [x] T009 `templates/admin/projects/index.html.twig` confirm-dialog create
- [x] T010 `admin_projects.create_intro` + `AdminProjectsTest` + capture `admin-projects-new`

## Phase 4: Specs / roadmap

- [x] T011 Add `specs/112-admin-create-modals/` + amend `002` / `019` / `027` / `036` / `086` / `111`
- [x] T012 ROADMAP Phase 6.64 + Unreleased note
