# Feature Specification: API secret always required

**Feature Branch**: `052-api-public-key-hardening`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: Treat public key as opaque non-secret id; ingest always requires a non-empty secret (`hash_equals`).

## User Scenarios & Testing

### User Story 1 - Reject empty secret (Priority: P1)

**Acceptance Scenarios**:

1. **Given** Envelope auth with empty/missing secret, **When** ingest is attempted, **Then** the request is rejected (403).
2. **Given** matching public key + secret for the project, **When** ingest is attempted, **Then** auth succeeds.

## Requirements

- Document in DSN.md that public key is not a credential.
