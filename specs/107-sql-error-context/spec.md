# Feature Specification: SQL / database error context on issue detail

**Feature Branch**: `107-sql-error-context`  
**Created**: 2026-08-26  
**Status**: Implemented (Unreleased / Phase 6.59)  
**Roadmap**: Phase 6.59 (proposed; after `106`)  

**Input**: Analyze a Sentry incident (Laravel `QueryException` / MySQL `SQLSTATE[42000]` 1055 `ONLY_FULL_GROUP_BY`, failing `SELECT … GROUP BY`, application frame `AttendanceRepository::getAttendanceSummary`) and specify how Beacon should collect and display the same diagnostic surface — especially MySQL/SQL facts, plus the in-app stack location — without forcing operators to open raw JSON.

## Summary

When an ingested event is a **database error**, issue and event detail MUST surface a first-class **Query** section (SQLSTATE / vendor code / SQL / optional bindings) and MUST open the **application** stack frame by default. The stored Envelope payload remains the source of truth (`010`); this feature **derives** display facts at read time from exception text, structured extra/contexts, and query breadcrumbs already stored.

BeaconBundle (local sibling repo) **is in scope**: capture of structured `contexts.db` on database exceptions, released as **1.8.0**. The server MUST still work when only exception messages or Doctrine query breadcrumbs are present (older clients / Laravel Sentry SDKs).

| ID | Area | Deliverable |
|----|------|-------------|
| Q1 | Query section | Dedicated collapsible Query panel on issue Main and event detail when SQL/SQLSTATE/vendor DB error facts can be derived |
| Q2 | Extraction | Derive SQLSTATE, vendor code, driver hint, SQL text, `sql_mode` when present, and bindings when already in the payload |
| Q3 | Stack | Default-expand the outermost in-app frame (same preference as culprit); keep vendor frames collapsed |
| Q4 | Culprit | Stored/displayed culprit MUST fit a typical PHP `Class::method` (today’s 40-character clip is insufficient) |
| Q5 | Exception chain | Each exception value can show its own type, message, and frames when present; extraction searches the whole chain |
| Q6 | Export / sample | AI export includes Query facts; sample seed includes at least one database-error event |
| Q7 | BeaconBundle | On `captureException`, attach `contexts.db` (SQLSTATE, vendor code, SQL when known) without requiring `instrumentation.doctrine`; ship **1.8.0** |

## Non-goals

- Changing Envelope ingest, fingerprint grouping, or issue title rules (`FingerprintCalculator` message normalization stays as today)
- Requiring `instrumentation.doctrine` (spans) for Query facts — exception-time `contexts.db` is enough; opt-in Doctrine breadcrumbs remain additive
- Performance transaction spans / N+1 UI (`006` / `024` already render `db.*` spans)
- Executing, explaining, or “fixing” the operator’s SQL
- New search filters on SQLSTATE or SQL text (list search remains title/culprit/tags as today)
- Disabling `ONLY_FULL_GROUP_BY` (or any sql_mode) on Beacon’s own database
- Session replay, query explain plans, or live schema introspection
- New cookies, third-party scripts, or public marketing surfaces

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See the failing query without raw JSON (Priority: P1)

As a project member investigating a production database error, I open the issue and immediately see the SQLSTATE (or vendor error code), a readable copy of the failing SQL when it was captured, and optional parameter bindings — without expanding Extra or Raw payload.

**Why this priority**: For `QueryException` / PDO / Doctrine failures the query is the primary diagnostic; burying it in a 2 KB exception string or Extra JSON is the gap versus Sentry.

**Independent Test**: Store an event whose exception value matches a Laravel/Doctrine-style `SQLSTATE[42000]: … (SQL: SELECT …)` message; open issue Main and event detail; assert a Query section with SQLSTATE `42000`, vendor code `1055` when present, and the SQL text. Store a second event with no SQL/SQLSTATE; assert the Query section is absent.

**Acceptance Scenarios**:

1. **Given** an event whose exception message includes `SQLSTATE[42000]` and an embedded SQL statement (Laravel `(SQL: …)` or equivalent), **When** I open issue Main, **Then** a Query section is visible (default expanded) showing SQLSTATE, vendor code when present, and the SQL in a monospace block.
2. **Given** the same event, **When** I open event detail, **Then** the same Query facts render (shared event body).
3. **Given** structured query data already in the payload (`extra.sql` / `extra.query`, `contexts.db`, or a breadcrumb category `query` / `db` with SQL in `data`), **When** that structured value differs from the exception string, **Then** structured fields win over regex extraction from the message.
4. **Given** an event with exception type/value but no SQLSTATE, SQL text, or query breadcrumb, **When** I open issue Main, **Then** no Query section is rendered.
5. **Given** Query facts were extracted, **When** I view Highlights, **Then** SQLSTATE and vendor code appear there when known (in addition to the Query section).

---

### User Story 2 - Land on the application frame, not vendor internals (Priority: P1)

As a member, the stack I see first is the **in-app** call site (repository, controller, handler), with source context when the client sent it — not the innermost PDO/Connection frame.

