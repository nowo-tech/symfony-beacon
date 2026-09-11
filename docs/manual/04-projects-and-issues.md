# Projects and issues

Each project is a telemetry namespace: ingest issues (errors and related events), inspect performance and analytics, track releases, and configure access, alerts, and data lifecycle.

Open a project from the [dashboard](02-dashboard.md). Project chrome exposes tabs: **Issues**, **Performance**, **Analytics**, **Releases**, and **Settings**.

## Issue list

**What it is.** The default project surface: filterable table of issues with saved views, severity/status/assignee filters, environment and release dimensions, and occurrence counts over time.

**What it contributes.** Day-to-day triage — find regressions by release, environment, or tag without leaving the project. Saved views encode recurring filter sets for the team.

![Issue list](images/project-issues.png)

## Analytics

**What it is.** Aggregate charts and breakdowns for the project’s issue traffic.

**What it contributes.** Trend and volume context that the issue table alone does not show (spikes, distributions, comparison windows).

![Analytics](images/project-analytics.png)

## Performance

**What it is.** Performance-oriented views for the project (latency / trace-style signals depending on what clients send).

**What it contributes.** Complements error issues with operational performance signals from the same project DSN.

![Performance](images/project-performance.png)

## Releases

**What it is.** Release-centric listing for versions reported with events.

**What it contributes.** Ties “what shipped” to “what broke,” and pairs with the dashboard [New in release](02-dashboard.md#new-in-release) panel.

![Releases](images/project-releases.png)

## Issue detail

**What it is.** Deep view for one issue: highlights, stack frames, tags, frequency, and actions. Tabs include **Overview**, **Similar issues**, and **Activity**. Companion shot continues the same viewport.

**What it contributes.** Debugging context in one place — severity, environment, stack, and event counts — plus **Copy for AI** (scrubbed `beacon-ai-export/v1` payload). See [AI-EXPORT.md](../product/AI-EXPORT.md).

![Issue detail](images/issue-detail.png)

![Issue detail (continued)](images/issue-detail-2.png)

### Similar issues

**What it is.** Candidates that look related to the current issue (grouping / similarity helpers).

**What it contributes.** Speeds merge/dedup decisions and avoids treating the same root cause as many unrelated tickets.

![Similar issues](images/issue-similar.png)

### Issue history (Activity)

**What it is.** Timeline of changes and activity on the issue (status, assignment, comments, system events).

**What it contributes.** Audit trail for how the issue was handled over time.

![Issue history](images/issue-history.png)

## Project settings

Settings are split into focused sections so operators can change governance, access, alerts, data, or destructive actions without mixing concerns.

| Section | Purpose |
|---------|---------|
| General | Name, description, and governance (retention, rate limits, quotas) |
| Access | API keys / DSN, members, group links, shares |
| Alerts | Destinations, thresholds, health |
| Data | Import / export |
| Danger | Suspend ingest, clear data, or delete the project |

### General (governance)

**What it is.** Project identity plus per-project retention, ingest rate limits, and daily/monthly quotas (empty inherits server defaults).

**What it contributes.** Keeps storage and ingest within operator policy. Banners (for example suspended ingest) surface here when governance blocks new events while keeping existing data readable.

![Settings — general](images/project-settings-general.png)

### Access

**What it is.** Who and what can talk to the project: members, groups, client keys / DSN material, and share links as configured. Companion shot continues the same viewport.

**What it contributes.** Connects SDKs (`nowo-tech/beacon-bundle` / Envelope ingest) and controls human membership without opening the admin hub.

![Settings — access](images/project-settings-access.png)

![Settings — access (continued)](images/project-settings-access-2.png)

### Alerts

**What it is.** Alert destinations, threshold rules, and delivery health for the project. Companion shot continues the same viewport.

**What it contributes.** Turns issue spikes into outbound notifications (webhooks, chat, email, etc. depending on configured destinations). Product deep dive: [NOTIFICATIONS.md](../product/NOTIFICATIONS.md).

![Settings — alerts](images/project-settings-alerts.png)

![Settings — alerts (continued)](images/project-settings-alerts-2.png)

#### New notification destination

**What it is.** Form / modal to add a destination (channel + credentials / URL).

**What it contributes.** Extends where alerts can be delivered without code changes.

![New notification destination](images/project-notifications-new.png)

#### Notifications help

**What it is.** In-product help for destination types and payload expectations. Companion shot continues the same viewport.

**What it contributes.** Operator-facing reference next to the form so docs are not the only place to learn channel requirements.

![Notifications help](images/project-notifications-help.png)

![Notifications help (continued)](images/project-notifications-help-2.png)

#### New threshold rule

**What it is.** Modal on Settings → Alerts to create a threshold (when to fire).

**What it contributes.** Defines *when* destinations should fire (counts, windows, levels), separate from *where* they deliver.

![New threshold rule](images/project-threshold-rules-new.png)

### Data

**What it is.** Import / export tools for project data.

**What it contributes.** Portability and backup-style transfer of project-scoped content (distinct from full instance Site Backup under Administration).

![Settings — data](images/project-settings-data.png)

### Danger zone

**What it is.** Irreversible or high-impact project actions (clear data, delete project, related destructive controls).

**What it contributes.** Explicit, hard-to-miss place for destructive operations so they are not mixed with routine settings.

![Settings — danger](images/project-settings-danger.png)
