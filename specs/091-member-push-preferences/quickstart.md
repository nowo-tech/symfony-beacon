# Quickstart: Member Push Notification Preferences

Validate `091-member-push-preferences` after implementation.

## Prerequisites

- Stack up: `make up` (or `docker compose up -d`)
- Mercure enabled under **Administration → Mercure** (sample seed may already enable it)
- Optional Web Push: `VAPID_*` in `.env` + Account → Display → browser push preference (default **on** for new users) + visit a project/issues page so `issue-realtime` can create a `push_subscription` after the browser permission prompt
- Two users (A, B) with access to the same project; browser session as A
- Optional: a third user as **viewer** on the project (for §7)

## 1. Defaults = all on

1. As user A, open Account → Display → Notifications.
2. Confirm member alerts master checked; browser push preference checked (new accounts); all events on; scope all; projects listed (modals) checked or absent rows meaning on.
3. Keep a tab open on the app (Mercure connected — DevTools EventSource to `/users/{uuid}/member-alerts`). Open Issues so Web Push can subscribe if permission is granted (`SELECT COUNT(*) FROM push_subscription` > 0).
4. Ingest a **new** issue into the project (SDK, `make beacon-test ARGS='--message=unique-…'`, or test envelope).
5. **Expect**: live toast for A with event title + project · preview link (same-origin); Web Push only if a `push_subscription` row exists (preference alone is not enough).

## 1b. Preference vs device subscription

1. Enable browser push preference and save.
2. Confirm `push_subscription` may still be **0** until `issue-realtime` runs subscribe on a mounted page.
3. Dogfood: `make beacon-test` warns on zero subscriptions even when preference is on.

## 2. Account master off

1. Uncheck member alerts master; save (LiveComponent).
2. Trigger another new issue.
3. **Expect**: no toast / no push for A. Mercure config should not subscribe (or empty topics).

## 3. Project off (from Account)

1. Re-enable master; open the project modal on Account notifications; disable only this project; save.
2. Trigger new issue on that project.
3. **Expect**: no alert for A.
4. Enable a second project A can access; trigger there → alert resumes for that project only.

## 4. Event + involved-only

1. Set `issue.resolved` to **involved only** (account or project override).
2. Resolve an issue where A is **not** assignee and **not** mentioned → no alert.
3. Assign A (or @mention A), then resolve → alert.

## 5. Lifecycle coverage

With defaults on, spot-check: regression (ingest), reopen, assign, comment — each can produce a member alert when gates pass.

## 6. Ingest safety

With Mercure container stopped (optional), ingest still returns success / Envelope ACK; logs may show publish warnings.

## 7. Viewer can edit own project prefs

1. Sign in as a project **viewer** (cannot open Project Settings).
2. Open Account → Display → Notifications — project appears in the list.
3. Disable the project (or set an event override); save.
4. **Expect**: success flash; prefs persist.
5. Open `/projects/{uuid}/settings` → **Expect**: forbidden (Settings still gated).

## Automated

```bash
make test ARGS='tests/Unit/Notifications/MemberAlertPreferenceEvaluatorTest.php'
make test ARGS='tests/Functional/Notifications/MemberAlertPreferencesFunctionalTest.php'
make test-unit-js ARGS='assets/controllers/issue_realtime_controller.test.ts'
```

See [data-model.md](./data-model.md) and [contracts/](./contracts/).
