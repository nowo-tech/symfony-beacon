# Feature Specification: Project Notifications

**Feature Branch**: `009-project-notifications`

**Created**: 2026-07-20

**Status**: Completed (as-built; channel manuals + SSRF guard — 2026-07-21)

**Input**: User description: "Add the option to send notifications for errors, warnings, N+1, … to different destinations (Slack, …); must be configurable per project."

## Clarifications

### Session 2026-07-20

- **Q1 (destination types for v1)**: Originally Slack Incoming Webhook **and** generic HTTP webhook. **As-built expansion**: also Discord, Microsoft Teams, Telegram (`bot_token@chat_id`), and email (instance Mailer DSN; see `034-encrypted-mailer-dsn`).
- **Q2 (issue occurrence rule)**: Notify on **first occurrence** of a new issue **and** on **regression** (issue was `resolved` or `ignored` and becomes active again). Do **not** notify on every duplicate event for an already-open issue.
- **Implementation note (ingest)**: Matching events reopen both **`resolved` and `ignored`** issues to **unresolved**, and notify on new issue + regression only.

### Session 2026-07-21

- **Q3 (operator manuals)**: In-app setup guides at `/projects/{uuid}/notifications/help` (and `docs/NOTIFICATIONS.md`) document how to connect Slack, Discord, Teams, Telegram, email, and generic HTTP.
- **Q4 (SSRF)**: Production blocks private/link-local/metadata URLs for Slack/Discord/Teams/HTTP destinations; Telegram uses Bot API host constructed by Beacon; email is Mailer-only (encrypted instance DSN via `034`).

### Session 2026-07-21 (Mailer settings)

- **Q5 (Mailer secret location)**: Email transport DSN is stored encrypted in `instance_settings` (Administration → Mailer). Env `MAILER_DSN` remains bootstrap/fallback only (`null://null` by default). See `034-encrypted-mailer-dsn`.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure notification destinations per project (Priority: P1)

As a project owner or admin, I can add, edit, enable/disable, and remove notification destinations for my project so each project can route alerts to its own team channels.

**Why this priority**: Without per-project configuration, operators cannot safely connect team chat or webhooks; this is the control surface for the whole feature.

**Independent Test**: As a project admin, open the project notification settings, create a destination with a webhook URL and event filters, save, then disable and delete it; assert persistence and permission checks.

**Acceptance Scenarios**:

1. **Given** I am a project owner or admin, **When** I open the project’s notification settings, **Then** I see the list of destinations (empty or existing) and can add a new one.
2. **Given** I submit a valid destination (type, destination URL/endpoint, enabled flag, and selected event categories), **When** it is saved, **Then** it appears in the list and is associated only with that project.
3. **Given** I am a project member without manage rights, **When** I try to change notification settings, **Then** the system denies the change.
4. **Given** an existing destination, **When** I disable it, **Then** it remains stored but stops receiving notifications until re-enabled.
5. **Given** an existing destination, **When** I delete it, **Then** it no longer appears and no further notifications are sent to it.
6. **Given** I choose Slack, Discord, Teams, Telegram, email, or generic HTTP, **When** I save with a valid endpoint for that type, **Then** the destination is stored with that type.
7. **Given** Settings → Notifications, **When** I open **Setup guides**, **Then** I see step-by-step manuals for each channel (readable by members; manage still admin-only).

---

### User Story 2 - Receive alerts for matching telemetry (Priority: P1)

As a team watching a chat channel or webhook consumer, I receive a clear notification when the project ingests matching problems (errors/warnings and related categories, and N+1 detections) according to that destination’s filters.

**Why this priority**: Configuration alone has no value until alerts actually reach external destinations after ingest.

**Independent Test**: Configure a destination with known filters; ingest matching and non-matching envelopes; assert only matching cases produce outbound notification attempts for that project’s enabled destinations.

**Acceptance Scenarios**:

