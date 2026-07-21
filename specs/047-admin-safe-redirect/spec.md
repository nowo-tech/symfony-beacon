# Feature Specification: Safe internal redirects

**Feature Branch**: `047-admin-safe-redirect`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: Admin view-as-member and account locale redirects must reject open redirects (`//host`, off-site URLs).

## User Scenarios & Testing

### User Story 1 - Same-origin relative redirects only (Priority: P1)

**Acceptance Scenarios**:

1. **Given** `redirect=//evil.example`, **When** view-as or locale POST completes, **Then** the app falls back to a safe default path.
2. **Given** `redirect=/projects/...` (same-origin relative), **When** the action completes, **Then** the user is sent to that path.

## Requirements

- Central helper `SafeInternalRedirect` (or equivalent) for privileged redirect targets.
