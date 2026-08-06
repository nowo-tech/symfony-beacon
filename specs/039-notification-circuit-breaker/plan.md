# Implementation Plan: Notification Circuit Breaker

**Branch**: `039-notification-circuit-breaker` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/039-notification-circuit-breaker/spec.md`

## Summary

After N consecutive delivery failures, persist an open circuit on `NotificationDestination`, skip further outbound attempts until a project admin resumes (CSRF) or an optional documented cooldown elapses. Surface paused state in Project Settings Health + destinations list. Builds on delivery history (`030`).

## Technical Context

**Language/Version**: PHP 8.5  
**Primary Dependencies**: Symfony 8.1, Doctrine ORM 3, Messenger, Twig  
**Storage**: MySQL 9.7 — columns on `notification_destination` via MDK migration  
**Testing**: PHPUnit 13 (unit + functional `DatabaseWebTestCase`)  
**Target Platform**: Docker / FrankenPHP (classic + worker)  
**Project Type**: Modular Symfony web app (`src/Notifications`)  
**Performance Goals**: Circuit check is O(1) in-memory on loaded entity; no extra queries on hot ingest ACK path (dispatch already async)  
**Constraints**: Never log/render webhook secrets; English UI/docs; worker-safe (no static circuit state)  
**Scale/Scope**: Per-destination state; instance defaults via env

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status |
|-----------|--------|
| I Spec-first | Pass — spec + this plan + tasks |
| II Canonical stack | Pass — no new runtime |
| III Product mission | Pass — operator reliability for alerts |
| IV Docker-first | Pass — env in `.env.dist` only |
| V Classic ↔ worker | Pass — state in DB entity |
| VI Efficient ingest | Pass — circuit evaluated on Messenger delivery / dispatch, not Envelope ACK |
| VII English docs/UI | Pass |
| VIII Tests per feature | Pass — trip + resume + skip while open |

## Project Structure

### Documentation (this feature)

```text
specs/039-notification-circuit-breaker/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── circuit-breaker.md
└── tasks.md
```

### Source Code (repository root)

```text
src/Notifications/
├── Entity/NotificationDestination.php          # consecutiveFailures, circuitOpenedAt
├── Service/NotificationDeliveryHistoryRecorder.php
├── Service/NotificationCircuitBreaker.php      # threshold/cooldown helpers
├── MessageHandler/DeliverNotificationHandler.php
├── Service/NotificationDispatcher.php          # skip open circuits when queueing
├── Controller/ProjectNotificationController.php # resume action
templates/project/settings.html.twig
migrations/Version20260731120000.php
config/parameters.yaml
.env.dist
tests/Functional/Notifications/NotificationCircuitBreakerTest.php
docs/product/NOTIFICATIONS.md
docs/CHANGELOG.md
docs/UPGRADING.md
```

## Complexity Tracking

| Violation | Why Needed | Simpler alternative rejected because |
|-----------|------------|-------------------------------------|
| — | — | — |
