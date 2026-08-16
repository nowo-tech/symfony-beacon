# Contract: Member alert events (internal delivery)

Not a public HTTP API. Describes the dispatcher → member channel contract.

## Events

| Event id | Dispatcher entry | Payload builder |
|----------|------------------|-----------------|
| `issue.new` | `dispatchNewIssue` | `forNewIssue` |
| `issue.regression` | `dispatchIssueRegression` | `forIssueRegression` |
| `issue.resolved` | `dispatchIssueResolved` | `forIssueResolved` |
| `issue.reopened` | `dispatchIssueReopened` | `forIssueReopened` |
| `issue.assigned` | `dispatchIssueAssigned` | `forIssueAssigned` |
| `issue.commented` | `dispatchIssueCommented` | `forIssueCommented` |

## Evaluation order

1. User has project access  
2. `memberAlertsEnabled` (missing → true)  
3. Project preference enabled (missing row → true)  
4. Event enabled after merge (missing → true)  
5. Scope `all` **or** (`involved` and user involved)  
6. Channel: Mercure if hub enabled; Web Push if VAPID + `pushNotificationsEnabled` (default true for new users) + stored `push_subscription` 

## Mercure publish

- Topic: `/users/{userUuid}/member-alerts` (private update; may batch multiple user topics in one `Update` if hub allows, or one publish per user)
- Body: JSON payload including at least `event`, project/issue identifiers, title/culprit preview fields, and issue URL

## Client toast / Web Push presentation

- Stimulus `issue-realtime` builds toasts with `textContent` (no `innerHTML` for payload fields).
- Toast **link `href`** MUST be same-origin absolute URL or root-relative path; reject `javascript:` and cross-origin URLs (defense in depth).
- Event titles come from Twig-translated labels (`toast.event.*`) with English fallbacks in the controller / service worker.
- Web Push SW titles use the same event map; body prefers `project · issue preview`.

## Web Push

- Message includes `event` + issue payload + optional `eligibleUserIds` (null = legacy all push-enabled members; empty = none)
- Handler iterates project members, skips users failing evaluation or without push preference / `push_subscription` 

## Failure

Log and continue; never fail the calling triage/ingest path.
