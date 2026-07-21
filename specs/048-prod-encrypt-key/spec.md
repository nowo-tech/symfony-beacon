# Feature Specification: Durable Halite encrypt key in production Compose

**Feature Branch**: `048-prod-encrypt-key`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: Production Compose must persist `/app/var/secrets` so Halite keys survive container recreate.

## User Scenarios & Testing

### User Story 1 - Secrets volume mounted (Priority: P1)

**Acceptance Scenarios**:

1. **Given** `compose.prod.yaml`, **When** `php` and `messenger` start, **Then** they share a durable `php_secrets` volume at `/app/var/secrets`.
2. **Given** PRODUCTION.md, **When** operators prepare backup, **Then** Halite key / `APP_ENCRYPT_KEY` backup steps are documented.

## Requirements

- Volume mount in prod Compose; PRODUCTION.md Halite section.
