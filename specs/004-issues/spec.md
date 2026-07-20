# Feature Specification: Issues

**Feature Branch**: `004-issues`  
**Created**: 2026-07-19  
**Status**: Completed (as-built through v0.6.0)  

**Input**: Group ingested events into issues, browse/filter them per project, and inspect event detail in a structured Beacon UI.

## Summary

Issues are keyed by a **similarity fingerprint** within a project. The project issues index uses DataTables with URL-synced sort/paging/filters. Issue detail presents structured panels (stack with source context, breadcrumbs, request, tags, contexts, extra, raw JSON) plus assignee and occurrence stats. Collapsible panel preferences are stored per browser (`localStorage`) with account-level defaults.

Cross-links: [`docs/event-context.md`](../../docs/event-context.md), ingest reopen behaviour in `003-ingest`.

## Clarifications (as-built)

- **Grouping**: Client `fingerprint` array wins when present; otherwise exception type + normalized message + file/function (no line numbers). Volatile tokens (UUIDs, hex, digits) are normalized.
- **Regression**: New events on a **resolved** issue reopen it to unresolved. **Ignored** issues are not auto-reopened by ingest today.
- **Assignee**: Optional project member; filter `assignee=<userId>` or `assignee=unassigned`.
- **Occurrence windows**: Total event count plus last **24h / 7d / 30d** (computed for list + detail).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse and filter project issues (Priority: P1)

As a project member, I can open `/projects/{id}/issues` (project home redirects here), filter by query/level/status/environment/assignee, sort columns, and page results.

**Independent Test**: Seed issues; apply filters and `sort`/`dir`/`page`/`per_page` query params; assert list and URL state.

**Acceptance Scenarios**:

1. **Given** I am a project member, **When** I open the project, **Then** I land on the issues list.
2. **Given** filters `q`, `level`, `status`, `environment`, `assignee`, **When** I submit or change them, **Then** the list reflects the filters and they remain in the URL.
3. **Given** DataTables paging (`per_page` 10/25/50/100), **When** I change page or page size, **Then** `page` and `per_page` are reflected in the URL.
4. **Given** sortable columns (title, level, assignee, events, events_24h/7d/30d, first_seen, last_seen), **When** I sort, **Then** `sort` and `dir` are in the URL and the order matches.
5. **Given** occurrence columns, **When** the list renders, **Then** each row shows total and window counts when available.

### User Story 2 - Inspect issue and event detail (Priority: P1)

As a developer, I open an issue and see a structured layout: hero, highlights, stack frames, breadcrumbs, request, tags, contexts, extra, raw JSON, plus aside (details, assignee, recent events).

**Independent Test**: Ingest an event with stack/source context; open issue and event pages; assert panels and stack rendering.

**Acceptance Scenarios**:

1. **Given** an issue with events, **When** I open issue detail, **Then** I see title, level/status, occurrence stats (total + windows, first/last seen), and recent events.
2. **Given** stack frames with `pre_context` / `context_line` / `post_context`, **When** I expand a frame, **Then** source context is shown; the first frame is expanded by default.
3. **Given** a stack frame path, **When** I use Copy path, **Then** the clipboard gets `abs_path:lineno` or `filename:lineno`.
4. **Given** collapsible panels, **When** I collapse/expand, **Then** state persists in `localStorage` (`beacon.issuePanelState`); account Display prefs supply defaults via `preferredCollapsedIssuePanels`.
5. **Given** an event id, **When** I open `/projects/{id}/events/{eventId}`, **Then** structured context and raw payload are available.

### User Story 3 - Assign an issue (Priority: P2)

As a project member, I can assign an issue to a project member (or leave unassigned) from the issue sidebar.

**Independent Test**: POST assign with a member id; assert persistence and rejection for non-members.

**Acceptance Scenarios**:

1. **Given** project members, **When** I pick an assignee via autocomplete and save, **Then** the issue stores that user.
2. **Given** I clear the assignee, **When** I save, **Then** the issue is unassigned.
3. **Given** a user who is not a project member, **When** assign is attempted, **Then** the change is rejected.

### User Story 4 - Grouping and status (Priority: P1)

As ingest, similar events merge into one issue; resolved issues reopen on new events.

**Independent Test**: Send two events that differ only by volatile tokens/line numbers; assert one issue. Resolve then send again; assert unresolved.

**Acceptance Scenarios**:

1. **Given** two events with the same type/file/function and messages that differ only by IDs/numbers, **When** both are ingested, **Then** they share one fingerprint/issue.
2. **Given** a client `fingerprint` array, **When** ingested, **Then** that fingerprint is used instead of the computed one.
3. **Given** a resolved issue, **When** a matching event arrives, **Then** status becomes unresolved.
4. **Given** an ignored issue, **When** a matching event arrives, **Then** status remains ignored (no auto-reopen).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Issues MUST be unique per `(project, fingerprint)`.
- **FR-002**: Fingerprint MUST prefer client array; else similarity rules (normalize message tokens; type + path/function without linenos).
- **FR-003**: Issues list MUST support filters and URL state: `q`, `level`, `status`, `environment`, `assignee`, `sort`, `dir`, `page`, `per_page`.
- **FR-004**: List MUST use DataTables (responsive) via Stimulus `datatable` for client paging/sort UX while honouring server initial sort.
- **FR-005**: Occurrence stats MUST expose total, last 24h, 7d, 30d, first_seen, last_seen.
- **FR-006**: Issue detail MUST present structured panels including stack source context and Copy path.
- **FR-007**: Panels MUST be collapsible with `localStorage` + optional account defaults (`IssuePanelIds`).
- **FR-008**: Assignee MUST be an optional project member with list filter support.
- **FR-009**: Status workflow MUST support unresolved / resolved / ignored (UI + reopen rule as above).

### Non-Functional

- **NFR-001**: Docs/UI/PHPDoc in English; Twig `lang="en"`.
- **NFR-002**: PHPUnit coverage under `tests/` for grouping, list/sort, assignee, and UI-critical behaviour.

## Success Criteria *(mandatory)*

- **SC-001**: Operators can find and sort noisy issues using filters and occurrence windows without leaving the URL shareable.
- **SC-002**: Stack investigation shows source context and copyable paths when the client sends them (BeaconBundle ≥ 1.3.0).
- **SC-003**: Similar exceptions collapse; resolved issues reopen on regression.
