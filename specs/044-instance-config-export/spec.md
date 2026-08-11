# Feature Specification: Instance Settings Export/Import

**Feature Branch**: `044-instance-config-export`
**Created**: 2026-07-31
**Status**: Implemented  

**Input**: Export/import instance settings (appearance, mailer metadata flags, non-secret config JSON) for backup drills. Secrets (Mailer DSN, OAuth, encrypt key) MUST NOT be included in cleartext exports.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Export (P1)

As an instance admin, I download non-secret instance config.

**Acceptance Scenarios**:

1. **Given ROLE_ADMIN, When I export, Then JSON omits secrets and includes documented allowlisted keys.**

### User Story 2 - Import (P2)

As an instance admin, I restore allowlisted settings from a file.

**Acceptance Scenarios**:

1. **Given a valid export, When I import, Then allowlisted fields update; secrets unchanged unless a separate documented flow.**

## Requirements *(mandatory)*

- **FR-001**: Admin-only export of allowlisted non-secret settings.
- **FR-002**: Import validates schema/version; rejects unknown/secret keys.
- **FR-003**: Audit log (UserAction) for import/export.

## Amendment (`087-security-audit-hardening`, 2026-08-10)

- **FR-004**: Import MUST NOT weaken security-sensitive booleans:
  - `ingest_reject_query_auth` (cannot turn off if currently on)
  - `metrics_require_token` (cannot turn off if currently on)
  - `notifications_allow_private_urls` (cannot turn on if currently off)
  - `hooks_allow_anonymous_resolve` (cannot turn on if currently off)
- Import MAY tighten those flags and MAY apply other allowlisted non-secret fields (retention, quotas, appearance, …).
- Covered by `InstanceConfigPortabilitySecurityFlagsTest`.

## Success Criteria

- **SC-001**: Round-trip test without leaking DSN/OAuth secrets (`InstanceConfigPortabilityTest`).
- **SC-002**: CSRF on import.
- **SC-003**: Weaken-attempt import leaves secure flags unchanged (`087`).

## Out of scope

- Full DB dump.
- SiteBackup media/DB — use SiteBackupBundle.
- Per-project metadata/memberships portability — see sibling `089-project-config-export` (`beacon-project-bundle`).

## Amendment (import size, 2026-08-11)

- Instance config JSON upload MUST be ≤ **2 MiB** (`JsonUploadReader`), same cap as project config import (`089`).
