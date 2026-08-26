# Contract: Query facts + `contexts.db`

## Server display

- Query panel `data-testid="issue-query"` present iff extractor returns non-empty facts.
- Panel id `query` participates in `IssuePanelIds` (default expanded).
- Highlights rows for SQLSTATE / vendor code when set.
- Hero subtitle uses `QueryFacts.summary` when set; stack panel still shows raw `exception.value`.
- SQL block: monospace, copy control (`clipboard-copy`).
- Stack: `data-open` on preferred in-app frame (`IssueStackPresenter`); each `exception.values[]` with frames renders its own list.

## Envelope `contexts.db` (client → ingest)

```json
{
  "type": "sql",
  "sqlstate": "42000",
  "code": "1055",
  "driver": "pdo",
  "sql": "SELECT id, user_id, date, status FROM attendances WHERE user_id = ? GROUP BY date",
  "sql_mode": "only_full_group_by"
}
```

Wire-compatible with Sentry-style `contexts` maps. Unknown extra keys ignored by extractor.

## AI export (`beacon-ai-export/v1`)

Optional top-level `query` object (same fields as QueryFacts `toArray()`, no secrets beyond what payload already had). Omit key when empty. Markdown `## Query` section when present.