1. **Given** an enabled destination that includes error-level issue events, **When** a **new** issue at a selected level is recorded for the project, **Then** the system attempts to notify that destination with project name, issue title/level, and a link to the issue in Beacon.
2. **Given** an enabled destination that includes warning (or other selected levels), **When** a matching **new** issue is recorded, **Then** a notification is attempted; **When** a non-selected level arrives, **Then** that destination is not notified for that event.
3. **Given** an existing unresolved issue and an enabled matching destination, **When** another event with the same fingerprint is ingested, **Then** no additional issue notification is sent for that destination (duplicates are silent).
4. **Given** an issue that was `resolved` or `ignored` and an enabled matching destination, **When** a new event reopens it (regression), **Then** a notification is attempted.
5. **Given** an enabled destination that includes N+1 detections, **When** a transaction with N+1 candidates is stored for the project, **Then** a notification is attempted with enough context to investigate (transaction name/path and count).
6. **Given** no enabled destinations (or none matching the filters), **When** matching telemetry is ingested, **Then** ingest still succeeds and no outbound notification is required.
7. **Given** a destination is disabled, **When** matching telemetry is ingested, **Then** that destination is not notified.

---

### User Story 3 - Keep ingest fast and resilient when destinations fail (Priority: P1)

As an operator, envelope ingest continues to acknowledge quickly even if Slack or other destinations are slow or down; failed deliveries are retried without blocking ingest.

**Why this priority**: Outbound HTTP must not violate the product’s efficient-ingest guarantee or drop client ACK behaviour.

**Independent Test**: Simulate a failing destination while ingesting; assert ingest ACK succeeds and a retryable delivery attempt is recorded/queued independently of the HTTP response to the SDK.

**Acceptance Scenarios**:

1. **Given** a slow or unavailable destination, **When** an envelope is ingested, **Then** the client still receives a successful acknowledge without waiting on the destination.
2. **Given** a transient delivery failure, **When** retries are exhausted per platform policy, **Then** the failure is visible to operators in application logs (or equivalent operational signal) without corrupting stored issues/events.
3. **Given** one destination fails and another succeeds for the same project, **When** notifications run, **Then** the successful destination still receives its message.

---

### User Story 4 - Send a test notification (Priority: P2)

As a project admin, I can send a test notification to a configured destination so I can verify the webhook URL and formatting before relying on real incidents.

**Why this priority**: Reduces misconfiguration; valuable but not required for first useful alert path.

**Independent Test**: Create a destination, trigger “Send test”, assert an outbound attempt occurs with a clearly marked test payload.

**Acceptance Scenarios**:

1. **Given** a saved destination, **When** I choose send test, **Then** the system attempts delivery and reports success or failure in the UI without requiring a real error ingest.

---

### Edge Cases

