# Quickstart: Notification Circuit Breaker

## Prerequisites

- `make up` + migrated DB
- Demo project with an HTTP destination pointing at a failing URL (or mock)

## Validate

1. Set `BEACON_NOTIFICATION_CIRCUIT_BREAKER_THRESHOLD=3` (optional; default 5).
2. Trigger deliveries that fail (or run PHPUnit).
3. After N consecutive failures, destination shows **Auto-paused** on Project Settings.
4. Further real notifications are not sent; **Send test** still queues.
5. Admin clicks **Resume** → circuit clears; next failure starts the counter at 1.

## Automated

```bash
composer test -- --filter NotificationCircuitBreaker
```
