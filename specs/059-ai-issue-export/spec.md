# Feature Specification: AI Issue Export

**Feature Branch**: `059-ai-issue-export`  
**Created**: 2026-07-29  
**Status**: Implemented (2026-07-29)

**Input**: Per-issue export in a versioned, scrubbed format (`beacon-ai-export/v1`) as Markdown and JSON for pasting into external AI assistants. Complements list export (`017`); does not embed an LLM.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Copy / download scrubbed AI export (Priority: P1)

As a project member viewing an issue, I download or copy a Markdown (or JSON) document that summarizes the issue and latest (or selected) event so I can paste it into an AI chat.

**Independent Test**: Authenticated member opens issue show → GET export endpoints return 200 with `beacon-ai-export/v1` marker; Authorization/Cookie headers scrubbed.

**Acceptance Scenarios**:

1. **Given** an issue with at least one event, **When** I request `…/export/ai.md`, **Then** I receive Markdown with format header `beacon-ai-export/v1` and exception/stack/request sections.
2. **Given** the same issue, **When** I request `…/export/ai.json`, **Then** JSON includes `"format": "beacon-ai-export/v1"` and the same logical fields.
3. **Given** event request headers include `Authorization` or `Cookie`, **When** exported, **Then** those values are redacted (not raw secrets).
4. **Given** a member who can read the issue, **When** they open issue show, **Then** UI offers Copy for AI and download links.

### User Story 2 - Selected event optional (Priority: P2)

As a member inspecting a specific event on the issue, I can export that event instead of the latest by passing an event id query parameter.

**Acceptance Scenarios**:

1. **Given** `?event={uuid}` for an event belonging to the issue, **When** I export, **Then** the document uses that event’s payload.
2. **Given** an event id from another issue, **When** I export, **Then** the server rejects the request (404 or 403).

## Requirements

- **FR-001**: Versioned format `beacon-ai-export/v1` in MD front matter and JSON `format` field.
- **FR-002**: Include title/level/status/fingerprint, exception, stack frames, scrubbed request, tags, environment, release, breadcrumbs summary, counts, absolute issue URL.
- **FR-003**: Scrub `Authorization`, `Cookie`, `Set-Cookie`, and similar sensitive headers; do not emit API secrets.
- **FR-004**: Routes under issue show; same read ACL as viewing the issue; UI copy + download.
- **FR-005**: Document format in `docs/product/AI-EXPORT.md`.

## Success Criteria

- **SC-001**: Members can obtain a paste-ready Markdown export in one click from issue show.
- **SC-002**: Sensitive headers never appear in cleartext in either format.
