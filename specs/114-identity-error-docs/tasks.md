# Tasks: Visual identity book + error manual docs

**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)  
**Prerequisites**: Spec Kit package present; warm E2E stack for captures / smoke.

## Phase 1: Spec Kit scaffolding

- [x] T001 Create `specs/114-identity-error-docs/` (`spec.md`, `plan.md`, `tasks.md`, `quickstart.md`, `research.md`, `data-model.md`, `contracts/`, `checklists/`)
- [x] T002 Point `.specify/feature.json` at `specs/114-identity-error-docs`

## Phase 2: Visual identity book (US1)

- [x] T003 [US1] Author `docs/identity/README.md` + preview images under `docs/identity/images/`
- [x] T004 [US1] Link identity book from `docs/README.md`, `docs/manual/README.md`, `docs/wiki/Home.md`, root `README.md`
- [x] T005 [US1] Record REQ-DOCS-APP-005 ✅ in `docs/ops/ENGINEERING-AUDIT.md`

## Phase 3: Error manual chapter (US2–US3)

- [x] T006 [US2] Write `docs/manual/08-errors.md` for supported status codes + maintenance preview
- [x] T007 [US2] Index chapter 08 in `docs/manual/README.md` and `docs/wiki/Home.md`
- [x] T008 [US3] Extend `e2e/manual/capture-screens.spec.ts` with `error-*` + `error-maintenance` paths and EN gate
- [x] T009 [US3] Commit 1440×900 PNGs under `docs/manual/images/error-*.png`
- [x] T010 [P] Amend `specs/111-product-ui-manual/spec.md` (+ plan/tasks as needed) for chapter 08 and identity companion link

## Phase 4: Transparent runtime art (US4)

- [x] T011 [US4] Ensure `public/brand/mascot.png` and `public/illustrations/error-*.png` are PNG RGBA transparent
- [x] T012 [US4] Add / keep PHPUnit IHDR + color-type assertions for those files
- [x] T013 [P] Amend `specs/063-branded-http-errors/spec.md` FR-002 item 9 (transparent PNG rule)

## Phase 5: CSP + markup smoke (US5)

- [x] T014 [US5] Stamp CSP nonce onto bare `<style>` in `ContentSecurityPolicySubscriber`
- [x] T015 [US5] Unit-test style nonce stamping
- [x] T016 [US5] Add `e2e/support/html-markup.ts` + `e2e/smoke/html-twig-standardization.spec.ts` (UC-UI-13)

## Phase 6: Mailpit Make + SiteBackup

- [x] T017 Add `make test-e2e-mailpit` (Compose `mail`, `PLAYWRIGHT_MAILPIT=1`, workers=1, deliverable DSN)
- [x] T018 Document Mailpit lane in `e2e/README.md` / Makefile help
- [x] T019 Add `templates/kit/site_backup_panel_login.html.twig` and wire `panel_login` in `nowo_site_backup.yaml`

## Phase 7: Legal / audit notes + close-out

- [x] T020 Record REQ-CC-010 counsel-pending table in `docs/product/LEGAL-AND-COOKIES.md` (no fake production clearance)
- [x] T021 Add ROADMAP Unreleased Phase **6.66** row pointing at this package
- [x] T022 Keep CHANGELOG Unreleased bullets aligned (identity, transparent art, errors chapter, CSP/markup/Mailpit as shipped)
- [x] T023 Refresh `specs/README.md` Spec Kit index

## Phase 8: Manual capture hygiene (US6, 2026-09-17)

- [x] T024 [US6] Add `filterManualDashboardProjects` + use on `dashboard` / private prefs theme shots
- [x] T025 [US6] Add `clearMaintenanceScheduleForManual` before `error-maintenance` capture
- [x] T026 [US6] Cold setup `lockSetupEnglish` + path-locale EN; harden `ensureEnglishUi` for `<a hreflang>`
- [x] T027 [US6] Recapture affected PNGs (`setup-*`, `dashboard*`, `prefs-private-*`, `error-maintenance`, related inventory)
- [x] T028 [P] Amend `111` FR-015 / FR-016; CHANGELOG Unreleased + ROADMAP 6.66 hygiene note

## Phase 9: Footer locale + admin Groups polish (US6–US7, 2026-09-17)

- [x] T029 [US7] Sync fragment sub-request locale from main in `UserPreferredLocaleSubscriber` (+ unit test)
- [x] T030 [US7] Pass request locale into `_legal_footer` `|trans` explicitly
- [x] T031 [US6] Add `filterManualAdminGroups` + Makefile purge of `%e2e%` groups before `docs-manual-screenshots`
- [x] T032 [US6] Recapture `dashboard` / prefs / `admin-groups` (EN footer + `Beacon operators`)
- [x] T033 [P] Amend `111` FR-017; CHANGELOG Fixed/Changed; contracts + this package FR-013 / FR-014

## Dependencies

```text
T001–T002 → T003–T019 (implementation already in tree)
T010 ∥ T013 ∥ T021–T023 (spec amendments + roadmap)
T011 → T012
T014 → T015 → T016
T024–T027 → T028
T029–T032 → T033
```

## Parallel opportunities

- T010 / T013 / T021–T023 can land with this Spec Kit write-up.
- Captures (T008–T009, T027, T032) need warm `ready-e2e` / cold stack; identity prose (T003–T004) does not.

## Implementation strategy

1. Spec package + feature.json (this pass).
2. Confirm working-tree deliverables match FR-001…014.
3. Amend 063 / 111 + ROADMAP / CHANGELOG / specs README.
4. Ship under CHANGELOG Unreleased until the next patch tag.
