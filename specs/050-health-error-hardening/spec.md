# Feature Specification: Health ready error hardening

**Feature Branch**: `050-health-error-hardening`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: `/health/ready` must not echo exception messages to clients.

## User Scenarios & Testing

### User Story 1 - Generic failure body (Priority: P1)

**Acceptance Scenarios**:

1. **Given** readiness fails, **When** a client GETs `/health/ready`, **Then** the body reports a generic `error: unavailable` (detail stays in logs).

## Amendment (Redis, 2026-09-18)

Outside `APP_ENV=test`, a failed Redis ping is the same generic body. `checks` then includes `redis: false` and does not include the exception text. In `test`, the probe is omitted so PHPUnit (no Redis service) still sees `checks.database` only.
