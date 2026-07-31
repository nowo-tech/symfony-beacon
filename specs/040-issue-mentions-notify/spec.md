# Feature Specification: Issue Mentions and Assignee Notify

**Feature Branch**: `040-issue-mentions-notify`
**Created**: 2026-07-31
**Status**: Implemented

**Input**: Support `@user` mentions in issue comments and email (instance Mailer from `034`) on assign and mention.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Mention notify (P1)

As a member, when someone @mentions me, I get an email if Mailer is configured.

**Acceptance Scenarios**:

1. **Given a comment with @user, When saved, Then the mentioned user receives email (Mailer deliverable).**
2. **Given no Mailer DSN, When mentioned, Then no crash; notify is skipped or queued policy documented.**

### User Story 2 - Assignee notify (P1)

As a member, when assigned to an issue, I get email.

**Acceptance Scenarios**:

1. **Given assign action, When Mailer works, Then assignee is notified once per assign.**

## Requirements *(mandatory)*

- **FR-001**: Parse @mentions against project-accessible users only.
- **FR-002**: Email on mention and assign via encrypted instance Mailer.
- **FR-003**: Respect user notification preferences if already present; otherwise sane defaults.

## Success Criteria

- **SC-001**: Functional tests with Mailer mock/catcher.
- **SC-002**: Mentions cannot target users outside project access.

## Out of scope

- In-app real-time mention UX beyond email.
- Slack/Discord mention bridging.
