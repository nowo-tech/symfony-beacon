# Feature Specification: Read API and Project Tokens

**Feature Branch**: `042-read-api-tokens`
**Created**: 2026-07-31
**Status**: Implemented  

**Input**: Authenticated JSON read API for issues list/detail/export for automation (not public boards). Ship after hardening `045`–`048`. Prefer project tokens distinct from ingest secrets.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Token read (P1)

As an integrator, I list/get issues with a project read token.

**Acceptance Scenarios**:

1. **Given a valid read token, When I GET issues, Then JSON returns membership-scoped data.**
2. **Given invalid/missing token, When I call the API, Then 401/403.**

## Requirements *(mandatory)*

- **FR-001**: Project-scoped read tokens (create/revoke in Settings; hashed at rest). Create/revoke MUST require `project.settings.manage`; Settings UI section gated with `canManageSettings`.
- **FR-002**: Endpoints for issues list/detail; export may reuse `017` auth model.
- **FR-003**: No public unauthenticated boards; rate-limit documented (reverse proxy; OpenAPI tag).
- **FR-004**: Tokens must not equal ingest public/secret key material.

## Success Criteria

- **SC-001**: OpenAPI/Nelmio documents read routes (`BeaconReadToken`).
- **SC-002**: Tests cover authz denials (`ProjectReadApiFunctionalTest`).

## Out of scope

- Write/mutate API.
- Public status pages.
