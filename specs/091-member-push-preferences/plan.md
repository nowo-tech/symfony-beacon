# Implementation Plan: Member Push Notification Preferences

**Branch**: `091-member-push-preferences` | **Date**: 2026-08-12 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/091-member-push-preferences/spec.md`

## Summary

Add **opt-out** member alert preferences (account master + per-event + involvement scope, plus per-project enable/overrides) for Mercure live toasts and Web Push. Expand realtime beyond **new issue** to regression / resolve / reopen / assign / comment. Evaluate prefs **server-side** before publish/send. Keep existing Web Push **device** preference (`UserUiPreferences.pushNotificationsEnabled`, default **on** for new users as of 2026-08-16) separate from the **member alerts master** (default on). A stored `push_subscription` remains required for actual browser push.

## Technical Context

**Language/Version**: PHP 8.5 / Symfony 8.1  
**Primary Dependencies**: `symfony/mercure-bundle`, Messenger, Doctrine ORM, FormKit / UI Kit for Account forms, Stimulus `issue-realtime`  
**Storage**: MySQL — `member_alerts_enabled` on user; `member_project_alert_preference`; relational `member_account_alert_event` + `member_project_alert_event` (no JSON event maps)  
**Testing**: PHPUnit Unit (evaluator) + Functional (Account prefs HTTP) + Integration (dispatch → filtered push/Mercure mock)  
**Target Platform**: Self-hosted Beacon (Docker / FrankenPHP)  
**Project Type**: Modular Symfony app (`Notifications` owns prefs + delivery; `Identity` Account UI; `Issues` involvement queries)  
**Performance Goals**: Preference evaluation must not delay Envelope ACK; fan-out stays on Messenger / afterFlush  
**Constraints**: Opt-out defaults (missing row = on); English UI; no new `env():` defaults in `parameters.yaml`; worker-safe services (`ResetInterface` if request-cached)  
**Scale/Scope**: Self-hosted teams (tens–hundreds of members per project); Account Notifications UI + delivery path changes

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- [x] Spec-first: `specs/091-member-push-preferences/`
- [x] Canonical stack / Docker-first / worker-safe
- [x] English docs/PHPDoc/UI; tests planned
- [x] No new `env(VAR): '…'` defaults in `parameters.yaml`
- [x] Prefer kits for Account forms (FormKit) / layout; no new auth kit
- [x] No Cursor / agent attribution
- [x] Legal: Web Push remains explicit device subscription; prefs copy points members to manage noise via opt-out (Privacy/Terms remain operator pages)

**Post-design**: Still pass — design reuses Notifications module, Messenger, existing Mercure/Web Push channels; no alternate stack.

## Technical approach

| Slice | Approach |
|-------|----------|
| Account prefs | Account → Display → Notifications via LiveComponents (`MemberAlertPreferencesLive`): master **member alerts** (default on), event toggles, default scope; keep existing Web Push device checkbox separate (preference default **on**; device subscribe still via `issue-realtime`) |
| Project prefs | **Primary**: same Account page (list via `ProjectRepository::findAccessibleByUser`) with per-project LiveComponent modals. **Optional shortcut**: Project Settings `#member-alerts` for members who can open Settings. Save gates use `requireAccess` (viewer+), never Settings-admin-only |
| Evaluator | `MemberAlertPreferenceEvaluator` — gates: master ∧ project ∧ event ∧ scope; missing data = on / all-issues |
| Involvement | Assignee OR `IssueMention` for user on issue (new repo helper) |
| Delivery expand | `NotificationDispatcher` calls member notifier on new/regression/resolve/reopen/assign/comment |
| Mercure | Publish **private** updates to **per-user** topics (see research); `/account/realtime/config` JWT subscribes to the current user’s alert topic only when master on |
| Web Push | `DeliverWebPushForProjectHandler` filters via `eligibleUserIds` from evaluator (+ existing device opt-in) |
| Client | Stimulus toasts: structured event labels; same-origin URL check; SW push titles aligned |
| Async | Keep afterFlush / Messenger; never fail ingest on alert errors |

