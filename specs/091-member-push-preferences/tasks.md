# Tasks: Member Push Notification Preferences

**Input**: Design documents from `/specs/091-member-push-preferences/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included for evaluator gates and Account prefs (constitution / SC measurable outcomes).

**Organization**: Phases by user story (US1–US5) after shared foundation.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: US1–US5 map to spec user stories
- Paths are repository-root relative

## Phase 1: Setup

**Purpose**: Enums and shared vocabulary

- [X] T001 [P] Add `MemberAlertEvent` enum in `src/Notifications/Enum/MemberAlertEvent.php`
- [X] T002 [P] Add `MemberAlertScope` enum in `src/Notifications/Enum/MemberAlertScope.php`
- [X] T003 [P] Document feature pointer in `docs/product/NOTIFICATIONS.md` (member prefs section stub linking to Account UI)

---

## Phase 2: Foundational (Blocking)

**Purpose**: Persistence + evaluator + involvement helper — required before UI and delivery changes

- [X] T004 Add `memberAlertsEnabled` (default true) on `src/Identity/Entity/Embeddable/UserUiPreferences.php` with getters/setters and User proxies as needed
- [X] T005 Create Doctrine migration for `member_alerts_enabled` under `migrations/`
- [X] T006 Create entity `src/Notifications/Entity/MemberProjectAlertPreference.php` (user, project, enabled only, unique user+project)
- [X] T006a Create `MemberAccountAlertEvent` + `MemberProjectAlertEvent` entities/repos (relational event rows; no JSON)
- [X] T007 [P] Create `src/Notifications/Repository/MemberProjectAlertPreferenceRepository.php`
- [X] T008 Create migration for `member_project_alert_preference` table under `migrations/`
- [X] T009 Add `IssueMentionRepository` helper (e.g. `isUserMentionedOnIssue`) in `src/Issues/Repository/IssueMentionRepository.php`
- [X] T010 Implement `src/Notifications/Service/MemberAlertPreferenceEvaluator.php` (master ∧ project ∧ event ∧ scope; missing = on/all)
- [X] T011 Implement `src/Notifications/Service/MemberAlertPreferenceManager.php` (load effective prefs, save account + project overrides, reset overrides)
- [X] T012 [P] Unit tests for evaluator truth table in `tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php`
- [X] T013 Extend `IssueRealtimeTopics` with per-user topic helper in `src/Notifications/Realtime/IssueRealtimeTopics.php`

**Checkpoint**: Evaluator + schema ready; no delivery wiring yet

---

## Phase 3: User Story 1 — Account master + event defaults (P1)

**Goal**: Account UI for master + per-event enable/scope; defaults all on; master off stops alerts

**Independent Test**: Save account prefs; master off ⇒ no Mercure subscribe / no delivery for that user

- [X] T014 [US1] Extend Account notifications form (FormKit) in `src/Identity/Form/` (e.g. extend `AccountDisplayType` or new `MemberAlertPreferencesType`)
- [X] T015 [US1] Wire save/load in `src/Identity/Controller/AccountPreferencesController.php` via `MemberAlertPreferenceManager`
- [X] T016 [US1] Update `templates/account/display_notifications.html.twig` — master, events, scopes; keep Web Push device checkbox separate
- [X] T017 [P] [US1] English strings in `translations/messages.en.yaml` (and sync other locale keys as project convention)
- [X] T018 [US1] Update `MemberRealtimeController::config` so Mercure token/topics only when `memberAlertsEnabled` (default true) in `src/Notifications/Controller/MemberRealtimeController.php`
- [X] T019 [US1] Functional test Account prefs save + config gate in `tests/Functional/Notifications/MemberAlertPreferencesFunctionalTest.php` (or Identity account test)

---

## Phase 4: User Story 2 — Per-project enablement AND gate (P1)

**Goal**: Per accessible project enable; delivery requires account master AND project on

**Independent Test**: Project A off / B on ⇒ alerts only for B

- [X] T020 [US2] List projects with `ProjectRepository::findAccessibleByUser` on notifications prefs UI in `templates/account/display_notifications.html.twig` + controller
- [X] T021 [US2] Persist project `enabled` via `MemberProjectAlertPreference` in `MemberAlertPreferenceManager`
- [X] T022 [US2] Ensure evaluator uses project enable (no row = on) — extend unit cases in `tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php`
- [X] T023 [US2] Functional coverage: toggle one project off in `tests/Functional/Notifications/MemberAlertPreferencesFunctionalTest.php` (viewer Account override + Settings forbidden covered in Phase 9 T043)

---

## Phase 5: User Story 3 — Involvement scope (P1)

**Goal**: `all` vs `involved` (assignee or IssueMention)

**Independent Test**: involved-only resolve notifies only when assignee/mentioned

- [X] T024 [US3] Wire involvement check into evaluator using Issue assignee + `IssueMentionRepository` in `MemberAlertPreferenceEvaluator.php`
- [X] T025 [US3] Expose scope controls in Account UI (account defaults) in form + `display_notifications.html.twig`
- [X] T026 [US3] Unit tests for involved vs not in `tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php`

---

## Phase 6: User Story 4 — Expand events beyond new issue (P2)

**Goal**: Member Mercure + Web Push for new, regression, resolve, reopen, assign, comment

**Independent Test**: Each lifecycle dispatch can notify eligible users (hub/push permitting)

- [X] T027 [US4] Refactor `MemberIssueRealtimeNotifier` (+ interface) to `notify(event, project, issue, …)` filtering eligible users in `src/Notifications/Realtime/`
- [X] T028 [US4] Publish private Mercure updates to per-user topics from notifier; keep failures logged non-fatal
- [X] T029 [US4] Call member notifier from `NotificationDispatcher` for regression/resolved/reopened/assigned/commented in `src/Notifications/Service/NotificationDispatcher.php`
- [X] T030 [US4] Ensure payload builders exist/reuse for each event in `src/Notifications/Service/NotificationPayloadBuilder.php`
- [X] T031 [US4] Filter Web Push recipients in `DeliverWebPushForProjectHandler` (+ message carries event/issue context if needed) under `src/Notifications/MessageHandler/`
- [X] T032 [US4] Update Stimulus `assets/controllers/issue_realtime_controller.ts` for user topic subscription shape
- [X] T033 [P] [US4] Unit/integration tests for dispatcher → notifier filtering in `tests/Unit/Notifications/` and/or `tests/Integration/Notifications/`

---

## Phase 7: User Story 5 — Per-project event/scope overrides (P2)

**Goal**: Project overrides merge over account; reset clears overrides

**Independent Test**: Project override involved-only comment; other projects follow account

- [X] T034 [US5] UI for per-project event/scope overrides + reset in `templates/account/display_notifications.html.twig` + form type
- [X] T035 [US5] Merge logic in `MemberAlertPreferenceManager` / evaluator (override wins when set)
- [X] T036 [US5] Tests for override + reset in `tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php` and functional save path

---

## Phase 8: Polish & cross-cutting

- [X] T037 Update `docs/ops/MERCURE.md` and `docs/product/NOTIFICATIONS.md` for user topics + Account prefs (opt-out)
- [X] T038 [P] Update `docs/CHANGELOG.md` / `docs/UPGRADING.md` notes for 091
- [X] T039 Manual pass of `specs/091-member-push-preferences/quickstart.md`
- [X] T040 Confirm Envelope ingest still ACKs when Mercure down (existing ingest functional or short regression)

---

## Phase 9: Auth + client hardening (post-ship review)

**Purpose**: Align gates with FR-013 / SC-007; toast defense in depth

- [X] T041 Replace `requireSettingsSurface` with `requireAccess` on `MemberProjectAlertPreferencesLive::save` and `project_member_alerts_save` in `src/Twig/Components/` + `src/Project/Controller/ProjectController.php`
- [X] T042 [P] Document Account-primary + Settings shortcut + `requireAccess` in `specs/091-member-push-preferences/contracts/account-member-alerts.md`, `spec.md` (FR-013), `docs/product/NOTIFICATIONS.md`
- [X] T043 Functional: viewer saves project override from Account / Settings forbidden in `tests/Functional/Notifications/MemberAlertPreferencesFunctionalTest.php`
- [X] T044 [P] Same-origin toast URL guard + vitest in `assets/controllers/issue_realtime_controller.ts` (+ `.test.ts`); align SW event titles in `PushServiceWorkerListener`

---

## Phase 10: Release polish (post-ship)

**Purpose**: Track follow-ups cut with **v1.8.1** / **v1.8.2** (no new product FR)

- [X] T045 Constructor-inject preference manager / repos on `MemberAlertPreferencesLive` + `MemberProjectAlertPreferencesLive` (cleaner LiveAction signatures)
- [X] T046 [P] PHPStan FormInterface / FormView / Rbac / Ingest `@return` alias clean-up; unit mock `with()` chains
- [X] T047 [P] Rector: skip `RemoveReturnTagIncompatibleWithNativeTypeRector`, `FlipTypeControlToUseExclusiveTypeRector`, `ControllerMethodInjectionToConstructorRector`; `make rector-fix` runs CS Fixer after apply

---

## Dependencies

```text
Phase 1 → Phase 2 → US1 (Phase 3) → US2 (Phase 4) → US3 (Phase 5)
                                              ↘
