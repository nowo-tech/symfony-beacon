# Feature Specification: Project role `full` + InstanceRole delete guards

**Feature Branch**: `088-project-full-role`  
**Created**: 2026-08-10  
**Status**: Implemented (shipped in **v1.5.0**, 2026-08-10)  
**Roadmap**: Phase 6.37  

**Input**: Add membership role `full` (same `project.*` matrix as owner, without primary ownership), demote transfer actors to `full`, harden InstanceRole delete when users are assigned, and document/cut the release.

## Summary

Operators need a **non-primary** membership that keeps the full project permission matrix (including `project.delete`) after ownership transfer, distinct from `admin`. Administration must not delete InstanceRoles that still have users assigned. Groups still cannot receive `owner` or `full`.

## Scope (as-built)

| ID | Area | Delivered |
|----|------|-----------|
| R1 | `ProjectRole::Full` | Same `ProjectPermission` matrix as owner; `rank()` = owner; `isPrimaryOwner()` = false |
| R2 | Transfer | `transferOwnership` demotes actor to `full`; UI + `requirePrimaryOwner()` exact `Owner` |
| R3 | Membership guards | Cannot remove `full` without demoting first; admins cannot mutate owner/full; groups reject full |
| R4 | Instance mirror | Seeded `ROLE_PROJECT_FULL` (`InstanceRoleCatalog`) |
| R5 | Admin Roles UI | Block delete when users assigned (`flash.roles.in_use`); hide delete control |
| Docs | Specs / ROLES / CHANGELOG | Amend `011`, `002`, `docs/product/ROLES.md`; cut **v1.5.0** |

## Non-goals

- Custom per-project roles beyond the enum + instance mirror
- Changing instance `ROLE_ADMIN` effective-owner shortcut
- Multi-primary-owner product model (transfer still creates one owner + one full)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Transfer leaves former owner as full (Priority: P1)

As a project owner, I hand ownership to another member and keep full project capabilities without remaining the primary owner.

**Independent Test**: Transfer ownership; assert target=`owner`, former=`full`; former cannot open transfer UI / POST transfer returns 403.

### User Story 2 - Full member cannot be removed cold (Priority: P2)

As an owner, I must demote a `full` member before removing them.

**Independent Test**: POST remove on a `full` membership → flash `member_cannot_remove_full`; membership unchanged.

### User Story 3 - Admin cannot delete roles in use (Priority: P2)

As an instance admin, I cannot delete an InstanceRole assigned to users.

**Independent Test**: Assign user to custom role; delete POST → flash `in_use`; role remains.

## Requirements *(mandatory)*

- **FR-001**: `ProjectRole::Full = 'full'` with `ProjectPermission::forRole(Full) === forRole(Owner)`.
- **FR-002**: Primary-ownership gates (transfer, last-owner counting) use exact `Owner`, not rank.
- **FR-003**: `transferOwnership` demotes the acting owner to `full` (audit `actor_new_role: full`).
- **FR-004**: `remove` of a `full` membership fails until role change; groups cannot be assigned `owner` or `full`.
- **FR-005**: Seed / upsert `ROLE_PROJECT_FULL`; Admin cannot delete system roles or roles with assigned users.
- **FR-006**: UI labels + flashes in EN (+ locale catalogs) for `full`, `flash.roles.in_use`, `flash.project.member_cannot_remove_full`.

## Success Criteria

- **SC-001**: Transfer demotes to `full`; Full cannot transfer; Full can delete project.
- **SC-002**: Admin role delete in-use blocked; Twig hides delete when users assigned.
- **SC-003**: Covered by unit + `ProjectMembersTest` / `AdminInstanceRbacTest` (or equivalent).
