# Feature Specification: Ingest worker governance re-check

**Feature Branch**: `051-ingest-worker-recheck`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2) — retrospective SDD artifact

**Input**: After HTTP ACK, `ProcessEnvelopeHandler` must re-check ingest suspend and daily quota before persisting.

## User Scenarios & Testing

### User Story 1 - Drop blocked envelopes in worker (Priority: P1)

**Acceptance Scenarios**:

1. **Given** a project suspended after ACK but before consume, **When** the handler runs, **Then** the envelope is dropped (not persisted).
2. **Given** daily quota exceeded after ACK, **When** the handler runs, **Then** the envelope is dropped.

## Notes

Generic client parse errors (detail → logs only) remain a low-priority Planned follow-up.
