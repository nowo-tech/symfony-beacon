# Feature Specification: Analytics

**Feature Branch**: `005-analytics`  
**Created**: 2026-07-19  
**Status**: Completed (as-built through v0.6.0)  

## Summary

Per-project daily counters (`DailyProjectStat`) track errors, transactions, and N+1 group hits. Operators view them at `/projects/{id}/analytics`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View project analytics (Priority: P1)

As a project member, I open Analytics and see recent daily stats.

**Acceptance Scenarios**:

1. **Given** ingested errors/transactions, **When** I open `/projects/{id}/analytics`, **Then** daily rows show error / transaction / N+1 counts for recent days.
2. **Given** no telemetry yet, **When** I open Analytics, **Then** the page loads with empty or zero series without error.

### User Story 2 - Counters updated by ingest (Priority: P1)

As ingest, each processed event/transaction updates the day’s `DailyProjectStat`.

**Acceptance Scenarios**:

1. **Given** an event item processed, **When** stats update, **Then** error count for that UTC date increments.
2. **Given** a transaction with N+1 groups, **When** processed, **Then** the N+1 counter increments.

## Requirements *(mandatory)*

- **FR-001**: Persist `DailyProjectStat` per project per day.
- **FR-002**: Expose `/projects/{id}/analytics` for members.
- **FR-003**: Counters include at least errors, transactions, and N+1 detections.

## Success Criteria

- **SC-001**: Analytics page reflects ingest activity for a seeded project in tests or manual verify.
