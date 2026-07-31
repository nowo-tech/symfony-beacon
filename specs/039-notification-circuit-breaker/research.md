# Research: Notification Circuit Breaker

## Decision: Persist circuit on destination entity

**Decision**: Add `consecutive_failures` (int) and `circuit_opened_at` (nullable datetime) on `notification_destination`.

**Rationale**: Delivery already updates the destination on each attempt (`NotificationDeliveryHistoryRecorder`). Circuit state belongs with that row so Health UI and Messenger workers see the same truth without a new table.

**Alternatives considered**:
- Cache-only circuit → lost on worker restart / multi-node
- Count failures from `notification_delivery_attempt` on each send → extra queries and racey under concurrent deliveries

## Decision: Hard open until resume, optional cooldown

**Decision**: Default cooldown `0` = stay open until admin **Resume**. If `BEACON_NOTIFICATION_CIRCUIT_BREAKER_COOLDOWN_MINUTES` > 0, treat circuit as closed after that duration from `circuit_opened_at` (documented backoff; first success resets counter).

**Rationale**: Spec FR-003 requires admin resume; out-of-scope forbids undocumented auto-resume heuristics. Zero cooldown keeps operators in control; non-zero is an explicit documented backoff.

## Decision: Skip at dispatch and handler

**Decision**: `NotificationDispatcher` does not queue messages for open circuits; `DeliverNotificationHandler` also no-ops if circuit opened (race / queued before trip). Sample **test** sends still run (same exception as manual disable).

**Rationale**: Avoid Messenger backlog noise; keep send-test as operator verification path while paused.

## Decision: Threshold via env parameter

**Decision**: `BEACON_NOTIFICATION_CIRCUIT_BREAKER_THRESHOLD` (default `5`). No per-project override in this slice (can extend later via governance).

**Rationale**: Spec allows env and/or project override; env-only meets FR-001 with minimal UI/schema.