US4 (Phase 6) needs Phase 2 evaluator + preferably US1–US3 gates
US5 (Phase 7) needs US2 + US3 UI/evaluator
Phase 8 after US4–US5
Phase 9 after Phase 8 (auth/client hardening)
Phase 10 after ship (tooling / Live DI polish)
```

**MVP**: Phase 1–5 (account + project enable + scope) with new-issue delivery still on project topics **or** early cut of user-topic publish for new issue only — prefer completing T027–T028 before calling MVP done so prefs actually filter Mercure.

**Suggested MVP ship**: T001–T026 + minimal T027–T028 + T018 for new-issue path only; then US4/US5. Phase 9 is required before treating viewer Account overrides as done. Phase 10 is post-release maintenance.

## Parallel examples

- After T003: T001/T002 already parallel; T004–T008 can split entity vs migration authorship carefully
- After T011: T012 and T013 in parallel
- US4: T030 payload work parallel to T032 Stimulus once topic contract stable
- Phase 9: T042 docs parallel to T044 client work after T041 gate fix
- Phase 10: T046 / T047 parallel after T045

## Task count

| Area | Tasks |
|------|-------|
| Setup | T001–T003 (3) |
| Foundational | T004–T013 (10) |
| US1 | T014–T019 (6) |
| US2 | T020–T023 (4) |
| US3 | T024–T026 (3) |
| US4 | T027–T033 (7) |
| US5 | T034–T036 (3) |
| Polish | T037–T040 (4) |
| Auth/client | T041–T044 (4) |
| Release polish | T045–T047 (3) |
| **Total** | **47** |
