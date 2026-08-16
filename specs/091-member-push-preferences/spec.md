# Feature Specification: Member Push Notification Preferences

**Feature Branch**: `091-member-push-preferences`

**Created**: 2026-08-12

**Status**: Implemented (shipped **v1.8.0**; LiveComponent DI polish **v1.8.1**; QA/CI follow-ups **v1.8.2**; realtime/prefs batching **v1.11.0** / `095`)

**Input**: User description: "Per-user push notifications for interesting issue events (new, regression, resolve, reopen, assign, comment, and related), configurable at account level and per project. Defaults all on (opt-out): members manually turn off noisy events/projects. Scope can be all project issues or only when the member is involved. Delivery requires account-level alerts enabled AND the specific project enabled, and only for projects the member can access."

## Product policy: opt-out by default

Member push/live alerts are **on for everything by default**. The member reduces noise **manually** (turn off master, a project, an event type, or switch scope to involved-only). The product MUST NOT ship silent/opt-in defaults for these prefs: absence of a saved preference row means **on** (and scope = all issues / project enabled), not off.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Account master + event defaults (Priority: P1)

As a signed-in member, I open Account notification preferences and see a master switch for member push/live alerts plus toggles for each event type (new issue, regression, resolve, reopen, assign, comment, and other member-relevant events listed in Requirements). Everything is **on by default**. I can turn the master off to stop all member push/live alerts without losing my saved event choices.

**Why this priority**: Without an account-level gate and event matrix, project overrides and delivery rules cannot be evaluated.

**Independent Test**: Change account prefs only; verify delivery starts/stops for a project that remains enabled with default event settings.

**Acceptance Scenarios**:

1. **Given** a new account (or prefs never customized), **When** the member views preferences, **Then** master alerts are on and every event type is on.
2. **Given** master alerts off, **When** a matching issue event occurs on an enabled project, **Then** the member receives no Mercure toast and no Web Push for that event.
3. **Given** master on and only “assign” off, **When** an issue is assigned and the member would otherwise qualify, **Then** no member push/live alert is sent for assign; other enabled events still deliver.

---

### User Story 2 - Per-project enablement (AND with account) (Priority: P1)

As a member of one or more projects (including **viewer**), I can enable or disable member push/live alerts **per project** from **Account → Display → Notifications** without needing project Settings admin rights. Delivery only happens when **account master is on** and **that project is on**. I only see projects I am allowed to access.

**Why this priority**: Matches the required two-step gate (user then project) and avoids noise from memberships the member does not care about.

**Independent Test**: Same account prefs; flip one project off and leave another on; only the enabled project delivers. Viewer can save a project override from Account while Settings remains forbidden.

**Acceptance Scenarios**:

1. **Given** account master on and project A on / project B off, **When** a matching event happens on A and B, **Then** the member is notified only for A.
2. **Given** account master off and project A on, **When** an event happens on A, **Then** no member push/live alert is sent.
3. **Given** the member loses access to a project, **When** prefs are shown or an event fires, **Then** that project no longer appears as configurable and no alerts are delivered for it.
4. **Given** a newly accessible project, **When** the member has not customized it yet, **Then** that project defaults to **on** (subject to account master).
5. **Given** a project **viewer** (can view issues, cannot open Project Settings), **When** they open Account notifications and save a per-project override, **Then** the override persists and Settings still returns forbidden.

---

### User Story 3 - Involvement scope (Priority: P1)

As a member, for each event type (account defaults, overridable per project), I choose whether alerts fire for **all issues in the project** or **only when I am involved** in that issue. Defaults: events on, and scope is **all project issues** unless I choose “involved only”.

**Why this priority**: Core product request; reduces noise without removing high-signal involvement alerts.

**Independent Test**: Set “resolve” to involved-only on a project; resolve an issue where the member is not involved vs is assignee/mentioned; only the involved case notifies.

**Acceptance Scenarios**:

1. **Given** scope “all issues” for resolve on project A, **When** any issue on A is resolved, **Then** the member is notified (if account + project gates pass).
2. **Given** scope “involved only” for assign on project A, **When** someone else is assigned and the member is not involved, **Then** the member is not notified.
3. **Given** scope “involved only”, **When** the member is the new assignee (or otherwise involved per Key Entities), **Then** the member is notified.

---

### User Story 4 - Richer event coverage than new-issue only (Priority: P2)

As a member with alerts enabled, I receive member push/live alerts for interesting triage events—not only new issues: at least new issue, regression, resolve, reopen, assign, and comment (plus mention when applicable). Channels remain the existing member channels (live toast while the app is open; Web Push when opted in / configured).

**Why this priority**: Expands today’s new-issue-only Mercure/Web Push path to match user value; depends on P1 gates.

**Independent Test**: With defaults, trigger each event type once and observe a member alert when gates and scope match.

**Acceptance Scenarios**:

