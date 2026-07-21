# Implementation Plan: Issues

**Status**: Completed (as-built through post-0.8.1: status UI + `issue_history`).

As-built highlights:

- Similarity `FingerprintCalculator`, assignee + Autocomplete, `IssueOccurrenceStats`
- DataTables + URL state (`IssueListSort`), structured issue show with collapsible panels and stack source context / copy path
- Manual status actions (`POST /projects/{projectId}/issues/{id}/status`) for resolved / unresolved / ignored
- `IssueHistoryEntry` + `IssueHistoryRecorder` (`issue_history` table); assign and status (UI + ingest reopen) append timeline rows
- Project clear-history deletes `issue_history` before issues

See `spec.md` for acceptance criteria. No further plan work unless a new Issues epic is specified.
