# Feature Specification: GDPR User Export and Anonymize

**Feature Branch**: `043-gdpr-user-export`
**Created**: 2026-07-31
**Status**: Implemented (Unreleased)  

**Input**: Account data export + soft-delete / anonymize path. Prefer `nowo-tech` anonymize kit if available; English legal/privacy copy remains operator-customizable.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Export (P1)

As a user, I export my account-related data.

**Acceptance Scenarios**:

1. **Given authenticated account, When I request export, Then I receive a downloadable archive/JSON of personal fields + memberships metadata (no other users' PII).**

### User Story 2 - Anonymize (P1)

As admin/user per policy, account can be anonymized/soft-deleted.

**Acceptance Scenarios**:

1. **Given anonymize action, When completed, Then email/identifier scrubbed per documented rules; sessions invalidated.**

## Requirements *(mandatory)*

- **FR-001**: Self-service or admin-triggered export of account data.
- **FR-002**: Anonymize/soft-delete path; prefer nowo-tech anonymize kit.
- **FR-003**: Document retention vs ingest event payloads (events may remain project data).
- **FR-004**: Legal/privacy pages remain available for operator text.

## Success Criteria

- **SC-001**: Export contains no other users' secrets.
- **SC-002**: Anonymized user cannot log in with old credentials.

## Out of scope

- Full project event purge UI (separate).
- Legal advice for operators.
