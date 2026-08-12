# Research: Member Push Notification Preferences

## R1 — Mercure targeting vs project topics

**Decision**: Publish private Mercure updates to a **per-user** topic (e.g. `/users/{userUuid}/member-alerts`). `/account/realtime/config` issues a subscriber JWT for that topic when the account master is on (and Mercure instance-enabled). Stop relying on project-wide `/projects/{uuid}/issues` for preference-filtered delivery.

**Rationale**: Project topics cannot enforce per-user event/scope prefs server-side; all JWT holders receive the same update. Client-side filtering would still deliver unwanted events and cannot reliably encode involvement without leaking audience lists.

**Alternatives considered**:
- Client filter on project topics — rejected (noise, weak enforcement, involvement awkward).
- N private publishes reusing project topic with user-specific JWTs — Mercure private delivery is topic+JWT based; still need distinct topics or subscription tricks.
- Keep project topics for “all on” only — rejected; prefs must always apply.

## R2 — Separate master alerts from Web Push device opt-in

**Decision**: Add `memberAlertsEnabled` (default **true**) distinct from existing `pushNotificationsEnabled` (default **false**, device/browser push).

**Rationale**: Spec opt-out for alert *content* vs explicit device permission for Web Push APIs. Reusing one flag would either spam push without consent or silence live toasts by default.

**Alternatives considered**: Reuse `pushNotificationsEnabled` as master — rejected (conflicts with FR opt-out and browser permission UX).

## R3 — Storage shape

**Decision**:
- Account master: `member_alerts_enabled` bool on `app_user` (default true).
- Account event deviations: table `member_account_alert_event` `(user_id, event, enabled, scope)` — no row = on + all.
- Project master: table `member_project_alert_preference` `(user_id, project_id, enabled)` — no row = project on.
- Project event overrides: table `member_project_alert_event` `(user_id, project_id, event, enabled, scope)` — no row = inherit account.

**Rationale**: Relational rows avoid JSON blobs; MySQL uniqueness is clean without nullable project_id tricks; opt-out storage stays sparse.

**Alternatives considered**: JSON maps on user/project preference rows — rejected by product (prefer tables). Single table with nullable `project_id` — rejected (MySQL UNIQUE + NULL pitfalls).

## R4 — Event coverage wiring

**Decision**: Extend `MemberIssueRealtimeNotifier` (or rename to member alert notifier) with methods / single `notify(event, project, issue, …)` and call from each relevant `NotificationDispatcher::dispatch*` (new, regression, resolved, reopened, assigned, commented). Mentions piggyback on comment path when the mentioned user is in the eligible set (comment event on + involvement includes mention).

**Rationale**: Dispatcher already is the fan-out hub for project destinations; member channel should sit beside it without new ingest hooks.

**Alternatives considered**: Separate Doctrine listeners — duplicates lifecycle knowledge. Only expand Web Push — would leave Mercure new-issue-only.

## R5 — Involvement definition (v1)

**Decision**: Involved = current **assignee** OR has an **`IssueMention`** row for that issue+user.

**Rationale**: Matches spec Key Entities and existing `040` data; no issue “reporter” user on ingest-created issues.

**Alternatives considered**: Include comment authors — noisier; defer. Include historical assignees — needs history table; defer.

## R6 — Async / ingest safety

**Decision**: Keep member alert work in afterFlush callbacks / Messenger (Web Push already async). Catch Mercure publish failures and log; never throw into Envelope ACK path.

**Rationale**: FR-010 / constitution efficient ingest.

## R7 — UI placement

**Decision**: **Primary** UI under Account → Display → Notifications (LiveComponents). List accessible projects via `ProjectRepository::findAccessibleByUser`. **Optional** Project Settings section `#member-alerts` for members who already can open Settings (secrets/destinations surface). Per-project **save** always uses `ProjectAccessService::requireAccess` (viewer+), never `requireSettingsSurface`, so viewers edit own overrides from Account without seeing DSN/keys.

**Rationale**: FR-008 / FR-013 — prefs are personal; Settings admin is unrelated. Spec allows Account and/or project entry; Account already hosts push opt-in and must remain sufficient alone.

**Alternatives considered**: Settings-only project overrides — rejected (viewers/members without settings grants could see projects in Account but get 403 on save). Duplicate full settings page for alerts — rejected (noise).

## R8 — LiveComponent CSRF

**Decision**: Member alert Live forms set Symfony form `csrf_protection: false`; Live endpoint retains Symfony UX CSRF (`X-Requested-With` + component token). Document in `config/packages/csrf.yaml`.

**Rationale**: SameOrigin one-shot form CSRF fails after Live re-renders (toggles). Pattern already used elsewhere in the host.

## R9 — Toast URL hardening

**Decision**: Client accepts only same-origin or root-relative `payload.url` before setting toast link `href`.

**Rationale**: Defense in depth if a Mercure publisher JWT were compromised; server already emits Symfony-generated absolute URLs.
