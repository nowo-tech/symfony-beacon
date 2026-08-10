# Feature Specification: API secret always required

**Feature Branch**: `052-api-public-key-hardening`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact; public-key entropy amended by `087` (2026-08-10)

**Input**: Treat public key as opaque non-secret id; ingest always requires a non-empty secret (`hash_equals`).

## User Scenarios & Testing

### User Story 1 - Reject empty secret (Priority: P1)

**Acceptance Scenarios**:

1. **Given** Envelope auth with empty/missing secret, **When** ingest is attempted, **Then** the request is rejected (403).
2. **Given** matching public key + secret for the project, **When** ingest is attempted, **Then** auth succeeds.

## Requirements

- Document in DSN.md that public key is not a credential.
- Public key MUST remain a non-secret identifier (not sufficient for ingest alone).

## Amendment (`087-security-audit-hardening`, 2026-08-10)

- Newly generated public keys MUST use high-entropy random material (`bin2hex(random_bytes(16+))`). Human-friendly adjective-noun tokens remain **labels only** (`ProjectApiKeyFactory`).
- Settings MUST NOT re-render the secret/DSN on every GET; show-once after create/rotate (see `087` FR-001 / `018` FR-004).
