# Feature Specification: Similar Issues Suggestions

**Feature Branch**: `041-similar-issues`
**Created**: 2026-07-31
**Status**: Implemented

**Input**: On issue show, suggest similar issues (fingerprint / title proximity) with shortcut to link or mark duplicate.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Suggestions (P1)

As a member, I see similar open/resolved issues on the issue page.

**Acceptance Scenarios**:

1. **Given related fingerprints or close titles in the same project, When I open an issue, Then a capped similar list appears.**
2. **Given a suggestion, When I mark duplicate/link, Then existing workflow relations are used (`015`).**

## Requirements *(mandatory)*

- **FR-001**: Similarity from fingerprint and/or title proximity within the project.
- **FR-002**: Cap N suggestions; exclude self.
- **FR-003**: Actions reuse issue workflow (duplicate/link) with authorization.

## Success Criteria

- **SC-001**: Suggestions stay project-scoped.
- **SC-002**: Empty state when none similar.

## Out of scope

- Cross-project similarity.
- ML embedding service.
