# Feature Specification: Issue detail tabs + temporary API DSN reveal

**Feature Branch**: `102-issue-detail-tabs-api-reveal`  
**Created**: 2026-08-16  
**Status**: Done (v1.18.4 / Phase 6.53)  
**Roadmap**: Phase 6.53  

**Input**: Split issue detail into path-based Main / Similar / History tabs; tighten issue hero back-nav and panel spacing with flat app-paper chrome; keep create/rotate API DSN show-once, but present the secret under a temporary-reveal control that auto-hides and can clear plaintext from the DOM.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| I1 | Issue detail routes | `GET /projects/{uuid}/issues/{id}/{tab}` with `tab` ∈ `main\|similar\|history` (default `main`); `IssueShowTab` enum; shared `_tabs` nav |
| I2 | Tab content | Main: events/comments/aside triage; Similar: suggestions (`041`); History: assignment/status timeline |
| I3 | Shell chrome | Flat app paper + shared grid; tighter hero back-nav / panel spacing (issue show + event) |
| K1 | API DSN reveal | Session flash `_beacon_last_api_key_dsn` still one-shot; attached to matching active key row when public key matches; Stimulus `temporary-reveal` (30s, clear-on-hide) |
| K2 | Masking | `ProjectApiKey::maskDsn()` for masked display; ordinary Settings GET stays redacted (`002` / `018` / `087`) |
| QA | Tests | Functional visibility + builder unit; Vitest for `temporary_reveal_controller`; E2E assertions on `issue-detail-tabs` |

## Non-goals

- Recovering secrets for active keys on ordinary Settings GET (hash-at-rest / `096` remains authoritative)
- Client-only hash tabs without distinct URLs
- Changing similarity algorithm (`041`) or workflow mutations (`015`)

## User Scenarios & Testing

### User Story 1 - Navigate issue sections by URL (P1)

As a member, I open Main, Similar, or History via distinct URLs so I can bookmark, share, and use browser back within an issue.

**Independent Test**: Open an issue; click Similar and History tabs; assert path ends with `/similar` or `/history` and the matching panel is shown; invalid `{tab}` → 404.

**Acceptance Scenarios**:

1. **Given** an issue I can view, **When** I open `/projects/{p}/issues/{i}` or `…/main`, **Then** the Main tab is current and primary triage content renders.
2. **Given** the same issue, **When** I open `…/similar`, **Then** Similar is current and the capped similar list (or empty state) from `041` is shown.
3. **Given** the same issue, **When** I open `…/history`, **Then** History is current and assignment/status history is shown.
4. **Given** an unknown tab slug, **When** I request the URL, **Then** the response is **404**.

### User Story 2 - Copy DSN once after create/rotate with timed hide (P1)

As a project API-key manager, after create/rotate I briefly see the full Envelope DSN, can copy it, and the UI hides/clears the secret so a later ordinary Settings visit does not re-show it.

**Independent Test**: `ProjectApiKeyVisibilityTest` + `ProjectSettingsPageBuilderTest`; Vitest `temporary_reveal_controller.test.ts`.

**Acceptance Scenarios**:

1. **Given** `project.api_keys.manage`, **When** I create or rotate a key and land on Settings, **Then** the matching active key row (or flash) exposes the full DSN via `temporary-reveal` starting revealed, with copy control.
2. **Given** that reveal, **When** the configured duration elapses or I hide, **Then** the display returns to a masked/cleared state and, with clear-on-hide, plaintext is stripped from the controller value so it cannot be toggled back without another create/rotate.
3. **Given** a subsequent ordinary Settings GET (no flash), **When** I open API keys, **Then** active keys show public id + redacted hint only — no full DSN.
4. **Given** a revoked key, **When** Settings renders, **Then** no DSN, secret, or clipboard control appears for that key.

### User Story 3 - Consistent issue chrome (P2)

As a member, issue detail and event pages share flat paper chrome, readable back-nav, and section spacing aligned with the rest of the app shell.

**Independent Test**: Visual/regression via issue show + event templates; CSS under `_components.scss` keeps `.app-shell` paper flat with the shared grid.

**Acceptance Scenarios**:

1. **Given** issue show, **When** the page loads, **Then** back navigation uses the shared issue back-link partial and section tabs sit above tab content.
2. **Given** event detail, **When** the page loads, **Then** spacing/chrome matches the tightened issue shell (no conflicting inset “card hero”).

## Functional Requirements

- **FR-001**: Issue show MUST accept optional `{tab}` (`main` | `similar` | `history`), default `main`; invalid values MUST 404.
- **FR-002**: Tab navigation MUST be real links (`path('issue_show', … tab)`) via shared tabs partial, not hash-only client tabs.
- **FR-003**: Similar tab content MUST reuse `041` suggestions and `015` duplicate/link shortcuts; History MUST surface existing assignment/status history.
- **FR-004**: Create/rotate MUST continue to set session `_beacon_last_api_key_dsn`; Settings builder MUST consume it once per render and MAY attach it to the matching active key by public key.
- **FR-005**: One-shot DSN UI MUST use Stimulus `temporary-reveal` (default ~30s; clear-on-hide) plus clipboard-copy; masked display MUST use `ProjectApiKey::maskDsn()`.
- **FR-006**: Ordinary Settings GET without flash MUST NOT embed full DSN/secret for active keys; revoked keys MUST stay fully redacted (`002` FR-003, `018` FR-003/004, `087` FR-001).
- **FR-007**: Issue/event chrome MUST keep flat app paper with the shared shell grid (no competing hero card plane).

## Success Criteria

- **SC-001**: Members can deep-link to Similar and History; invalid tab → 404 in functional tests.
- **SC-002**: After create/rotate, DSN is copyable once; ordinary GET stays redacted (`ProjectApiKeyVisibilityTest`).
- **SC-003**: Temporary reveal auto-hides / clear-on-hide covered by Vitest.
- **SC-004**: E2E issue detail asserts `issue-detail-tabs` (and related UC-ISS-12 / 19 / 23 coverage).

## Assumptions

- Default tab label catalogue keys: `issues.tab_main`, `issues.similar_title`, `issues.history_title`.
- Breadcrumbs / ProjectAware resolvers treat bare issue show as Main.
- Hash-at-rest secrets (`096`) mean temporary reveal never re-derives DSN from storage.

## Out of scope

- Read-API token temporary reveal (separate surface; still one-shot flash where applicable).
- Changing Envelope DSN format or ingest auth.

## Cross-links

- Amends: `041-similar-issues`, `015-issue-workflow` (detail chrome), `002` / `018` / `087` (DSN reveal UX).
- Related: `004-issues`, `096-audit-follow-up-hardening`.
