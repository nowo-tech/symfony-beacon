# AI issue export (`beacon-ai-export/v1`)

Paste-ready export of a single issue (plus latest or selected event) for external AI assistants.

This is **not** the project list CSV/JSON export (`017`). It does **not** call an LLM — operators copy or download the document themselves.

## Formats

| Endpoint | Content-Type | Use |
|----------|--------------|-----|
| `GET /projects/{projectUuid}/issues/{issueUuid}/export/ai.md` | `text/markdown` | Paste into chat |
| `GET /projects/{projectUuid}/issues/{issueUuid}/export/ai.json` | `application/json` | Tooling / scripts |

Optional query: `?event={eventId}` to export a specific event belonging to the issue (otherwise the latest event).

Same ACL as viewing the issue (`requireIssueRead`).

## Document shape

Markdown starts with YAML front matter:

```markdown
---
format: beacon-ai-export/v1
issue_id: …
project: demo
level: error
status: unresolved
environment: prod
release: 1.2.3
---
```

JSON includes `"format": "beacon-ai-export/v1"` plus `issue`, `event`, `exception`, `stacktrace`, `query` (when database facts exist), `request`, `tags`, `breadcrumbs`, and `links.issue`.

## Scrubbing

Sensitive request headers are redacted (`Authorization`, `Cookie`, `Set-Cookie`, `X-Beacon-Auth`, and similar). Cookie bags and password/secret/token form fields are redacted. Do not treat the export as free of all PII — review before sharing outside your org.

## UI

Issue show → **AI export** panel: **Copy for AI** (clipboard), download Markdown, download JSON. See also [EVENT-CONTEXT.md](EVENT-CONTEXT.md).
