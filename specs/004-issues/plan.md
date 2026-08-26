# Implementation Plan: Issues

**Status**: Completed (as-built through post-0.8.1: status UI + `issue_history`).

As-built highlights:

- Similarity `FingerprintCalculator`, assignee + Autocomplete, `IssueOccurrenceStats`
- DataTables + URL state (`IssueListSort`), structured issue show with collapsible panels and stack source context / copy path
- Manual status actions (`POST /projects/{projectId}/issues/{id}/status`) for resolved / unresolved / ignored
- `IssueHistoryEntry` + `IssueHistoryRecorder` (`issue_history` table); assign and status (UI + ingest reopen) append timeline rows
- Project clear-history deletes `issue_history` before issues

### Status graph (do not use Symfony Workflow)

Canonical decision: spec amendment **Issue status graph is not Symfony Workflow** (2026-08-26).

| Path | Owner | Graph |
|------|--------|--------|
| UI / API status POST | `IssueStatusChanger` → `IssueStatusTransition::assertCanTransition()` | Any of the three statuses to the other two; same status is a no-op |
| Ingest regression | `IssueEnvelopeWriter` (and equivalent ingest writers) | Direct `resolved`/`ignored` → `unresolved`; does **not** go through `IssueStatusChanger` |
| Duplicate / merge | `IssueDuplicateMarker` / `IssueMergeService` | Force `ignored` as a domain action |

Do **not** add `symfony/workflow` or `nowo-tech/workflow-bundle` for this cycle. Revisit only with a new spec (extra states, named guards, or per-project graphs); then YAML `state_machine` in-repo, not DB-defined WorkflowBundle CRUD.

See `spec.md` for acceptance criteria. No further plan work unless a new Issues epic is specified.
