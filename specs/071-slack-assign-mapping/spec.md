# Feature Specification: Slack Assign-to-me + user mapping

**Feature Branch**: `071-slack-assign-mapping`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.23  
**Issue**: [#32](https://github.com/nowo-tech/symfony-beacon/issues/32)

**Input**: Let on-call engineers assign an issue to themselves from Slack, using a linked Slack user id on their Beacon account.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Assign to me from Slack (Priority: P1)

As a project member with triage, I click **Assign to me** on a Slack alert so the issue is assigned to my Beacon user.

**Acceptance Scenarios**:

1. **Given** my Account profile has my Slack user id and I have triage on the project, **When** I click Assign to me with a valid signature, **Then** I am the issue assignee.
2. **Given** my Slack id is not linked, **When** I click Assign to me, **Then** HTTP 403 and the assignee is unchanged.
3. **Given** an invalid signature, **When** I POST, **Then** HTTP 401 and nothing changes.

### User Story 2 - Link Slack id in profile (Priority: P1)

As a user, I store my Slack member ID on Account → Profile so interactive actions can map me.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Optional unique `slackUserId` on User; profile form; scrub on anonymize; include in GDPR export |
| FR-002 | Slack Block Kit **Assign to me** beside Resolve when signing secret set |
| FR-003 | Shared `IssueAssigneeChanger` for UI and Slack |
| FR-004 | Resolve attributes actor when mapped user has triage; otherwise null actor |
| FR-005 | Document in NOTIFICATIONS.md |

## Out of Scope

- Teams Assign / Teams user id mapping
- Assign-to-other / user picker
- OTLP metrics

## Privacy

Slack user ids are personal identifiers — document in Account profile help; scrub on anonymize; remind operators about Privacy policy when collecting them.
