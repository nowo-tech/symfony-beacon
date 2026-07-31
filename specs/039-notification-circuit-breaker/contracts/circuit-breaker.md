# Contract: Circuit breaker behaviour

## Env

| Variable | Default | Meaning |
|----------|---------|---------|
| `BEACON_NOTIFICATION_CIRCUIT_BREAKER_THRESHOLD` | `5` | Consecutive failures before open |
| `BEACON_NOTIFICATION_CIRCUIT_BREAKER_COOLDOWN_MINUTES` | `0` | `0` = pause until resume; `>0` = allow retries after N minutes |

## HTTP (project admin)

`POST /projects/{projectUuid}/notifications/{destinationUuid}/resume`

- CSRF: `notif_resume_{destinationId}`
- Role: project `admin` (same as toggle)
- Effect: `consecutiveFailures = 0`, `circuitOpenedAt = null`
- Redirect: `project_settings` with flash

## Delivery semantics

| Condition | Non-test delivery | Send-test |
|-----------|-------------------|-----------|
| `enabled = false` | skip | allowed |
| circuit open (and cooldown not elapsed) | skip | allowed |
| else | deliver | deliver |

## Secrets

Error snippets and UI must never include raw `endpointUrl` / tokens (existing masking + recorder truncation apply).
