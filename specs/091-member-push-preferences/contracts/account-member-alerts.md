# Contract: Account member alert preferences

## UI

| Surface | Who | What |
|---------|-----|------|
| **Account → Display → Notifications** (`account_display_notifications`) | Any signed-in member (`ROLE_USER`) | Account master + event defaults + **per accessible project** overrides (modals / LiveComponents) |
| **Project → Settings → `#member-alerts`** (`project_settings`) | Members who can open Settings (`canOpenSettings`) | Shortcut to the **same** own-prefs form for the current project |
| **POST** `project_member_alerts_save` / LiveComponent `MemberProjectAlertPreferencesLive::save` | Signed-in user with **project access** | Persist own per-project prefs |

- **Auth (account page)**: `ROLE_USER` — own prefs only (no `userId` in forms; FKs bound to session user).
- **Auth (per-project save)**: `ROLE_USER` + `ProjectAccessService::requireAccess` (viewer+ / equivalent issue visibility). **Not** `requireSettingsSurface` / `canOpenSettings`.
- **Language**: English copy (host UI may translate via `messages`).

### Implementation notes (host)

- Account form: LiveComponent `MemberAlertPreferencesLive` (Symfony UX CSRF on Live endpoint; Symfony form `csrf_protection: false` for Live re-renders — see `config/packages/csrf.yaml`). Preference manager / repos injected via **constructor** (not LiveAction method injection).
- Per-project form: LiveComponent `MemberProjectAlertPreferencesLive` from Account modals and Project Settings (same constructor DI pattern).
- Classic POST `project_member_alerts_save` remains for non-Live submissions; same `requireAccess` gate.

### Account fields (POST / Live submit)

| Field | Type | Default shown |
|-------|------|----------------|
| `memberAlertsEnabled` | checkbox | checked |
| `events[issue.new].enabled` | checkbox | checked |
| `events[issue.new].scope` | `all` \| `involved` (UI may use `involved` boolean) | `all` |
| … same for regression, resolved, reopened, assigned, commented | | |
| `pushNotificationsEnabled` | checkbox | checked by default (browser permission still required) |

### Per-project fields

For the current user’s access to that project:

| Field | Type | Default |
|-------|------|---------|
| `enabled` | checkbox | checked |
| `events[…].enabled` / scope | optional overrides | inherit account |
| `resetOverrides` | checkbox | clears overrides |

Saving with no prior DB row and all defaults may omit writes (opt-out storage) or upsert only dirty fields — implementation choice; reads must treat missing as on.

**Project destinations** (Slack/email/… under Project Settings) are **project-owned** and independent of member prefs; dispatch crosses both layers when applicable.

## API (existing realtime bootstrap)

### `GET /account/realtime/config`

When Mercure instance-enabled **and** `memberAlertsEnabled` (default true):

```json
{
  "mercure": {
    "enabled": true,
    "hubUrl": "https://…/.well-known/mercure",
    "token": "<subscriber JWT>",
    "topics": ["/users/{userUuid}/member-alerts"]
  },
  "push": {
    "preferenceEnabled": false,
    "vapidPublicKey": "…",
    "configured": true
  }
}
```

When account master off: `mercure.enabled` false or empty `topics` / null token (no EventSource).

Web Push subscribe/unsubscribe endpoints unchanged; still require device opt-in + `X-CSRF-TOKEN` / `account_push`.
