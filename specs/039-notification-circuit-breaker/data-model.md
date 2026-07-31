# Data Model: Notification Circuit Breaker

## NotificationDestination (extended)

| Field | Type | Notes |
|-------|------|-------|
| `consecutiveFailures` | int, default 0 | Incremented on delivery failure; reset to 0 on success or admin resume |
| `circuitOpenedAt` | `?DateTimeImmutable` | Non-null when circuit is open (threshold reached) |

### State transitions

```text
closed (circuitOpenedAt = null)
  --[failure, consecutiveFailures >= threshold]--> open
open
  --[admin resume]--> closed (failures = 0)
  --[success]--> closed (failures = 0)
  --[cooldown elapsed, cooldownMinutes > 0]--> effectively closed for next attempt
    (circuitOpenedAt cleared on next success or resume; optional clear on cooldown check)
```

### Related (unchanged)

- `NotificationDeliveryAttempt` — history of individual attempts (`030`)
- `enabled` — manual disable; independent of circuit (both block non-test delivery)
