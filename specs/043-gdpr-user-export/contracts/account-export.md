# Contract: Account data export

## Endpoint

| Who | Method | Path |
|-----|--------|------|
| Self | `GET` | `/account/privacy/export` |
| Admin | `GET` | `/admin/users/{uuid}/export` |

Response: `200` with `Content-Type: application/json; charset=UTF-8` and  
`Content-Disposition: attachment; filename="beacon-account-{uuid}.json"`.

## Schema (`beacon-account-export/v1`)

```json
{
  "schema": "beacon-account-export/v1",
  "exported_at": "ISO-8601",
  "account": {
    "uuid": "...",
    "email": "...",
    "display_name": "...",
    "roles": ["ROLE_ADMIN"],
    "enabled": true,
    "anonymized_at": null,
    "preferred_locale": "en",
    "preferred_theme": "light",
    "created_at": "...",
    "updated_at": "...",
    "last_activity_at": "...",
    "password_changed_at": "..."
  },
  "project_memberships": [
    { "project_uuid": "...", "project_name": "...", "role": "owner" }
  ],
  "group_memberships": [
    { "group_uuid": "...", "group_name": "..." }
  ],
  "security_activity": [
    { "action": "auth.magic_login_requested", "created_at": "...", "context": {} }
  ],
  "password_history": {
    "count": 2,
    "entries": [{ "changed_at": "..." }]
  },
  "social_accounts": [
    { "provider": "github", "linked_at": "...", "provider_email": "..." }
  ],
  "push_subscriptions_count": 0,
  "notes": {
    "events_retention": "Project ingest events and issues are not included; they remain project data until retention purge."
  }
}
```

**Must not include**: password hashes, API secrets, other users' emails, webhook URLs, Mailer DSN.

## Anonymize

| Who | Method | Path |
|-----|--------|------|
| Self | `POST` | `/account/privacy/anonymize` (CSRF) |
| Admin | `POST` | `/admin/users/{uuid}/anonymize` (CSRF) |

Effects: scrub email/display name, disable, random password, clear reset + password history, delete social + push, set `anonymized_at`, audit, self → logout.
