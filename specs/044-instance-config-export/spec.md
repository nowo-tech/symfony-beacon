# Feature Specification: Instance Settings Export/Import

**Feature Branch**: `044-instance-config-export`
**Created**: 2026-07-31
**Status**: Draft  

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

## Success Criteria

- **SC-001**: Round-trip test without leaking DSN/OAuth secrets.
- **SC-002**: CSRF on import.

## Out of scope

- Full DB dump.
- SiteBackup media/DB — use SiteBackupBundle.
