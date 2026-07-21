# Project notifications

Beacon can notify external systems when a project records a **new issue**, an **issue regression**, an **N+1** performance group, selected **issue lifecycle** changes (resolve, reopen, assign, comment, mark duplicate), or a **volume threshold** spike (`volume.threshold`).

Supported channels: **Slack**, **Discord**, **Microsoft Teams**, **Telegram**, **email**, and **generic HTTP**.

See feature specs `specs/009-project-notifications/`, `specs/017-export-webhooks/`, `specs/020-notification-digest/`, and `specs/027-threshold-alerts/`, and the product [ROADMAP](ROADMAP.md).
In the app, open **Project → Settings → Notifications → Setup guides** for the same manuals.

## Configure (any channel)

1. Open **Project → Settings**.
2. Under **Notifications**, click **Add destination** (owner/admin only).
3. Choose the channel **type** and paste the matching **endpoint** (see manuals below).
4. Select alert categories (issue levels, N+1, lifecycle events such as `issue.resolved`, and/or `volume.threshold`).
5. Save, then optionally **Send test** to verify delivery.

Endpoints are **encrypted at rest** and **masked** in the settings list (URLs, emails, and Telegram tokens).

There are **no** global `SLACK_*` / `DISCORD_*` / `TELEGRAM_*` environment variables — each destination stores its own endpoint on the project. Email delivery uses the instance **Mailer** settings (encrypted DSN in the database); see [Email](#email).

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

1. Open **Administration → Mailer** and save a real Symfony Mailer DSN (encrypted at rest), e.g. `smtp://user:pass@mail.example:587`. Optionally set the **From** address.
2. Env `MAILER_DSN` (default `null://null`) is only a bootstrap fallback when no database DSN is stored — it does **not** deliver mail.
3. In Beacon: type **Email**, set endpoint to the **recipient address** (e.g. `ops@example.com`).
4. Choose categories, save, **Send test**, and check the inbox (and spam).

**What Beacon sends:** email subject = summary; body = summary plus the issue/performance URL when present. From address comes from Mailer settings.

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
| Member marks issue **resolved** (`issue.resolved` enabled) | Yes |
| Member **reopens** issue to unresolved (`issue.reopened` enabled) | Yes |
| Member **assigns** / unassigns (`issue.assigned` enabled) | Yes |
| Member **comments** (`issue.commented` enabled) | Yes |
| Member **marks duplicate** (`issue.duplicated` enabled) | Yes |
| Rolling **error/fatal** volume ≥ rule threshold (`volume.threshold` enabled) | Yes (after cooldown) |
| Disabled destination | No |

Lifecycle categories are **opt-in** on each destination (they are not included in the default category set). Subscribe destinations to **`volume.threshold`** to receive spike alerts. Delivery uses the same Messenger `async` path and SSRF guards as other outbound notifications.

## Threshold alerts

Configure rules under **Project → Settings → Threshold alerts** (owners/admins). Each rule defines:

- `errorCount` — fire when at least N matching events are received
- `windowMinutes` — rolling lookback window
- `cooldownMinutes` — silence period after a fire
- Optional `environment` / `releaseVersion` / `label`

Beacon evaluates rules only after a newly ingested **`error`** or **`fatal`** event is persisted (count uses `event.received_at`). Suspended ingest skips evaluation.

Canonical payload `event` is `volume.threshold` and includes `threshold_rule`, `count`, `threshold`, window/cooldown minutes, optional environment/release filters, and the project Settings URL. Quiet hours and digests apply the same as other categories.

## Quiet hours and digests

Per destination (Settings → Notifications → Edit):

| Setting | Behaviour |
|--------|-----------|
| Quiet hours | When enabled, matching alerts are **buffered** in `notification_digest_buffer` instead of immediate send |
| Timezone / start / end | Window evaluated in the destination timezone (`HH:MM`, supports overnight ranges such as 22:00–07:00) |
| Digest on flush | When enabled, `app:notifications:flush-digests` sends **one summary** per destination; when disabled, each held item is dispatched individually after the window |

Schedule the flush (cron / Compose sidecar):

```bash
php bin/console app:notifications:flush-digests
# optional: php bin/console app:notifications:flush-digests --force
```

**Send test** always bypasses quiet hours. There is **no** native PagerDuty connector; use a generic HTTP webhook if you need an incident bridge.

## Project data export

Project **owners** and **admins** can download filtered snapshots (same list filters as the issues index where applicable; max **1,000** rows):

| Format | Path |
|--------|------|
| Issues CSV | `GET /projects/{uuid}/export/issues.csv` |
| Issues JSON | `GET /projects/{uuid}/export/issues.json` |
| Events CSV | `GET /projects/{uuid}/export/events.csv` |
| Events JSON | `GET /projects/{uuid}/export/events.json` |

CSV responses are streamed (`text/csv`). Exports omit raw Envelope payloads and secrets.

## Delivery

Outbound delivery runs on the **Messenger `async`** transport (`DeliverNotificationMessage`), with the same retry policy as envelope processing. Envelope **ACK never waits** on external channels.

- Slack / Discord / Teams / Telegram / HTTP use `HttpClient` POSTs.
- Email uses Symfony Mailer via encrypted instance Mailer settings (env `MAILER_DSN` fallback only).
- Each attempt updates the destination **last delivery** summary and appends a bounded **delivery history** row (`notification_delivery_attempt`). Retention per destination defaults to **20** attempts (`BEACON_NOTIFICATION_DELIVERY_HISTORY_LIMIT`). Recent attempts appear under **Project → Settings → Health**.

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

Other `event` values: `issue.regression`, `issue.resolved`, `issue.reopened`, `issue.assigned`, `issue.commented`, `issue.duplicated`, `performance.n_plus_one`, `volume.threshold`, `test`.

For `issue.assigned`, the payload may include `assignee.previous` / `assignee.current`. For `issue.commented`, a `comment` object (uuid, author, body preview). For `issue.duplicated`, a `canonical_issue` object. For `volume.threshold`, see [Threshold alerts](#threshold-alerts).

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
