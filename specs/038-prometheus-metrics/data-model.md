# Data model: 038 metrics

No new Doctrine entities.

## Cache keys

| Key | Value |
|-----|--------|
| `beacon.metrics.ingest_ack` | int counter |
| `beacon.metrics.ingest_reject.{reason}` | int counter (`unauthorized`, `forbidden`, `quota`, `rate_limit`, `invalid`, `other`) |

## Labels

- `reason` on reject counter only — closed enum, no free-form client input.
- No project id / DSN / URLs in labels.
