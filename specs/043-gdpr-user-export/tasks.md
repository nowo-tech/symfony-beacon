# Tasks: GDPR User Export and Anonymize

**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)

## Phase 0: Spec & plan

- [x] T000 Draft `spec.md`
- [x] T001 Plan / research / data-model / contracts / quickstart
- [x] T002 Expand `tasks.md`

## Phase 1: Foundation

- [x] T003 Add `User::$anonymizedAt` + MDK migration `Version20260731140000`
- [x] T004 Add `UserActionType::AccountExported` + `UserAnonymized`; extend `AdminIdentityAudit`
- [x] T005 [P] Implement `AccountDataExporter`
- [x] T006 [P] Implement `AccountAnonymizer` (blocks + scrub + logout helper)

## Phase 2: US1 Export (P1)

- [x] T007 `AccountPrivacyController` privacy page + `GET .../export`
- [x] T008 Admin `GET /admin/users/{uuid}/export`
- [x] T009 Twig privacy tab + admin actions; i18n keys (en + other locales)

## Phase 3: US2 Anonymize (P1)

- [x] T010 Self `POST .../anonymize` with confirm + session invalidate
- [x] T011 Admin `POST .../anonymize` (not self / not last admin / not sole owner)
- [x] T012 Docs: LEGAL-AND-COOKIES retention note; DATABASE.md column

## Phase 4: Tests & polish

- [x] T013 `tests/Functional/Identity/GdprAccountExportTest.php`
- [x] T014 CHANGELOG / ROADMAP / UPGRADING; mark spec Implemented
