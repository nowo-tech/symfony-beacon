# Plan: Monthly Event Quota (`032`)

**Branch**: `032-monthly-quota` | **Date**: 2026-07-31 | **Spec**: [spec.md](./spec.md)

## Summary

Mirror daily quota (`018`) with a nullable `event_quota_monthly` override and env `BEACON_EVENT_QUOTA_MONTHLY`. Enforce on Envelope HTTP + Messenger worker. Warn at 80% in Settings. Month boundary: UTC calendar month.

## Technical Context

| Area | Decision |
|------|----------|
| Storage | `project.event_quota_monthly` nullable int |
| Env | `BEACON_EVENT_QUOTA_MONTHLY` → `beacon.event_quota_monthly` (0 = unlimited) |
| Inherit | `null` inherits env; `0` = unlimited override |
| Count | `EventRepository::countReceivedSinceForProject` from UTC month start |
| UI | Project Settings governance (same form as daily) |
| Enforce | Daily and monthly both apply when configured |

## Constitution Check

| Gate | Status |
|------|--------|
| Spec-first | Pass |
| English docs/UI | Pass |
| Tests | Pass — worker drop + Settings warning/save |

## Implementation

1. Entity + migration + parameters + `.env.dist`
2. `ProjectGovernanceResolver` monthly helpers
3. EnvelopeController / ProcessEnvelopeHandler
4. Settings Twig + saveGovernance + approaching flash
5. i18n, UPGRADING, CHANGELOG, ROADMAP, docs/dev/DATABASE.md
6. PHPUnit
