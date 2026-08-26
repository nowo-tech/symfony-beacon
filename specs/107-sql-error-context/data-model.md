# Data model: Query facts

## QueryFacts (derived, not persisted)

Computed by `QueryFactsExtractor` from one event payload.

| Field | Type | Notes |
|-------|------|--------|
| sqlstate | ?string | 5-char SQLSTATE when found |
| vendorCode | ?string | Driver numeric/string code (`1055`, `1040`) |
| driver | ?string | Best-effort (`pdo`, `pdo_mysql`, `mysql`, …) |
| sqlMode | ?string | From `sql_mode=` fragment |
| sql | ?string | Display SQL, truncated at 8192 chars |
| sqlTruncated | bool | True when original was longer |
| bindings | mixed | List/map from payload only |
| source | string | `structured` \| `breadcrumb` \| `exception_message` |
| summary | ?string | Hero subtitle: `SQLSTATE[…]` + vendor message without SQL dump |

Empty when none of sqlstate, vendorCode, sql are set.

## Event.payload (existing)

No schema change. Readers:

1. `contexts.db` (object)
2. `extra.sql` / `extra.query` / `extra.bindings`
3. `breadcrumbs.values[]` with category `query` / `db` / `db.query` / `sql.query` — last match
4. `exception.values[].value` and `message`

## Issue.culprit (persisted)

| Column | Before | After |
|--------|--------|--------|
| `issue.culprit` | VARCHAR(40) NOT NULL | VARCHAR(255) NOT NULL |

PHP `Issue::CULPRIT_MAX_LENGTH = 255`. No backfill of old clipped rows.

## Envelope `contexts.db` (Bundle 1.8.0)

| Key | Required | Notes |
|-----|----------|--------|
| type | yes | `"sql"` |
| sqlstate | no | |
| code | no | vendor code |
| driver | no | |
| sql | no | scrubbed literals, max 8192 |
| sql_mode | no | |
| bindings | no | only if already on the exception/query object |

Omit empty keys. Do not send `contexts.db` when nothing was extracted.
