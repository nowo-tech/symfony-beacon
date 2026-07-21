# Implementation Plan: Threshold Alerts

**Branch**: `027-threshold-alerts`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Summary

Add per-project rolling error volume rules so owners and admins can alert on sudden spikes such as "50 error/fatal events in 15 minutes", with optional environment and release filters, cooldown suppression, and delivery through the existing notification pipeline.

## Technical Context

| Area | Decision |
|------|----------|
| Entity | `App\Notifications\Entity\ProjectThresholdRule` with project relation, public UUID, rolling thresholds, optional filters, and `lastFiredAt` cooldown marker |
| Count source | `EventRepository::countReceivedSince()` joins `event` to `issue` and counts only `error` / `fatal` issue levels |
| Trigger point | `ProcessEnvelopeHandler` calls `VolumeThresholdEvaluator` only after a persisted `error` or `fatal` event |
| Delivery | New category `volume.threshold` routed by `NotificationDispatcher`, so Messenger, quiet hours, digests, and destination health continue to apply |
| Cooldown | Rule-level `lastFiredAt` + `cooldownMinutes` suppress repeated alerts while volume stays high |
| UI | Project Settings adds Threshold alerts CRUD (owner/admin only) with create, edit, enable/disable, and delete actions |
| Suspended ingest | Evaluator exits early when project ingest is disabled; normal ingest is already blocked earlier in the request path |
| Tests | Functional coverage for first fire and cooldown suppression; settings CRUD access test |

## Constitution Check

- Spec-first implementation for `027`
- English docs and PHPDoc
- Reuse existing notification pipeline instead of adding a separate delivery path
- No new cookies, tracking, or public legal surface changes

## Project Structure

```text
src/Notifications/Entity/ProjectThresholdRule.php
src/Notifications/Repository/ProjectThresholdRuleRepository.php
src/Notifications/Form/ProjectThresholdRuleType.php
src/Notifications/Controller/ProjectThresholdRuleController.php
src/Notifications/Service/VolumeThresholdEvaluator.php
src/Notifications/Service/NotificationDispatcher.php
src/Notifications/Service/NotificationPayloadBuilder.php
src/Notifications/NotificationCategories.php
src/Ingest/MessageHandler/ProcessEnvelopeHandler.php
src/Issues/Repository/EventRepository.php
templates/project/settings.html.twig
tests/Notifications/ThresholdAlertTest.php
migrations/Version20260721190000.php
docs/NOTIFICATIONS.md
```