**Why this priority**: Sentry’s value for this incident is `AttendanceRepository.php:158`, not `Connection.php`. Beacon already prefers in-app for culprit calculation but expands the innermost frame in the UI.

**Independent Test**: Payload with vendor frames innermost and one `in_app` frame with `context_line`; open issue stack; assert that in-app frame’s details are open and vendor frames are closed; culprit on the issue matches that in-app function or file.

**Acceptance Scenarios**:

1. **Given** frames including vendor throw-site and at least one `in_app: true` frame, **When** the stack renders, **Then** the preferred in-app frame (outermost in-app walking from the throw site, same rule as culprit) is expanded and others start collapsed.
2. **Given** that in-app frame has `pre_context` / `context_line` / `post_context`, **When** it is expanded, **Then** source lines render as today (`010` FR-008).
3. **Given** no in-app frames, **When** the stack renders, **Then** the innermost frame is expanded (current fallback).
4. **Given** `exception.values` has more than one entry (e.g. QueryException wrapping PDOException), **When** the stack section renders, **Then** each value shows type and message; frames attached to a value render under that value (not only under the first).

---

### User Story 3 - Culprit and AI export carry the query location (Priority: P2)

As a member scanning the issue list or pasting an AI export, I see a complete application culprit and the same Query facts that the UI shows.

**Why this priority**: List/hero culprit is clipped today (~40 characters), which hides typical `Repository::method` names; export (`059`) currently has exception + stack but no dedicated query block.

**Independent Test**: Ingest a database-error event with a long in-app function name; assert list + hero culprit is not clipped mid-name; AI markdown/JSON includes a Query section when facts exist.

**Acceptance Scenarios**:

1. **Given** an in-app function such as `App\Repositories\Eloquent\AttendanceRepository::getAttendanceSummary`, **When** the issue is stored and listed, **Then** the visible culprit includes the method name in full (not cut off at 40 characters).
2. **Given** Query facts on the selected event, **When** I copy AI markdown or JSON, **Then** the export includes SQLSTATE, vendor code, and SQL text (bindings included when present in the payload).
3. **Given** no Query facts, **When** I export, **Then** there is no empty Query heading (omit the section).

---

### Edge Cases

- **SQL without SQLSTATE**: MySQL-style `(1040, 'Too many connections')` or Doctrine “too many connections” still yields a Query (or compact DB-error) section with vendor code and message; SQL block omitted if no statement was captured.
- **SQLSTATE without SQL**: Connection failures (`HY000` / `1040`) show code + message only.
- **Multiple query breadcrumbs**: Prefer the last query breadcrumb before the error; do not dump the entire breadcrumb trail into Query (full trail stays in Breadcrumbs).
- **Bindings**: Show only if already present as a list/map in the payload. Do not invent values from `?` placeholders. Do not treat bindings as a reason to hide SQL.
- **PII in SQL**: Literals may already sit in `event.payload`. Display as stored. Do not add server-side SQL rewriting in this slice; client scrubbing (`023`) remains the control. Extra/Raw stay collapsed by default.
- **Huge SQL**: Truncate displayed SQL with a clear remaining-length hint; full text remains in Raw payload.
- **Non-MySQL SQLSTATE**: PostgreSQL/SQLite `SQLSTATE[…]` MUST parse the same way (SQLSTATE is vendor-neutral). Driver hint is best-effort (`mysql`, `pgsql`, `sqlite`, or unknown).
- **Malformed payload**: Missing `exception.values`, non-array frames, or non-string messages MUST NOT error the page; fall back to existing exception/stack/message panels.
- **Share-link viewers**: Same Query + stack rules as members who can view the issue (`046` scope unchanged).
- **Historical events**: No backfill job; derivation is at render time from existing JSON.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Issue Main and event detail MUST render a dedicated Query section when derived **Query facts** are non-empty (SQLSTATE, vendor code, SQL text, or structured query object). The section MUST be absent otherwise.
- **FR-002**: Query facts MUST be derived at display (and AI export) time from the stored event payload, in this precedence: (1) structured `contexts.db` or `extra.sql` / `extra.query` / `extra.bindings`; (2) breadcrumb `query` / `db` / `sql.query` data; (3) parse of `exception.values[].value` (and `payload.message` if no exception) for `SQLSTATE[…]`, vendor numeric codes, Laravel `(SQL: …)`, and `sql_mode=` fragments.
- **FR-003**: Extraction MUST search the full exception chain, not only the first value.
- **FR-004**: The Query section MUST show, when known: SQLSTATE, vendor/SQL error code, optional driver hint, optional `sql_mode`, SQL in a copyable monospace block, and bindings. It MUST NOT execute SQL.
- **FR-005**: Displayed SQL MUST be truncated at a documented maximum (plan: enough for typical ORM statements, not unbounded). Raw payload keeps the original string.
- **FR-006**: When Query facts include SQLSTATE or vendor code, Highlights MUST list those values.
- **FR-007**: When Query facts exist, the issue hero subtitle SHOULD prefer the database error summary (SQLSTATE + vendor message) over dumping the full SQL in the hero; the stack panel still shows the original exception `value`.
- **FR-008**: Stack UI MUST default-expand the same preferred in-app frame used for culprit; if none, expand the innermost frame. Other frames start collapsed. Source context rules from `010` FR-008 are unchanged.
- **FR-009**: Each `exception.values[]` entry MUST render its type and value; stack frames nested on that entry MUST render under it.
- **FR-010**: Stored issue culprit MUST retain a typical PHP class::method or relative `app/`/`src/` path without clipping at 40 characters. Existing issues MAY keep old clipped values until a new event updates culprit (no mandatory backfill).
- **FR-011**: AI export (`059`) MUST include Query facts in both markdown and JSON when present, using the same derivation as the UI.
- **FR-012**: Sample/demo issue seed MUST include at least one event that exercises the Query section (SQLSTATE + SQL + in-app frame with source context) so QA/E2E can open it without a live Laravel app.
- **FR-013**: Query panel collapse id MUST participate in the existing per-user collapsed-panel preference set (default: expanded). Extra and Raw remain default-collapsed.
- **FR-014**: Docs (`docs/product/EVENT-CONTEXT.md`) MUST describe Query derivation, truncation, PII caveat, and BeaconBundle `contexts.db` (v1.8.0+).
- **FR-015**: BeaconBundle MUST attach `contexts.db` when capturing a database-related exception (PDO / Doctrine DBAL when present / SQLSTATE in the message), including SQL text when the driver exposes it. Quoted SQL literals SHOULD be scrubbed. This MUST NOT require `instrumentation.doctrine`.

