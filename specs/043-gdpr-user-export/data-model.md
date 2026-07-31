# Data Model: GDPR User Export and Anonymize

## User (`app_user`)

| Field | Type | Notes |
|-------|------|--------|
| `anonymized_at` | datetime immutable, nullable | Set once when anonymized; UI badge; block re-anonymize |

Existing: `email`, `display_name`, `enabled`, `password`, memberships, password history.

## Export document (ephemeral)

See [contracts/account-export.md](./contracts/account-export.md). Not persisted.

## Audit

| Action | When |
|--------|------|
| `user.account_exported` | Export downloaded (actor = requester; subject = account) |
| `user.anonymized` | Anonymize completed |

## Retention note (docs)

Ingest **events / issues** stay as project telemetry. Anonymize does not purge Envelope history (out of scope).
