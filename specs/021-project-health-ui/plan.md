# Implementation Plan: Project Health UI

**Branch**: `021-project-health-ui`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Delivery status | `lastDeliveryAt`, `lastDeliverySuccess`, `lastDeliveryError` on `NotificationDestination` |
| Writer | `DeliverNotificationHandler` records success/failure before rethrow |
| Queue | Shared `MessengerQueueHealth` (also used by `/health/ready`) |
| UI | Health panel on Project Settings + Admin project show |

## Constitution Check

- Spec-first (`021`); English UI copy
- Observes Messenger; does not change worker topology