1. **Given** defaults and a subscribed open session, **When** a new issue is ingested, **Then** a live toast can appear.
2. **Given** defaults and Web Push opted in, **When** an issue is resolved on an enabled project, **Then** a Web Push can be delivered (instance VAPID configured).
3. **Given** “comment” off at account level, **When** a comment is added, **Then** no member push/live alert for comment.

---

### User Story 5 - Per-project overrides of event/scope (Priority: P2)

As a member, on a project I can override account event toggles and involvement scope for that project only (still requiring account master + project enable). Clearing overrides returns to account defaults for that project.

**Why this priority**: Fine-tuning without changing global prefs; secondary to the AND gate.

**Independent Test**: Account has comment on / all-issues; project override sets comment involved-only; verify only that project respects the override.

**Acceptance Scenarios**:

1. **Given** account “comment = on, all issues” and project override “comment = involved only”, **When** a comment lands on an uninvolved issue, **Then** no alert for that project; other projects still follow account defaults.
2. **Given** a project override, **When** the member resets project event prefs to inherit, **Then** account defaults apply again for that project.

---

### Edge Cases

- Member has account master on but zero projects enabled → no member push/live alerts.
- Member enables a project but lacks (or loses) project access → no delivery; prefs UI hides or shows read-only inaccessible state without leaking other projects’ data.
- Actor is the member themselves (e.g. I resolve my own issue) → still notify if prefs match (do not auto-suppress self-actions in v1).
- Mercure hub disabled / Web Push not configured / member has preference off **or** no `push_subscription` row → live and push channels degrade independently; preference evaluation still runs but undeliverable channels are skipped without failing ingest.
- Quiet hours / digest apply only to **project outbound destinations** (Slack, etc.), not to these member prefs, unless a later spec says otherwise.
- High fan-out (many members, many events) must not block Envelope ACK; delivery stays asynchronous / non-blocking for ingest.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide account-level member alert preferences with a master enable switch and per-event enable switches. Defaults: master **on**, every supported event **on**. Missing preference data MUST be treated as **on** (opt-out model).
- **FR-002**: System MUST let a member enable/disable member alerts **per accessible project**. Defaults: each accessible project **on**. Missing per-project preference MUST be treated as **on**. Delivery MUST require **account master on AND project on**.
- **FR-003**: System MUST support involvement scope per event: **all project issues** or **involved only**. Default scope: **all project issues**. Missing scope MUST mean **all project issues**. Account defaults MAY be overridden per project.
- **FR-004**: Supported member alert events MUST include at least: **new issue**, **regression**, **resolve**, **reopen**, **assign**, **comment**. Mentions SHOULD map to the comment/mention path so @mentions can alert when comment (or a dedicated mention toggle) is enabled—see Assumptions.
- **FR-005**: Member alerts MUST only be considered for projects the user can access (membership or equivalent project access that already grants issue visibility). No alerts for projects outside that set.
- **FR-006**: When evaluating delivery, the system MUST apply gates in order: account master → project enabled → event enabled (project override or account default) → involvement scope → channel availability (live hub / Web Push preference + device subscription).
- **FR-007**: Live in-app alerts and Web Push MUST honor the same preference evaluation. Existing instance gates remain: Mercure admin enablement for live; VAPID + `pushNotificationsEnabled` (default **on** for new users) + a stored `push_subscription` row for push. Account preference alone MUST NOT imply a device endpoint exists.
- **FR-007a** (2026-08-16): New users MUST have `pushNotificationsEnabled = true` by default (`` `user` `` column default true; existing rows unchanged). Browser Notification permission and service-worker subscribe (`POST /account/push/subscribe`) remain required before Web Push delivery.
- **FR-008**: Preference UI MUST exist under **Account** (global + per accessible project). An optional Project Settings shortcut is allowed for members who can open Settings; it MUST NOT be the only way to edit per-project overrides.
- **FR-009**: Changing preferences MUST take effect for subsequent events without requiring re-login (short cache TTL acceptable if documented).
- **FR-010**: Preference evaluation MUST NOT block or fail Envelope ingest acknowledgment.
- **FR-011**: Project outbound notification destinations (Slack, Discord, Teams, Telegram, email-to-channel, HTTP) remain independently configured by project admins and are **out of scope** for this member preference matrix.
- **FR-012**: Users MUST NOT configure another user’s member alert preferences.
- **FR-013**: Saving **own** per-project member alert preferences MUST require only **project access** (viewer or higher / equivalent issue visibility). It MUST NOT require project Settings-admin grants (`members.manage`, `settings.manage`, notification destinations admin, etc.).

### Key Entities

