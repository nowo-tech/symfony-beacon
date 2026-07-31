# Feature Specification: Restrict Nelmio API Doc to Admins

**Feature Branch**: `054-api-doc-admin-only`
**Created**: 2026-07-31
**Status**: Implemented (v0.13.0)

**Input**: Restrict Nelmio `/api/doc` (and UI) to `ROLE_ADMIN` so anonymous/member users cannot browse OpenAPI on public self-hosts.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin only (P1)

As an operator, only admins open `/api/doc`.

**Acceptance Scenarios**:

1. **Given anonymous or ROLE_USER, When GET /api/doc, Then 401/403.**
2. **Given ROLE_ADMIN, When GET /api/doc, Then 200.**

## Requirements *(mandatory)*

- **FR-001**: Security access_control or firewall for Nelmio doc routes → ROLE_ADMIN.
- **FR-002**: UPGRADING notes the change for operators who linked /api/doc publicly.
- **FR-003**: Functional test covers denial and admin allow.

## Success Criteria

- **SC-001**: Non-admin cannot load Swagger UI.
- **SC-002**: Ingest OpenAPI remains accurate for admins.

## Out of scope

- Public developer portal.
- Per-project OpenAPI.
