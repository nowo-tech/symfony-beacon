# Feature Specification: Teams Assign to me via OpenUri

**Feature Branch**: `073-teams-assign-openuri`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.25  
**Issue**: [#36](https://github.com/nowo-tech/symfony-beacon/issues/36)

**Input**: Let on-call engineers assign an issue to themselves from a Teams alert using Beacon session identity (OpenUri), because MessageCard HttpPOST cannot identify the clicker.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Assign to me from Teams (Priority: P1)

As a project member with triage, I click **Assign to me** on a Teams alert, sign in to Beacon if needed, and become the assignee.

**Acceptance Scenarios**:

1. **Given** I am logged in with triage and the HMAC assign token is valid, **When** I GET `/hooks/teams/assign-me`, **Then** I am the issue assignee and redirect to the issue.
2. **Given** I am anonymous, **When** I open the Assign OpenUri, **Then** I am redirected to login (then can return to assign).
3. **Given** an invalid/expired token, **When** I GET assign-me while logged in, **Then** HTTP 403 and assignee unchanged.
4. **Given** I am a Viewer (no triage), **When** I open a valid assign link, **Then** assignee unchanged and an error flash.

### User Story 2 - Card includes Assign when secret set (Priority: P1)

As an operator, Teams MessageCards for `issue.new` / `regression` / `reopened` include **Assign to me** OpenUri whenever a signing secret is configured (same gate as Resolve).

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | `InteractionActionToken` supports `a: assign` (and keeps resolve wrappers) |
| FR-002 | MessageCard **Assign to me** OpenUri → `hooks_teams_assign_me` with signed query |
| FR-003 | `GET /hooks/teams/assign-me` requires `ROLE_USER`, validates HMAC, triage, `IssueAssigneeChanger::assign(..., 'teams')` |
| FR-004 | Document in NOTIFICATIONS.md |

## Out of Scope

- Adaptive Cards / `teamsUserId` mapping
- Changing Slack Assign or Teams Resolve HttpPOST

## Assumptions

- OpenUri runs in a browser context where the Beacon cookie/session can authenticate the user.