- **Account member alert preferences**: Master enable; per-event enable; optional default involvement scope per event.
- **Project member alert preferences**: Per user+project enable; optional overrides for per-event enable and involvement scope; inherits account defaults when unset.
- **Involvement**: For an issue, the member counts as involved when they are the **current assignee**, and/or have been **@mentioned** on that issue (existing mention records), and/or are otherwise listed by product rule documented in Assumptions for v1.
- **Member alert channels**: Live toast (when Mercure enabled) and Web Push (when configured, preference on, and a `push_subscription` exists)—distinct from project webhook destinations.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With defaults unchanged, a member belonging to a project receives member alerts for new issue, resolve, reopen, assign, and comment when those events occur (channels permitting) in manual or automated acceptance checks.
- **SC-002**: Turning account master off stops 100% of member push/live alerts for that user in acceptance tests, even if all projects remain enabled.
- **SC-003**: Turning a single project off stops alerts for that project while another enabled project still delivers in the same test run.
- **SC-004**: “Involved only” suppresses alerts for non-involved issues and still delivers when the member is assignee or mentioned, verified per event type sampled in tests.
- **SC-005**: A member can open Account prefs and complete a full configure-and-save pass (master, events, one project override) in under 3 minutes without admin help.
- **SC-006**: Envelope ingest still acknowledges successfully when preference evaluation or downstream alert publish fails (no ingest regression).
- **SC-007**: A project **viewer** can save their own per-project override from Account in automated acceptance tests while Project Settings remains forbidden for that role.

## Assumptions

- “Push” in the product sense here means **member live (Mercure) + Web Push**, not Slack/Discord project destinations.
- **Email** notify from `040-issue-mentions-notify` (assign/mention mail) stays on its current behavior in v1 unless product later unifies email under this matrix; this spec does not remove those emails.
- Supported events align with existing issue lifecycle hooks already used for project destinations where possible (new, regression, resolved, reopened, assigned, comment); duplicate/N+1/volume are **out of scope for member push v1** unless added in clarify.
- **Opt-out philosophy**: everything interesting starts enabled; the member disconnects noisy items by hand. No “start quiet / opt in per event” default.
- Default involvement scope is **all issues** (not involved-only) so behavior stays close to today’s “all members get new-issue alerts” once events expand.
- Self-actions are not suppressed in v1.
- New projects the member gains access to default to **enabled** (still opt-out).
- Instance Mercure / VAPID remain operator prerequisites for each channel; prefs never bypass those.
- **Authorization**: own prefs are personal data for the signed-in user; project access (viewer+) is enough to edit overrides for that project. Project Settings UI remains gated for secrets/DSN/destinations; the Account entry point covers viewers and members.
- Legal/privacy: Web Push still requires an explicit browser permission + device subscription (`push_subscription`); Account prefs copy stays in English; operators should keep Privacy/Terms available for self-hosted SaaS surfaces. `pushNotificationsEnabled` defaults **on** for new accounts but does not itself create a device endpoint.
- UI copy and docs remain English (`lang="en"`).

## Out of scope

- Replacing or reconfiguring project admin notification destinations.
- Per-device preference matrices beyond existing Web Push subscribe/unsubscribe.
- Native mobile push outside the existing PWA Web Push path.
- Digest/batching of member push/live alerts (immediate delivery).
- Admin impersonation of member prefs.
- Granting viewers access to the full Project Settings surface solely to edit member alerts (Account covers that).

## Shipped releases

| Version | Notes |
|---------|--------|
| **v1.8.0** | Feature cut: Account matrix, Mercure `/users/{uuid}/member-alerts`, Web Push filter, viewer `requireAccess`, migrations |
| **v1.8.1** | Constructor-injected LiveComponents; restore `seedTestOpsDefaults` helper name |
| **v1.8.2** | PHPStan typing clean-up; Rector skips that preserve PHPStan aliases / Live method injection; `make rector-fix` → CS Fixer |

## Amendment (FormKit preference forms, 2026-08-13)

`MemberAlertPreferencesType` / `MemberProjectAlertPreferencesType` extend host `FormKitAbstractType` (profile `beacon`). Labels/help stay under `preferences.*` in the `form` catalogue (shared with Twig). Nested event matrix is built outside FormKit merge; the `events` compound sets `translation_domain: form` once so children inherit. Twig: `form_row` / `_fields` for residual fields (`077`). Canonical: `081` FR-003c.

## Amendment (Live `pref-switch` chrome, 2026-08-13)

Account / project member-alert LiveComponent Twigs intentionally use `form_widget` for master / push / event / scope toggles so `pref-switch` + Live `data-model` / `role="switch"` stay intact. Default theme `checkbox_row` MUST NOT replace that chrome until a dedicated switch theme block exists. Listed as a standing exception in `077` / `081` Twig consolidation amendments.

## Amendment (Browser push preference default on, 2026-08-16)

`UserUiPreferences.pushNotificationsEnabled` defaults to **true** for new users (Doctrine column default `1` on table `` `user` ``; migration `Version20260816120000` changes default only — no mass update of existing rows). Product copy: preference on by default; browser still prompts for Notification permission; `issue-realtime` registers `push_subscription` when the member visits a page that mounts the controller with VAPID configured. Dogfood `make beacon-test` counts `push_subscription` rows (not the preference flag) when warning about missing Web Push.
