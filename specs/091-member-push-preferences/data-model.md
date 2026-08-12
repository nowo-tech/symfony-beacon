# Data Model: Member Push Notification Preferences

## Enumerations

### `MemberAlertEvent`

| Value | Trigger |
|-------|---------|
| `issue.new` | New issue from ingest |
| `issue.regression` | Regression from ingest |
| `issue.resolved` | Status → resolved |
| `issue.reopened` | Status → reopened |
| `issue.assigned` | Assignee changed |
| `issue.commented` | Comment created (mentions included via involvement) |

### `MemberAlertScope`

| Value | Meaning |
|-------|---------|
| `all` | Any issue in the project (default) |
| `involved` | Only if member is involved |

## Account master (`UserUiPreferences` / `app_user`)

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| `memberAlertsEnabled` | bool | `true` | Master gate (opt-out) |
| `pushNotificationsEnabled` | bool | `false` | **Unchanged** — Web Push device opt-in |

## `member_account_alert_event`

Account-level **deviations** only (opt-out). Missing row for an event ⇒ enabled + scope `all`.

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | PK |
| `user_id` | FK → app_user | CASCADE |
| `event` | varchar(32) | `MemberAlertEvent` value |
| `enabled` | bool | |
| `scope` | varchar(16) | `all` \| `involved` |
| `created_at` / `updated_at` | datetime | |

**Unique**: `(user_id, event)`.

## `member_project_alert_preference`

Per-user per-project **master** enable (no event payload).

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | PK |
| `user_id` | FK | CASCADE |
| `project_id` | FK | CASCADE |
| `enabled` | bool | default true when row exists to disable or hold overrides |
| `created_at` / `updated_at` | datetime | |

**Unique**: `(user_id, project_id)`.  
**Semantics**: no row ⇒ project enabled.

## `member_project_alert_event`

Per-user per-project **event overrides**. Missing row ⇒ inherit account (or global default).

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | PK |
| `user_id` | FK | CASCADE |
| `project_id` | FK | CASCADE |
| `event` | varchar(32) | |
| `enabled` | bool | |
| `scope` | varchar(16) | |
| `created_at` / `updated_at` | datetime | |

**Unique**: `(user_id, project_id, event)`.

## Involvement (read model)

No new table. Derived:

1. `Issue.assignee === user`, or
2. Exists `IssueMention` where `mentionedUser === user` and mention’s comment belongs to the issue.

## Relationships

```text
User ── memberAlertsEnabled (embed)
User 1──* MemberAccountAlertEvent
User 1──* MemberProjectAlertPreference *──1 Project
User 1──* MemberProjectAlertEvent *──1 Project
Issue *──0..1 User (assignee)
Issue 1──* IssueComment 1──* IssueMention *──1 User
```

## Authorization (prefs rows)

- Rows are always owned by the signed-in user (no cross-user edits).
- Creating/updating `MemberProjectAlertPreference` / `MemberProjectAlertEvent` requires **project access** for that `project_id` (viewer+), not Settings-admin.
- Delivery evaluation also requires current project access; orphaned prefs after loss of access must not deliver.