## Project Structure

### Documentation (this feature)

```text
specs/091-member-push-preferences/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── account-member-alerts.md
│   └── member-alert-events.md
├── checklists/requirements.md
└── tasks.md                 # /speckit-tasks (not this command)
```

### Source Code (repository root)

```text
src/Notifications/
├── Entity/MemberProjectAlertPreference.php
├── Entity/MemberAccountAlertEvent.php
├── Entity/MemberProjectAlertEvent.php
├── Enum/MemberAlertEvent.php
├── Enum/MemberAlertScope.php
├── Realtime/MemberIssueRealtimeNotifier.php      # expand events + filtered publish
├── Realtime/IssueRealtimeTopics.php              # add user alert topic helper
├── Service/MemberAlertPreferenceEvaluator.php
├── Service/MemberAlertPreferenceManager.php      # load/save account+project
├── Repository/MemberProjectAlertPreferenceRepository.php
├── Message/DeliverWebPushForProjectMessage.php   # eligibleUserIds + payload
└── MessageHandler/DeliverWebPushForProjectHandler.php

src/Identity/
├── Entity/Embeddable/UserUiPreferences.php       # memberAlertsEnabled
├── Controller/AccountPreferencesController.php
└── Form/MemberAlertPreferencesType.php
    Form/MemberProjectAlertPreferencesType.php

src/Twig/Components/
├── MemberAlertPreferencesLive.php
└── MemberProjectAlertPreferencesLive.php         # requireAccess on save

src/Project/Controller/ProjectController.php      # settings shortcut + POST save (requireAccess)

src/Issues/Repository/IssueMentionRepository.php  # isUserMentionedOnIssue

assets/controllers/issue_realtime_controller.ts   # user topic + safe toast URLs
src/Shared/Pwa/PushServiceWorkerListener.php      # event titles / preview body
templates/account/display_notifications.html.twig
templates/project/settings.html.twig              # #member-alerts (Settings-gated UI)

tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php
tests/Functional/Notifications/MemberAlertPreferencesFunctionalTest.php
```

**Structure Decision**: Preferences and delivery stay in `Notifications`; Account HTTP/forms in `Identity` (existing Account prefs home); LiveComponents under `App\Twig\Components`; involvement query in `Issues`. Matches constitution module map.

## Authorization (must not regress)

| Action | Gate |
|--------|------|
| View Account notifications | `ROLE_USER` |
| Save account matrix | `ROLE_USER` (session user only) |
| Save per-project override | `ROLE_USER` + `ProjectAccessService::requireAccess` |
| Open Project Settings page (incl. `#member-alerts` section) | `requireSettingsSurface` (unchanged for secrets/destinations) |
| POST `project_member_alerts_save` | `requireAccess` (not Settings-admin) |

## Testing

- Unit: evaluator truth table (defaults, master off, project off, event off, involved-only)
- Functional: save Account prefs; LiveComponent cascading; **viewer can save project override / cannot open Settings**; CSRF Live pattern
- Integration / functional: dispatch lifecycle → Mercure hub mock / push handler only targets eligible users
- JS unit: toast same-origin URL rejection
- Regression: Envelope ACK still succeeds when Mercure publish throws

## Complexity Tracking

None — justified complexity is product scope (prefs matrix), not stack violation.

## Shipped

- **v1.8.0** — feature + Phase 9 auth/client hardening.
- **v1.8.1** — LiveComponent constructor DI (`Member*AlertPreferencesLive`); PHPUnit helper naming.
- **v1.8.2** — PHPStan generics / array-shape docs; conservative Rector skips; CS after Rector in Make.
- **v1.23.2** — Rector 2.6.2: no `SymfonySetList::SYMFONY_*` version constants; Symfony code-quality sets only.
