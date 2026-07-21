# Implementation Plan: Delivery History

**Branch**: `030-delivery-history`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Persist a bounded rolling window of recent delivery attempts per `NotificationDestination`, keep the existing last-delivery summary fields synchronized, and expose the history in Project Settings so admins can inspect recent successes and failures without unbounded storage growth.

## Technical Context

| Area | Decision |
|------|----------|
| Storage | New `notification_delivery_attempt` table linked to `notification_destination` with timestamp, success flag, and optional truncated error snippet |
| Writer | `NotificationDeliveryHistoryRecorder` is the single write path used by `DeliverNotificationHandler` |
| Retention | Per-destination rolling limit with env-backed default `BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT=20` |
| Summary fields | Existing `lastDeliveryAt`, `lastDeliverySuccess`, `lastDeliveryError` remain and are updated from the same recorder |
| UI | Project Settings Health panel shows expandable recent attempts under each destination |
| Tests | Functional/UI smoke in `ProjectHealthUiTest` plus persistence/prune coverage in `NotificationDeliveryHistoryRecorderTest` |

## Constitution Check

- Spec-first implementation for `030`
- English docs, PHPDoc, and code comments
- Bounded storage; no payload archival or external log system
- No new cookies, tracking, or public legal surface changes

## Project Structure

```text
src/Notifications/Entity/NotificationDeliveryAttempt.php
src/Notifications/Entity/NotificationDestination.php
src/Notifications/Repository/NotificationDeliveryAttemptRepository.php
src/Notifications/Service/NotificationDeliveryHistoryRecorder.php
src/Notifications/MessageHandler/DeliverNotificationHandler.php
templates/project/settings.html.twig
translations/messages.en.yaml
translations/messages.es.yaml
tests/Notifications/NotificationDeliveryHistoryRecorderTest.php
tests/Notifications/ProjectHealthUiTest.php
migrations/Version20260721192000.php
```
