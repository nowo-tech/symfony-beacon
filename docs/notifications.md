# Project notifications

Beacon can notify external systems when a project records a **new issue**, an **issue regression**, or an **N+1** performance group.

See feature spec `specs/009-project-notifications/` and the product [ROADMAP](ROADMAP.md) (Phase 1).

## Configure

1. Open **Project → Settings**.
2. Under **Notifications**, add a destination (owner/admin only).
3. Choose type:
   - **Slack Incoming Webhook** — paste the Slack Incoming Webhook URL.
   - **Generic HTTP webhook** — any URL that accepts `POST` JSON.
4. Select alert categories (issue levels and/or N+1).
5. Optionally **Send test** to verify delivery.

Webhook URLs are **masked** in the settings list.

## When alerts fire

| Signal | Notifies? |
|--------|-----------|
| First event of a new issue (matching level) | Yes |
| Another event on an already **unresolved** issue | No |
| Event on a **resolved** or **ignored** issue (reopens to unresolved) | Yes (regression) |
| Transaction with N+1 groups ≥ 1 (and category enabled) | Yes |
| Disabled destination | No |

## Delivery

Outbound HTTP runs on the **Messenger `async`** transport (`DeliverNotificationMessage`), with the same retry policy as envelope processing. Envelope **ACK never waits** on Slack/webhooks.

## Generic HTTP JSON body

```json
{
  "event": "issue.new",
  "summary": "New issue: [error] TypeError: …",
  "project": { "id": 1, "name": "Acme", "slug": "acme" },
  "issue": {
    "id": 10,
    "title": "TypeError: …",
    "level": "error",
    "status": "unresolved",
    "culprit": "App\\Service::run"
  },
  "url": "https://beacon.example/projects/1/issues/10",
  "category": "error",
  "test": false
}
```

Other `event` values: `issue.regression`, `performance.n_plus_one`, `test`.

Slack destinations receive `{ "text": "<summary>", "beacon": { …same payload… } }`.

## Permissions

| Role | Manage destinations |
|------|---------------------|
| Owner / Admin | Yes |
| Member | View settings section only (no add/edit/test/delete) |
