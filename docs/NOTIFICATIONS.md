# Project notifications

Beacon can notify external systems when a project records a **new issue**, an **issue regression**, or an **N+1** performance group.

Supported channels: **Slack**, **Discord**, **Microsoft Teams**, **Telegram**, **email**, and **generic HTTP**.

See feature spec `specs/009-project-notifications/` and the product [ROADMAP](ROADMAP.md) (Phase 1).
In the app, open **Project → Settings → Notifications → Setup guides** for the same manuals.

## Configure (any channel)

1. Open **Project → Settings**.
2. Under **Notifications**, click **Add destination** (owner/admin only).
3. Choose the channel **type** and paste the matching **endpoint** (see manuals below).
4. Select alert categories (issue levels and/or N+1).
5. Save, then optionally **Send test** to verify delivery.

Endpoints are **encrypted at rest** and **masked** in the settings list (URLs, emails, and Telegram tokens).

There are **no** global `SLACK_*` / `DISCORD_*` / `TELEGRAM_*` environment variables — each destination stores its own endpoint on the project. Email delivery also needs `MAILER_DSN` (see [Email](#email)).

Outbound HTTP destinations (Slack / Discord / Teams / HTTP) are checked against an SSRF guard: private, link-local, and cloud-metadata addresses are blocked in production. Set `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS=1` (or the `when@dev` / `when@test` defaults) only for local webhooks.

---

## Channel setup manuals

### Slack (Incoming Webhook)

1. In Slack, open the workspace where alerts should appear.
2. Go to **[api.slack.com/apps](https://api.slack.com/apps)** → **Create New App** → **From scratch**.
3. Under **Incoming Webhooks**, turn the feature **On**.
4. Click **Add New Webhook to Workspace**, pick a channel, and authorize.
5. Copy the webhook URL (`https://hooks.slack.com/services/...`).
6. In Beacon: type **Slack Incoming Webhook**, paste the URL as the endpoint, choose categories, save.
7. Use **Send test** and confirm a message appears in the Slack channel.

**What Beacon sends:** JSON `{ "text": "<summary>", "beacon": { …canonical payload… } }`.

**Tips:** Prefer a dedicated `#errors` / `#ops` channel. Rotate the webhook by regenerating it in Slack and updating the destination in Beacon. Do not commit webhook URLs to git.

---

### Discord (webhook)

1. In Discord, open **Server settings** → **Integrations** → **Webhooks** (or channel → **Edit channel** → **Integrations**).
2. **New Webhook**, name it (e.g. `Beacon`), choose the target channel.
3. **Copy Webhook URL** (`https://discord.com/api/webhooks/...` or `https://discordapp.com/api/webhooks/...`).
4. In Beacon: type **Discord webhook**, paste the URL, choose categories, save, then **Send test**.

**What Beacon sends:** `{ "content": "<summary>", "embeds": [{ "title", "description", "url", "color" }] }`.

**Tips:** Discord rate-limits webhooks; Beacon delivers asynchronously, so bursts are queued. Delete/rotate the webhook in Discord if it leaks.

---

### Microsoft Teams (Incoming Webhook)

1. In Teams, open the channel → **⋯** → **Connectors** / **Manage channel** → **Connectors** (UI labels vary by Teams version).
2. Find **Incoming Webhook**, configure it, name it (e.g. `Beacon`), and create.
3. Copy the webhook URL Teams provides.
4. In Beacon: type **Microsoft Teams webhook**, paste the URL, choose categories, save, then **Send test**.

**What Beacon sends:** Office 365 **MessageCard** JSON (`@type`, `summary`, `title`, `text`, optional **Open in Beacon** action when an issue URL is present).

**Tips:** If your tenant only allows Workflows / Power Automate instead of classic Incoming Webhooks, use a workflow that accepts an HTTP POST and point a **Generic HTTP** destination at that URL (payload shape differs — prefer adapting the workflow to the [canonical JSON](#generic-http-json-body), or keep Teams type when classic Incoming Webhooks are available).

---

### Telegram (bot)

1. In Telegram, open **[@BotFather](https://t.me/BotFather)** → `/newbot` → follow prompts → copy the **bot token** (`123456:ABC-DEF...`).
2. Start a chat with your bot (or add it to a group).
3. Obtain the **chat id**:
   - Private chat: message the bot, then call  
     `https://api.telegram.org/bot<TOKEN>/getUpdates` and read `message.chat.id`.
   - Group: add the bot, send a message, call `getUpdates`; group ids are often **negative** (e.g. `-100123…`).
4. In Beacon: type **Telegram bot**, set endpoint to:

   ```text
   <bot_token>@<chat_id>
   ```

   Example: `7123456789:AAH...xyz@-1001234567890`
5. Save and **Send test**.

**What Beacon sends:** Bot API `sendMessage` — `{ "chat_id", "text": "<summary>", "disable_web_page_preview": true }` (not the full JSON payload).

**Tips:** The endpoint format is validated as `token@chat_id` (last `@` splits token and chat id). Keep the token secret; it is encrypted in Beacon like other endpoints.

---

### Email

1. Configure Symfony Mailer with a real transport in `.env` / production secrets, e.g.:

   ```env
   MAILER_DSN=smtp://user:pass@mail.example:587
   ```

   The default `null://null` accepts messages but does **not** deliver them.
2. In Beacon: type **Email**, set endpoint to the **recipient address** (e.g. `ops@example.com`).
3. Choose categories, save, **Send test**, and check the inbox (and spam).

**What Beacon sends:** email subject = summary; body = summary plus the issue/performance URL when present.

**Tips:** Use a shared ops alias. For richer HTML digests, prefer Slack/Discord/Teams or a generic HTTP bridge into your mail tool.

---

### Generic HTTP webhook

1. Expose an HTTPS endpoint that accepts `POST` with `Content-Type: application/json` (your automation, Zapier, n8n, custom service, etc.).
2. In Beacon: type **Generic HTTP webhook**, paste the URL, choose categories, save, **Send test**.
3. Confirm your service receives the [canonical JSON body](#generic-http-json-body).

**What Beacon sends:** the canonical payload as the JSON body (no Slack/Discord wrapper). Header `User-Agent: symfony-beacon-notifications/1.0`.

**Tips:** Prefer HTTPS. Avoid pointing at internal/metadata IPs from production (SSRF risk). Authenticate your receiver (path secret, shared header validated on your side, etc.).

---

## When alerts fire

| Signal | Notifies? |
|--------|-----------|
| First event of a new issue (matching level) | Yes |
| Another event on an already **unresolved** issue | No |
| Event on a **resolved** or **ignored** issue (reopens to unresolved) | Yes (regression) |
| Transaction with N+1 groups ≥ 1 (and category enabled) | Yes |
| Disabled destination | No |

## Delivery

Outbound delivery runs on the **Messenger `async`** transport (`DeliverNotificationMessage`), with the same retry policy as envelope processing. Envelope **ACK never waits** on external channels.

- Slack / Discord / Teams / Telegram / HTTP use `HttpClient` POSTs.
- Email uses Symfony Mailer (`MAILER_DSN`).

Ensure the Messenger worker is running (`make up` starts it in Docker).

## Generic HTTP JSON body

```json
{
  "event": "issue.new",
  "summary": "New issue: [error] TypeError: …",
  "project": { "id": 1, "uuid": "…", "name": "Acme", "slug": "acme" },
  "issue": {
    "id": 10,
    "uuid": "…",
    "title": "TypeError: …",
    "level": "error",
    "status": "unresolved",
    "culprit": "App\\Service::run"
  },
  "url": "https://beacon.example/projects/…/issues/…",
  "category": "error",
  "test": false
}
```

Other `event` values: `issue.regression`, `performance.n_plus_one`, `test`.

Channel-specific wrappers:

- **Slack**: `{ "text": "<summary>", "beacon": { …payload… } }`
- **Discord**: `{ "content": "<summary>", "embeds": [ … ] }`
- **Teams**: Office 365 MessageCard JSON
- **Telegram**: Bot API `sendMessage` with `chat_id` + `text`
- **Email**: subject/body from `summary` (+ issue URL when present)

## Permissions

| Role | Manage destinations | Read setup guides |
|------|---------------------|-------------------|
| Owner / Admin | Yes | Yes |
| Member | View settings section only (no add/edit/test/delete) | Yes |