### Non-Functional / Privacy

- **NFR-001**: No new cookies or third-party scripts. Query UI is operator issue/event detail only (existing AuthKit session). Cookie consent / legal pages are unchanged.
- **NFR-002**: SQL and bindings may contain personal data already stored in `event.payload`. Operators remain responsible for client `before_send` scrubbing and legal basis (`010` NFR-001). This feature MUST NOT log Query facts to application logs beyond what ingest already records.
- **NFR-003**: Docs, PHPDoc, and default UI copy remain English (`lang="en"`).
- **NFR-004**: Derivation MUST be CPU-cheap on a single event payload (no full-table scans, no extra queries beyond loading the event already shown).
- **NFR-005**: PHPUnit MUST cover extractor edge cases and issue/event HTML for present vs absent Query sections (constitution VIII). Includable coverage stays at the project gate.

### Key Entities

- **Query facts (derived, not a new table)**: SQLSTATE, vendor code, driver hint, sql_mode, SQL text, bindings, source (`structured` | `breadcrumb` | `exception_message`). Computed from `event.payload`; not persisted in v1.
- **Event payload**: Unchanged JSON document (`010`). Structured `contexts.db` is optional and wins when present.
- **Issue culprit**: Existing string field; longer display/storage limit so application locations remain readable.
- **Exception value**: Existing Envelope `exception.values[]` chain (type, value, optional stacktrace.frames with `in_app` and source context).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A member can identify the failing SQL (when captured) and the SQLSTATE or vendor code on issue Main without opening Extra or Raw, on the same visit as the stack.
- **SC-002**: When at least one in-app frame exists, the first expanded stack frame is that application frame, not a vendor database driver frame, in 100% of covered fixtures.
- **SC-003**: Issue list and detail culprit show a complete typical application method name (no mid-name clip at the old 40-character limit) for newly ingested events.
- **SC-004**: Automated tests prove Query appears for a stored database-error event and is omitted when no query/SQLSTATE/vendor code can be derived.
- **SC-005**: An AI export of a database-error issue contains the same SQLSTATE and SQL the member saw in the UI (when those facts exist).

## Assumptions

- Envelope payloads from Sentry-compatible SDKs (Laravel/PHP Sentry, BeaconBundle, Doctrine breadcrumbs) already carry exception messages and optionally query breadcrumbs; v1 does **not** require a client release.
- Full `event.payload` remains canonical; promoting Query facts to SQL columns is deferred (same storage model as `010` Q1 for uncommon fields).
- Fingerprint grouping is unchanged: similar SQL with different numeric literals may already share a fingerprint via existing number normalization; table names still distinguish queries.
- Culprit updates on new events only; historical clipped culprits are acceptable.
- Bindings are shown as received; client scrubbing is the privacy control.
- Share-link and viewer roles keep read-only access to the same panels.
- Truncation limit and new culprit max length are plan-time constants (SQL display 8 KiB; culprit 255 characters).
- BeaconBundle **1.8.0** is prepared in the local sibling checkout (`repositories/bundles/BeaconBundle`); Packagist pin on this server follows after the tag is published.

## Dependencies

- `010-rich-event-context` — stack source context, payload-as-source-of-truth, issue/event body partial
- `059-ai-issue-export` — export shape version bump if a new `query` key is added
- `023-client-tags-scrubbing` — client-side PII control (no server rewrite of SQL)
- `024-client-spans` — Doctrine breadcrumbs may supply SQL; Performance UI unchanged
- `102-issue-detail-tabs-api-reveal` — Query lives on Main / event pages, not Similar/History tabs
