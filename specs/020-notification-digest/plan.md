# Implementation Plan: Notification Digest and Quiet Hours

**Branch**: `020-notification-digest`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Settings | Columns on `NotificationDestination`: `quietHoursEnabled`, `quietHoursTimezone`, `quietHoursStart`, `quietHoursEnd`, `digestEnabled` |
| Buffer | Table `notification_digest_buffer` (destination FK + JSON payload + createdAt) |
| Dispatch | `QuietHoursEvaluator` + `NotificationDispatcher` buffers during the window (send-test bypasses) |
| Flush | `app:notifications:flush-digests` → `NotificationDigestFlusher` (digest summary or individual) |
| Out of scope | Native PagerDuty |

## Constitution Check

- Spec-first (`020`); English docs/PHPDoc/UI keys
- Extends `009-project-notifications` Messenger delivery

## Legal note

No new cookies or tracking. Operators still need privacy/terms for hosted Beacon if notifying personal emails.
