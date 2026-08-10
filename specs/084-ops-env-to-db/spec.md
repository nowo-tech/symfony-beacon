# Feature Specification: Ops env knobs → database

**Feature Branch**: `084-ops-env-to-db`

**Created**: 2026-08-03

**Status**: Implemented (as-built; section tabs + FormKit Types — 2026-08-05)

**Input**: User description: "Migrate remaining tunable BEACON_* operator knobs from `.env.dist` (ingest reject query auth, metrics token/require, envelope max bytes, inbound email, allow private notification URLs, anonymous hook resolve) into instance database settings under Administration → Ops defaults, matching the prior retention/quotas migration."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin configures remaining ops knobs in UI (Priority: P1)

As an instance administrator, I configure envelope size limits, ingest query-auth rejection, metrics scrape requirements/token, inbound email, private webhook URL allowance, and anonymous Resolve policy from **Administration → Ops defaults**, without editing server environment files.

**Why this priority**: Completes the ops-in-database principle; operators already use this screen for retention/quotas.

**Independent Test**: Admin opens Ops defaults, changes the new fields (including blank-keep secrets), saves, and runtime behavior reflects the saved values.

**Acceptance Scenarios**:

1. **Given** an admin on Ops defaults, **When** they set envelope max bytes and save, **Then** Envelope/OTLP ingest rejects bodies larger than that limit with HTTP 413.
2. **Given** reject-query-auth enabled (default), **When** a client uses query-string ingest auth, **Then** the request is refused with 401; **When** disabled in Ops defaults, **Then** deprecated query auth is accepted with deprecation headers.
3. **Given** a metrics scrape token saved in Ops defaults, **When** a scraper sends `Authorization: Bearer`, **Then** `/metrics` succeeds; blank token field on save keeps the previous secret.
4. **Given** require-metrics-token enabled and no token stored, **When** anyone calls `/metrics`, **Then** the response is 503.
5. **Given** inbound email enabled with domain and webhook secret, **When** a provider posts with `X-Beacon-Inbound-Secret`, **Then** the webhook is accepted; when disabled, the endpoint returns 404.
6. **Given** allow-private-URLs disabled (default), **When** a notification destination uses a private host, **Then** it is blocked; when enabled, private hosts are allowed.
7. **Given** anonymous Resolve disabled (default), **When** Slack/Teams Resolve lacks a mapped actor, **Then** mutation is refused unless the legacy flag is enabled in Ops defaults.

---

### User Story 2 - Secrets stay out of exports (Priority: P1)

As an administrator exporting instance config, I receive non-secret ops flags and metadata that secrets are configured, never the secret values themselves. Import rejects secret keys and does not overwrite stored secrets.

**Why this priority**: Matches Mailer/Mercure portability rules and avoids credential leakage in backups.

**Independent Test**: Export JSON after setting metrics/inbound secrets; assert secrets absent and `*_configured` flags present; import with secret keys fails.

**Acceptance Scenarios**:

1. **Given** metrics and inbound secrets stored, **When** config is exported, **Then** JSON has no token/secret values and includes configured flags.
2. **Given** an import file containing metrics_token or inbound webhook secret keys, **When** import runs, **Then** it is rejected.

---

### User Story 3 - Env template no longer documents migrated knobs (Priority: P2)

As an operator installing from `.env.dist`, I no longer see (or need) the migrated `BEACON_*` knobs; comments point to Ops defaults. Production docs and upgrade notes explain the move and fail-closed expectations.

**Why this priority**: Prevents dual sources of truth.

**Independent Test**: `.env.dist` and parameters no longer bind those knobs; PRODUCTION/UPGRADING describe Ops defaults.

**Acceptance Scenarios**:

1. **Given** a fresh install from `.env.dist`, **When** the operator opens Ops defaults, **Then** safe defaults match former env defaults (reject query auth on, private URLs off, anonymous resolve off, envelope 2 MiB, inbound off, metrics require off until enabled).

### Edge Cases

- Clearing metrics or inbound secrets via checkbox removes them; blank password fields leave existing values.
- Enabling inbound email without a domain or secret must not accept webhooks (treated as disabled / unauthorized).
- Instance config import accepts prior export versions (v1–v2) and a new version that includes the extra non-secret flags.
- PHPUnit uses seeded Ops defaults (metrics token, inbound secret, allow private URLs) so existing functional suites stay green without env parameters.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Persist the migrated knobs on the singleton instance settings row with defaults matching former `.env.dist` values.
- **FR-002**: Expose them on Administration → Ops defaults (ROLE_ADMIN), including blank-keep / clear for metrics token and inbound webhook secret (encrypted at rest).
- **FR-003**: Runtime consumers read knobs from instance settings (not env parameters).
- **FR-004**: Instance config export/import includes non-secret flags; secrets never exported; secret keys forbidden on import; bump schema version.
- **FR-005**: Remove migrated keys from `.env.dist` and stop binding them in `parameters.yaml` / env-based package overrides.
- **FR-006**: Document upgrade path and production checklist for re-applying former env values in the UI (especially prod metrics require-token).
- **FR-007**: Ops defaults MUST use route-based sections (`/admin/ops-defaults` → `/admin/ops-defaults/{section}` for governance|ingest|metrics|inbound|notifications) with shared product tabs (`shared/_tabs` → UiKit `_tabs`).
- **FR-008**: Ops defaults (and sibling settings Mailer / Mercure / Social credential) Form Types MUST extend FormKit (`FormKitAbstractType` / profile `beacon`) and paint fields via `form/_fields.html.twig` (`077`).

### Key Entities

- **Instance settings**: singleton operator configuration including prior ops defaults plus ingest/security, metrics, inbound email, and hook policy fields.
- **Ops defaults form**: admin UI for those fields (sectioned FormKit Types + UiKit tabs).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can change each migrated knob in the UI and observe the documented runtime effect within one save/redirect cycle.
- **SC-002**: Export JSON never contains metrics token or inbound webhook secret plaintext.
- **SC-003**: Fresh installs do not require the removed `BEACON_*` env vars for correct default behavior.
- **SC-004**: Existing PHPUnit coverage for metrics, inbound email, query auth, SSRF guard, and ops defaults remains green with database-backed settings.

## Assumptions

- Secrets use the same encryption approach as Mailer DSN (`doctrine-encrypt-bundle`).
- Production fail-closed for `/metrics` becomes an Ops checkbox (default off for local DX); upgrade notes tell prod operators to enable it and set a token (replacing former `when@prod` env default).
- Local private webhook URLs are enabled via Ops defaults (or test seed), not `when@dev` parameter overrides.

## Related

- `077-form-type-field-loop` — Form Type field loop.
- `078-form-save-restore-actions` — Save + Restore chrome.
- `081-formkit-uikit-kit-sync` — UiKit tabs/pagination + FormKit pins.
