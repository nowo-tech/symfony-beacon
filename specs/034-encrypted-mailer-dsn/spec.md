# Feature Specification: Encrypted Instance Mailer DSN

**Feature Branch**: `034-encrypted-mailer-dsn`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: Store the Symfony Mailer DSN encrypted in the database (instance settings), not as the primary operator secret in `.env`. Prefer Halite / `nowo-tech/doctrine-encrypt-bundle` like API secrets and webhook URLs. Admin UI under Administration; runtime mailer for magic login and email notification destinations.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure Mailer in Administration (Priority: P1)

As an instance admin, I open Administration → Mailer and save an SMTP (or other) DSN so transactional email works without putting credentials in `.env`.

**Why this priority**: Operators need a secure, UI-managed transport for magic login and email alerts.

**Independent Test**: As `ROLE_ADMIN`, open `/settings/mailer`, submit a DSN + From address, reload; assert masked DSN shown and ciphertext at rest.

**Acceptance Scenarios**:

1. **Given** I am an instance admin, **When** I open `/settings/mailer`, **Then** I can enter a Mailer DSN and optional From address and save.
2. **Given** a DSN is saved, **When** I view the page again, **Then** I see a masked DSN (not the full secret) and confirmation that the database DSN is active.
3. **Given** I am not an admin, **When** I open `/settings/mailer`, **Then** access is denied (403).

### User Story 2 - Encryption at rest (Priority: P1)

As an operator, the Mailer DSN is stored encrypted in the database using the same Halite profile as other secrets.

**Acceptance Scenarios**:

1. **Given** a plaintext DSN was saved, **When** I read `instance_settings.mailer_dsn` from SQL, **Then** the value is ciphertext (not the plaintext; ends with `<ENC>`).
2. **Given** the entity is reloaded via Doctrine, **When** I read `getMailerDsn()`, **Then** I receive the original plaintext.

### User Story 3 - Runtime delivery uses DB DSN (Priority: P1)

As the system, magic-login emails and email notification destinations use the configured instance Mailer (DB first).

**Acceptance Scenarios**:

1. **Given** a database DSN is set, **When** outbound mail is sent, **Then** the effective transport DSN is the database value.
2. **Given** no database DSN is set, **When** outbound mail is sent, **Then** the system falls back to env `MAILER_DSN` (typically `null://null`).
3. **Given** a From address is configured, **When** magic login or notification email is sent, **Then** the message From uses that address (default `beacon@localhost` when empty).

### User Story 4 - Clear stored DSN (Priority: P2)

As an admin, I can clear the stored DSN so the instance returns to the env fallback.

**Acceptance Scenarios**:

1. **Given** a database DSN exists, **When** I check “Clear stored DSN” and save, **Then** the DB field is null and the UI shows env-fallback status.

### Edge Cases

- Blank DSN field on save keeps the existing encrypted value (does not wipe accidentally).
- Clearing DSN and submitting a new DSN in the same request: clear wins (DSN removed).
- Invalid From email is rejected by form validation.
- FrankenPHP / Messenger workers: mailer transport must not permanently cache a stale DSN across resets (`ResetInterface`).

## Requirements *(mandatory)*

- **FR-001**: Singleton `instance_settings` row (id=1) with encrypted `mailer_dsn` (`#[Encrypted]`) and optional `mailer_from`.
- **FR-002**: Admin UI at `/settings/mailer` (`ROLE_ADMIN`) with hub card, sidebar menu, and breadcrumb seed entries.
- **FR-003**: `ConfiguredMailer` implements `MailerInterface`, prefers DB DSN, falls back to `%env(MAILER_DSN)%`.
- **FR-004**: Magic login and email notification delivery use `ConfiguredMailer` (including From).
- **FR-005**: `.env` / `.env.dist` document `MAILER_DSN` as bootstrap/fallback only.
- **FR-006**: Docs updated: CHANGELOG, UPGRADING, NOTIFICATIONS, SECURITY, LEGAL-AND-COOKIES as needed.

## Success Criteria

- **SC-001**: Functional tests cover admin-only access, encrypt-at-rest, and clear → env fallback (`InstanceMailerSettingsTest`).
- **SC-002**: `make qa` stays green with Doctrine mapping + route registration for Settings controllers.

## Assumptions

- Same Halite default profile as `ProjectApiKey.secretKey` and `NotificationDestination.endpointUrl`.
- No per-project Mailer DSN in this feature (instance-wide only).
- English UI catalogues are source of truth; Spanish (and other locales) follow project i18n conventions.

## Out of Scope

- Multi-tenant / per-project SMTP credentials.
- OAuth “sign in with Google” style mailer setup wizards.
- Removing Symfony’s `framework.mailer.dsn` env binding entirely (kept for container boot + fallback).
- Marketing email / newsletter tooling.
