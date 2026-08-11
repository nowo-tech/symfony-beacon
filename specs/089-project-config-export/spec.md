# Feature Specification: Project config export / import

**Feature Branch**: `089-project-config-export`  
**Created**: 2026-08-11  
**Status**: Implemented (shipped in **v1.6.0**, 2026-08-11)  
**Roadmap**: Phase 6.38  

**Input**: Export and import one or many projects as JSON, preserving memberships and roles; dual surfaces (Administration vs Project Settings) with different user-creation rules; stable project `code` for idempotency; ability to deactivate membership without delete.

## Summary

Operators move project **metadata + direct memberships** between Beacon instances (or rebuild a project) via a versioned JSON bundle. Matching is by unique **`project.code`**. Memberships are keyed by **email** with `role` and `active`. **API key secrets are never exported.** Administration may create missing users; the project Settings panel must not.

Sibling pattern: instance non-secret portability (`044`). Issues/events CSV|JSON export remains `017` / `ProjectExportController` (orthogonal).

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| R1 | `Project.code` | Unique slug-like code; migrate backfill from `slug`; factory sets from slug |
| R2 | Bundle schema | `beacon-project-bundle` v1: projects[] with code/uuid/slug/name/description/governance + memberships[] |
| R3 | Admin surface | `ROLE_ADMIN`: export all / one; import upserts by code; **creates** missing users (disabled + random password) |
| R4 | Panel surface | `project.settings.manage`: export this project; import only when code matches; **skip** unknown emails |
| R5 | Membership active | `ProjectMembership.active` (default true); deactivate/reactivate via `project.members.manage`; inactive grants no access |
| R6 | Audit | `project.config_exported` / `project.config_imported`; member activated/deactivated actions |
| Docs | Specs / ROLES / CHANGELOG | Amend `002` / `018` / `019` / `044`; Unreleased CHANGELOG |

## Non-goals (v1)

- Export/import of API keys (public or secret), read tokens, share links
- Group links (`ProjectGroupAccess`) / UserGroup creation
- Telemetry (issues, events, performance, analytics)
- Notification destinations / threshold rules / webhooks
- Full DB / SiteBackup (use SiteBackupBundle)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin export / import fleet (Priority: P1)

As an instance admin, I download all (or one) projects as JSON and restore them on another instance, recreating missing users by email.

**Independent Test**: Export → wipe/empty target → import → projects exist by `code`; memberships match; new users exist and are disabled.

**Acceptance Scenarios**:

1. **Given** `ROLE_ADMIN` and projects with members, **When** I export all, **Then** JSON schema is `beacon-project-bundle` v1, includes memberships by email, and omits API secrets.
2. **Given** a valid bundle with a new `code` and unknown emails, **When** I import in Administration, **Then** the project is created/updated and missing users are created **disabled**.
3. **Given** a bundle for an existing `code`, **When** I import again, **Then** the project upserts idempotently (no duplicate `code`).
4. **Given** I am not `ROLE_ADMIN`, **When** I hit admin export/import routes, **Then** access is denied.

### User Story 2 - Panel export / import this project (Priority: P1)

As a project admin/owner with Settings manage, I export this project and re-import membership/governance without creating accounts.

**Independent Test**: Export from Settings; change name; import same file; name restored; unknown email in file is skipped with warning.

**Acceptance Scenarios**:

1. **Given** `project.settings.manage`, **When** I export, **Then** I download a single-project bundle for this `code`.
2. **Given** a bundle whose `code` matches the opened project, **When** I import, **Then** governance fields and known-email memberships update; unknown emails are skipped (no user create).
3. **Given** a bundle with a different `code`, **When** I import on this project, **Then** import fails with a clear error (`code_mismatch` / not in bundle).
4. **Given** viewer/member without Settings manage, **When** I request export/import, **Then** **403** / UI hidden (`002` FR-013 / FR-014).

### User Story 3 - Deactivate membership access (Priority: P2)

As a project admin with members manage, I disable a member’s access without removing the membership row.

**Independent Test**: Deactivate member; assert product URLs 403 / project not listed; reactivate; access restored. Cannot deactivate last active owner.

**Acceptance Scenarios**:

1. **Given** an active non-owner member, **When** I deactivate, **Then** they lose product access; row remains with `active=false`.
2. **Given** the sole active owner, **When** I try to deactivate, **Then** the action is rejected (`last_owner`).
3. **Given** `project.members.manage`, **When** I reactivate, **Then** access returns.

## Requirements *(mandatory)*

- **FR-001**: Each `Project` MUST have a unique `code` (slug-like). Migration backfills from `slug`. `ProjectFactory` MUST set `code` from slug when empty.
- **FR-002**: Export schema MUST be `beacon-project-bundle` version **1** with `projects[]` containing at least: `code`, `uuid`, `slug`, `name`, `description`, governance (`ingest_enabled`, retention/quota/rate fields), `memberships[]` (`email`, `display_name`, `role`, `active`). MUST NOT include API keys/secrets, group links, or telemetry.
- **FR-003**: Import MUST upsert by `code`. Administration (`ROLE_ADMIN`) MAY create projects and users (email identity; new users disabled). Panel import MUST only update the opened project when codes match and MUST NOT create users.
- **FR-004**: Panel export/import MUST require `project.settings.manage`. Membership deactivate/reactivate MUST require `project.members.manage`. CSRF on import POSTs.
- **FR-005**: `ProjectMembership.active` defaults to true. `ProjectAccessService` MUST ignore inactive direct memberships for grants. Active-owner counts (last-owner / transfer guards) MUST count only active owners. Dashboard accessible-project queries MUST exclude inactive direct memberships.
- **FR-006**: Audit UserActions: `project.config_exported`, `project.config_imported`, `project.member_activated`, `project.member_deactivated`.
- **FR-007**: Panel import MUST NOT promote users to `owner`/`full` via import alone when that would bypass Transfer / role UI (warn and clamp to `admin` when appropriate).
- **FR-008**: Dual gating for Settings config panel follows `002` FR-013 / FR-014 (`canManageSettings` + controller permission).

## Success Criteria

- **SC-001**: Admin round-trip recreates projects by `code` and users by email (`ProjectConfigPortability` + functional coverage as available).
- **SC-002**: Panel import skips unknown emails and rejects code mismatch (`ProjectConfigPortabilityTest`).
- **SC-003**: Inactive membership does not grant product access; last active owner cannot be deactivated.
- **SC-004**: Export JSON never contains API key secrets.

## Key Entities

- **Project**: `code` (unique portability key), plus existing uuid/slug/governance.
- **ProjectMembership**: `active` flag; role enum including `full`.
- **Bundle**: `beacon-project-bundle` v1 JSON document.

## Assumptions

- Email is the stable cross-instance user identity for memberships.
- Disabled users created by admin import are enabled later by operators (password reset / admin UI).
- Group access remains out of band for v1.

## Cross-links

- `002-identity-project` — FR-013/FR-014 settings + members surfaces  
- `018-project-governance` — governance fields in bundle  
- `019-admin-projects-ops` — admin project list export/import UI  
- `044-instance-config-export` — instance-level sibling pattern  
- `088-project-full-role` — roles in membership payload  
- `docs/product/ROLES.md`
