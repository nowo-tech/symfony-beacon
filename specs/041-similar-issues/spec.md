# Feature Specification: Similar Issues Suggestions

**Feature Branch**: `041-similar-issues`
**Created**: 2026-07-31
**Status**: Implemented (tab URL surface amended 2026-08-16 — see `102`)

**Input**: On issue show, suggest similar issues (fingerprint / title proximity) with shortcut to link or mark duplicate.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Suggestions (P1)

As a member, I see similar open/resolved issues on the issue page.

**Acceptance Scenarios**:

1. **Given related fingerprints or close titles in the same project, When I open an issue (Main or Similar tab), Then a capped similar list appears.**
2. **Given a suggestion, When I mark duplicate/link, Then existing workflow relations are used (`015`).**
3. **Given** an issue I can view, **When** I open `/projects/{p}/issues/{i}/similar`, **Then** the Similar tab is current and the suggestions panel is the primary body (`102`).

## Requirements *(mandatory)*

- **FR-001**: Similarity from fingerprint and/or title proximity within the project.
- **FR-002**: Cap N suggestions; exclude self.
- **FR-003**: Actions reuse issue workflow (duplicate/link) with authorization.
- **FR-004** (2026-08-16): Similar content MUST be reachable via dedicated issue show tab `similar` (`102` FR-001 / FR-003); Main may still surface shortcuts as product chrome allows.

## Success Criteria

- **SC-001**: Suggestions stay project-scoped.
- **SC-002**: Empty state when none similar.
- **SC-003**: Deep link to `…/similar` selects the Similar tab (`102` SC-001).

## Out of scope

- Cross-project similarity.
- ML embedding service.

## Amendments

### 2026-08-16 — Path-based Similar tab (`102`)

Issue detail uses `IssueShowTab` routes (`main` | `similar` | `history`). Similar suggestions remain `041` behaviour; presentation lives primarily under the Similar tab URL. See `specs/102-issue-detail-tabs-api-reveal/`.
