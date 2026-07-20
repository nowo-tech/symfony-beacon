# Implementation Plan: Project Notifications

**Branch**: `009-project-notifications`  
**Spec**: [spec.md](./spec.md)  
**Status**: In progress (Phase 1 of [docs/ROADMAP.md](../../docs/ROADMAP.md))

## Technical Context

| Area | Decision |
|------|----------|
| Module | `src/Notifications/` |
| Storage | `notification_destination` table (project FK, cascade delete) |
| Types | `slack` \| `http` enums |
| Categories | JSON list: issue levels + `n_plus_one` |
| Dispatch | After issue create/regression or N+1 tx in `ProcessEnvelopeHandler` → `MessageBus` |
| Delivery | `DeliverNotificationMessage` on `async` transport (retries via Messenger) |
| HTTP | `symfony/http-client` already required |
| UI | Project Settings section + CRUD routes (owner/admin) |
| Regression | Reopen both `resolved` and `ignored` → `unresolved` on matching event |

## Constitution Check

- Spec-first: yes (`009`)
- Fast ACK: outbound only via Messenger after ingest processing (still not on EnvelopeController)
- English docs/PHPDoc/UI
- PHPUnit required

## Out of scope (v1)

Email, Discord-native, Slack OAuth, digests, per-user prefs.