- Invalid or empty webhook URL must be rejected at save time with a clear validation message.
- Private / link-local / metadata HTTP(S) endpoints MUST be rejected in production (SSRF guard); may be allowed in `dev`/`test` or via `BEACON_NOTIFICATIONS_ALLOW_PRIVATE_URLS=1`.
- Duplicate fingerprints for an already-open (`unresolved`) issue must **not** trigger another issue notification.
- Regression (from `resolved`/`ignored` back to active) **must** trigger an issue notification when filters match.
- Very high ingest volume must not create unbounded synchronous work on the ingest path; outbound work stays asynchronous.
- Secrets in destination URLs (tokens in query strings) must not be shown in full in ordinary UI lists (mask or show last characters only); endpoints are encrypted at rest.
- Deleting a project must remove or cascade its notification destinations so orphaned secrets do not remain.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow project owners and admins to manage notification destinations scoped to a single project.
- **FR-002**: Each destination MUST support: **Slack**, **Discord**, **Microsoft Teams**, **Telegram**, **email**, and **generic HTTP** webhook.
- **FR-003**: Each destination MUST store: display name/label, type, endpoint credentials/URL, enabled flag, and selected alert categories.
- **FR-004**: Alert categories MUST include issue severity filters covering at least `error` and `warning` (and allow selecting other known levels the product already uses, such as `fatal` / `info` / `debug`), plus an N+1 performance category.
- **FR-005**: System MUST attempt notifications only for enabled destinations whose selected categories match the ingested signal.
- **FR-006**: Issue-oriented notifications MUST include project identity, severity/level, issue title (or equivalent summary), and a deep link into the Beacon UI for that issue when available.
- **FR-007**: N+1-oriented notifications MUST include project identity, a transaction summary, N+1 count (or equivalent), and a deep link into the related performance view when available.
- **FR-008**: Outbound notification delivery MUST run asynchronously after ingest acknowledgment so destination latency cannot block Envelope ACK.
- **FR-009**: Delivery failures MUST be retried with bounded attempts; permanent misconfiguration must not break ingest or issue persistence.
- **FR-010**: Members without project manage rights MUST NOT create, edit, disable, enable, test, or delete destinations.
- **FR-011**: System MUST provide a way to send a test notification for a saved destination.
- **FR-012**: Destination secrets MUST NOT be exposed in full in list views or ordinary page HTML where avoidable (masked display); MUST be encryptable at rest.
- **FR-013**: Automated tests MUST cover permission rules, filter matching (issue levels and N+1), first-occurrence vs duplicate silence, regression notify, and that ingest ACK does not depend on destination success.
- **FR-014**: For issue signals, the system MUST notify on **new issue creation** and on **regression** (status was `resolved` or `ignored` and the issue becomes active again). The system MUST NOT notify on every subsequent event for an already-unresolved issue.
- **FR-015**: System MUST provide English (and Spanish UI) setup guides for connecting each destination type (`docs/NOTIFICATIONS.md` + in-app help).
- **FR-016**: Outbound HTTP destinations MUST be validated against SSRF rules before save and before delivery (except Telegram Bot API URLs constructed by the server).

### Key Entities

- **Notification destination**: A per-project outbound channel (type, endpoint/secret, label, enabled, selected alert categories).
- **Alert category**: A selectable filter such as issue levels (`fatal`, `error`, `warning`, …) or performance signals (`n_plus_one`).
- **Notification attempt**: A logical delivery of one alert to one destination after a matching ingest signal (for async processing and retries).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A project admin can add an enabled destination with filters in under 3 minutes using the setup guides without reading source code.
- **SC-002**: After a matching new problem (or regression) is ingested, a correctly configured destination receives (or is attempted) a notification without the Envelope client waiting on that delivery.
- **SC-003**: Non-matching levels, disabled destinations, and duplicate events on open issues produce no notification attempt for that destination.
- **SC-004**: A member without manage rights cannot change destinations (denied in UI and server-side checks) but can open setup guides.
- **SC-005**: PHPUnit coverage for configuration permissions, filter matching, occurrence rules, async/non-blocking ingest, and SSRF rejection stays green in CI.

## Assumptions

- Self-hosted operators paste webhook URLs or `bot_token@chat_id`; no global Slack/Discord/Telegram env vars.
- Notification UI/docs copy is English in docs; Twig UI is i18n (`en` / `es`).
- Multiple destinations per project are supported.
- Email requires a configured instance Mailer DSN under **Administration → Mailer** (encrypted). Env `MAILER_DSN` is fallback only; default `null://null` does not deliver. See `034-encrypted-mailer-dsn`.

## Out of Scope

- Native mobile push notifications.
- Third-party OAuth “Add to Slack” marketplace apps.
- On-call paging products (PagerDuty/Opsgenie) as first-class types.
- Changing N+1 detection thresholds themselves (still product-wide defaults).
- Per-user personal notification preferences (this feature is project-scoped team destinations only).
- Hourly digests / rate-limit windows beyond the first-occurrence + regression rule.
