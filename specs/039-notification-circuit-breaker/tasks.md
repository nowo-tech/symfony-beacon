# Tasks: Notification Circuit Breaker

**Input**: Design documents from `/specs/039-notification-circuit-breaker/`  
**Prerequisites**: plan.md, research.md, data-model.md, contracts/

## Phase 1: Setup

- [x] T001 Add env defaults + parameters for circuit threshold/cooldown in `.env.dist` and `config/parameters.yaml`
- [x] T002 [P] Document behaviour in `docs/NOTIFICATIONS.md` (short section)

## Phase 2: Foundational

- [x] T003 MDK migration `Version20260731120000` — `consecutive_failures`, `circuit_opened_at` on `notification_destination`
- [x] T004 Extend `NotificationDestination` with fields + `recordDeliverySuccess`/`Failure` circuit logic + `openCircuit`/`resumeCircuit`/`isCircuitOpen`
- [x] T005 Add `NotificationCircuitBreaker` service (threshold/cooldown from parameters; `shouldSkipDelivery`)

## Phase 3: User Story 1 — Auto-pause + resume (P1)

**Goal**: Trip after N failures; skip deliveries; admin resume.

- [x] T006 Wire recorder to increment/reset via entity methods; trip when threshold hit
- [x] T007 Skip open circuits in `DeliverNotificationHandler` and `NotificationDispatcher` (allow send-test)
- [x] T008 Add `project_notification_resume` POST + CSRF in `ProjectNotificationController`
- [x] T009 UI: Auto-paused badge + Resume button in `templates/project/settings.html.twig` (destinations + health)
- [x] T010 i18n keys in `translations/messages.en.yaml` (+ other locales)

## Phase 4: Tests & docs

- [x] T011 PHPUnit: trip after N failures, skip while open, resume clears, success resets counter
- [x] T012 CHANGELOG Unreleased + UPGRADING placeholder notes for v0.15
- [x] T013 Mark ROADMAP 6.6 Done (v0.15.0) when code lands

## Dependencies

T001 → T005 → T006/T007 → T008/T009 → T011  
T003 → T004 → T006

## Parallel examples

- T002 || T003 after T001
- T008 || T009 after T007
