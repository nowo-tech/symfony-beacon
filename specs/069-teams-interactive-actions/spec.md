# Feature Specification: Teams interactive Resolve

**Feature Branch**: `069-teams-interactive-actions`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.21  
**Issue**: [#26](https://github.com/nowo-tech/symfony-beacon/issues/26)

**Input**: Allow operators to resolve an issue from a Microsoft Teams MessageCard via an **HttpPOST Resolve** action, verified with an HMAC token.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Resolve from Teams (Priority: P1)

As an on-call engineer, I click **Resolve** on a Teams issue alert so the Beacon issue becomes resolved without opening the UI.

**Independent Test**: With a Teams destination that has a signing secret, POST a signed JSON token to `/hooks/teams/actions`; the issue status is `resolved`.

**Acceptance Scenarios**:

1. **Given** a valid HMAC token for the destination signing secret, **When** I POST JSON claims to `/hooks/teams/actions`, **Then** HTTP 200 and the issue is resolved.
2. **Given** a tampered or expired token, **When** I POST, **Then** HTTP 401 and the issue is unchanged.
3. **Given** a Teams destination without signing secret, **When** outbound builds a MessageCard for `issue.new`, **Then** no Resolve HttpPOST is attached (OpenUri may still appear).

### User Story 2 - Operator configures interaction secret (Priority: P1)

As a project owner, I store a signing secret on the Teams destination (same encrypted field as Slack) so Resolve actions can be verified.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Reuse encrypted `signingSecret` on Teams destinations |
| FR-002 | MessageCard includes HttpPOST Resolve when secret set + issue/project UUIDs on new/regression/reopened (not samples) |
| FR-003 | `POST /hooks/teams/actions` public; verify HMAC token + expiry (default 7 days) |
| FR-004 | Shared `IssueStatusChanger` with `via: teams` |
| FR-005 | Absolute action URL from router (`DEFAULT_URI`); document in NOTIFICATIONS.md |

## Out of Scope (this spec)

- Assign / Ignore buttons (Assign OpenUri shipped in `073-teams-assign-openuri`)
- Slack→member mapping (`071`)
- Adaptive Cards / Workflows-only tenants without MessageCard HttpPOST
- Teams→member mapping (ROADMAP **Later**)

## As-built follow-ups

- Teams Assign to me via OpenUri: **`073-teams-assign-openuri`** (Phase 6.25 Done).

## Assumptions

- Classic Office 365 Incoming Webhooks / MessageCard `HttpPOST` remain available on the tenant, or an equivalent connector can POST the token body to Beacon.
