# Feature Specification: Local Mailpit (dev SMTP catcher)

**Feature Branch**: `066-local-mailpit`  
**Created**: 2026-07-31  
**Status**: Implemented (v0.17.0)  

**Input**: Provide an optional Docker Compose Mailpit service, startable from the Makefile, so operators can test Administration → Mailer, magic-login email, and project email notifications locally without a real SMTP provider. Production stacks must not run Mailpit.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Start Mailpit from Make (Priority: P1)

As a developer, I run `make mailpit` and get a local SMTP catcher with a web UI, without Mailpit being required for every `make up`.

**Why this priority**: Email testing should be opt-in and documented, similar to Vite HMR.

**Independent Test**: With the default stack up, run `make mailpit`; assert container `mailer` is running and the UI responds on the published UI port.

**Acceptance Scenarios**:

1. **Given** the local Compose stack is running, **When** I run `make mailpit`, **Then** Mailpit starts via Compose profile `mail` and Make prints the UI URL and the PHP SMTP DSN `smtp://mailer:1025`.
2. **Given** Mailpit is not started, **When** I run `make up`, **Then** the Mailpit container is not required / not started (profile not enabled).
3. **Given** Mailpit is running, **When** I run `make down`, **Then** the Mailpit service is stopped with the stack (profile included in `down`).

### User Story 2 - Deliver test mail into Mailpit (Priority: P1)

As an instance admin, I save `smtp://mailer:1025` under Administration → Mailer and use **Send sample email**; the message appears in the Mailpit UI.

**Acceptance Scenarios**:

1. **Given** Mailpit is up and I saved DSN `smtp://mailer:1025` (encrypted) in Admin → Mailer, **When** I send a sample email, **Then** Mailpit shows the message.
2. **Given** only env `MAILER_DSN=null://null` (no DB DSN), **When** I attempt sample/magic flows that require a deliverable DB DSN, **Then** behaviour remains gated per `034` (sample may fail / magic links hidden).

### User Story 3 - Production exclusion (Priority: P1)

As an operator deploying with `compose.prod.yaml` or the prod image, I never get a Mailpit container as part of that stack.

**Acceptance Scenarios**:

1. **Given** `compose.prod.yaml`, **When** I inspect services, **Then** there is no Mailpit / `mailer` catcher service.
2. **Given** docs for production Mailer, **When** I follow them, **Then** I am directed to a real SMTP/API DSN, not Mailpit.

## Requirements *(mandatory)*

- **FR-001**: Define Mailpit in `compose.override.yaml` as service `mailer` (Symfony Flex convention) with Compose profile `mail`.
- **FR-002**: Publish host ports via `MAILPIT_UI_PORT` (default 18025) and `MAILPIT_SMTP_PORT` (default 1026); container ports remain 8025 / 1025.
- **FR-003**: Makefile targets `mailpit` and `mailpit-logs`; `down` includes `--profile mail`; `print-urls` / `up` hint or show Mailpit when running.
- **FR-004**: Document in `docs/ops/MAILPIT.md`, cross-link from INSTALL / NOTIFICATIONS / PRODUCTION / README / CHANGELOG; `.env.dist` comments for ports + DSN.
- **FR-005**: Spec artifact `066-local-mailpit`; ROADMAP note that local Mailpit is available for Mailer testing and is not a production dependency.
- **FR-006**: Production Compose / prod image MUST NOT start Mailpit.

## Success Criteria

- **SC-001**: `make mailpit` brings up Mailpit; UI reachable on configured host port.
- **SC-002**: Documented DSN `smtp://mailer:1025` works from PHP to Mailpit for Admin sample send.
- **SC-003**: `compose.prod.yaml` has no Mailpit service.

## Assumptions

- Service hostname stays `mailer` so Flex recipe markers and Symfony docs remain familiar.
- Encrypted instance Mailer DSN (`034`) remains the primary configuration path; env `MAILER_DSN` is bootstrap/fallback only.
- English documentation only (project convention).

## Out of Scope

- Shipping Mailpit in production or staging Compose files.
- Auto-writing `smtp://mailer:1025` into `instance_settings` on seed (operators configure Admin → Mailer intentionally).
- Replacing real SMTP integration tests in CI with Mailpit (optional later).
