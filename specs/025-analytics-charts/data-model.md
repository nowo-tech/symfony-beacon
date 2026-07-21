# Data Model: Analytics Charts

## AnalyticsDayPoint (DTO, not persisted)

| Field | Type | Notes |
|-------|------|-------|
| `date` | `DateTimeImmutable` (date-only UTC) | Chart label `Y-m-d` |
| `errorCount` | int | Always set |
| `transactionCount` | int\|null | null when filtered |
| `nPlusOneCount` | int\|null | null when filtered |

## Analytics view state (URL)

| Param | Values |
|-------|--------|
| `period` | `7`, `14`, `30`, `90`, `custom` (default `30`) |
| `from` / `to` | `Y-m-d` when `period=custom` |
| `environment` | optional string |
| `release` | optional string (matches `event.release_version`) |
| `level` | optional string (matches `issue.level`) |
| `page` / `per_page` | table pagination |

## Existing entities

- `DailyProjectStat` — unchanged schema
- `Event` — source for filtered error buckets
- `Issue` — `level` for filtered path
