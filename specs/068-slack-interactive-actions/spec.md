# Feature Specification: Slack interactive Resolve

**Feature Branch**: `068-slack-interactive-actions`  
**Created**: 2026-07-31  
**Status**: Implemented  
**Roadmap**: Phase 6.20  
**Issue**: [#24](https://github.com/nowo-tech/symfony-beacon/issues/24)

**Input**: Allow operators to resolve an issue from a Slack alert via a Block Kit **Resolve** button, with HMAC verification of Slack callbacks.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Resolve from Slack (Priority: P1)

As an on-call engineer, I click **Resolve** on a Slack issue alert so the Beacon issue becomes resolved without opening the UI.

**Independent Test**: With a Slack destination that has a signing secret, POST a signed interaction payload for Resolve; the issue status is `resolved` and history/audit/lifecycle notify run.

**Acceptance Scenarios**:

1. **Given** a valid `X-Slack-Signature` for the destination signing secret, **When** I POST `payload` with action `resolve` and matching destination/project/issue UUIDs, **Then** HTTP 200 and the issue is resolved.
2. **Given** an invalid signature, **When** I POST the same body, **Then** HTTP 401 and the issue is unchanged.
3. **Given** a Slack destination without signing secret, **When** outbound delivery builds the Slack body for `issue.new`, **Then** no Resolve Block Kit actions are attached.

### User Story 2 - Operator configures signing secret (Priority: P1)

As a project owner, I store the Slack app Signing Secret on the destination (encrypted) so interactive buttons can be verified.

**Acceptance Scenarios**:

1. Destination form accepts optional signing secret / clear checkbox.
2. Secret is encrypted at rest like the webhook URL.

## Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | Optional encrypted `signingSecret` on `NotificationDestination` |
| FR-002 | Slack outbound includes Block Kit Resolve when secret set + issue/project UUIDs on `issue.new` / `issue.regression` / `issue.reopened` (not sample sends) |
| FR-003 | `POST /hooks/slack/interactions` public; verify `X-Slack-Signature` + ±5 min timestamp for Resolve/Assign actions |
| FR-003a (`087`, 2026-08-10) | Interactions endpoint MUST NOT echo unsigned Events-style `url_verification` challenges (reject / bad request; Events API belongs on a dedicated route if added later) |
| FR-004 | Shared `IssueStatusChanger` for UI and Slack paths (history, UserAction, resolve/reopen notify) |
| FR-005 | Resolve actor is `null` unless Slack user → Beacon member mapping exists (`071-slack-assign-mapping`) |
| FR-006 | Document Slack App Interactivity URL + signing secret in docs/product/NOTIFICATIONS.md |

## Out of Scope (this spec)

- Teams interactive actions (`069`)
- Assign / Ignore / other buttons (Assign shipped in `071`)
- Replacing Incoming Webhooks with full Slack Events API

## As-built follow-ups

- Slack user ID → member mapping + Assign-to-me: **`071-slack-assign-mapping`** (Phase 6.23 Done).

## Assumptions

- Incoming Webhooks can carry Block Kit; button clicks require a Slack **App** with Interactivity Request URL pointing at Beacon.
- Authorization for Resolve without mapping is possession of the destination signing secret (HMAC).
