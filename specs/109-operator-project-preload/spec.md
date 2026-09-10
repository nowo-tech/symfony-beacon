# Feature Specification: Operator project preload

**Feature Branch**: `109-operator-project-preload`  
**Created**: 2026-09-10  
**Status**: Shipped (v1.25.0 / Phase 6.61)  
**Roadmap**: Phase 6.61  

**Input**: Operators need a console command to preload projects (and optional API keys) from devops-owned JSON without committing secrets into the Beacon application repository. Preload MUST reuse project-bundle portability (`089`), honour stable UUIDs, fail closed on UUID conflicts/mismatches, and support transactional apply with `--dry-run` validation.

## Summary

| ID | Area | Deliverable |
|----|------|-------------|
| P1 | Command | `app:preload-projects` — `--bundle=` (`beacon-project-bundle`) + optional `--api-keys=` (`beacon-api-keys`) |
| P2 | UUID | `PublicUuidTrait::assignUuid()` validates RFC UUID; import fails on `invalid_uuid`, `uuid_conflict`, `uuid_mismatch` (existing code + different UUID) |
| P3 | Dry-run | `--dry-run` validates bundle (via `countValidatedProjects`) and api-keys shape without writing |
| P4 | Transaction | Real runs wrap project import + API key create in one `EntityManager::wrapInTransaction` |
| P5 | Secrets | API key material lives in devops paths only — never committed to this repo |
| P6 | Docs / tests | `docs/INSTALL.md`, `docs/PRODUCTION.md`; PHPUnit for command + portability + `assignUuid` |

## Non-goals

- Replacing UI project config import/export (`089` admin / settings panels)
- Shipping sample secret JSON in the git tree
- Multi-tenant SaaS control plane / remote push of bundles
- Changing Envelope ingest auth

## User Scenarios & Testing

### User Story 1 - Dry-run validates before apply (P1)

As an operator, I run preload with `--dry-run` and learn whether the bundle/API-keys files are well-formed and UUID-safe before touching the database.

**Independent Test**: Invalid UUID or api-keys shape exits non-zero on `--dry-run` with no Doctrine writes.

**Acceptance Scenarios**:

1. **Given** a valid bundle path, **When** `app:preload-projects --bundle=… --dry-run`, **Then** validation succeeds and the DB is unchanged.
2. **Given** an invalid project UUID in the bundle, **When** dry-run runs, **Then** the command fails with `invalid_uuid` (or equivalent) and does not persist.

### User Story 2 - Apply is transactional and UUID-stable (P1)

As an operator, a successful preload creates/updates projects with stable UUIDs and optional API keys in one transaction; a mismatch on an existing project code fails closed.

**Independent Test**: Existing project code with a different incoming UUID fails with `uuid_mismatch`; matching UUID upserts; create with colliding UUID fails with `uuid_conflict`.

**Acceptance Scenarios**:

1. **Given** no existing project for `code`, **When** preload runs with a valid UUID, **Then** the project is created with that UUID and optional keys from the api-keys file.
2. **Given** an existing project with the same code and UUID, **When** preload runs, **Then** metadata/memberships upsert per `089` rules.
3. **Given** an existing project with the same code and a different UUID, **When** preload runs, **Then** the command fails (`uuid_mismatch`) and rolls back the transaction.

### User Story 3 - Docs keep secrets out of git (P2)

As a maintainer, INSTALL/PRODUCTION describe mounting devops JSON and running dry-run then apply; CONTRIBUTING/security review rejects committing key material.

**Independent Test**: Docs reference devops-owned paths; no `beacon-api-keys` fixtures with live secrets in-repo.

## Functional Requirements

- **FR-001**: Console command `app:preload-projects` MUST accept `--bundle` and optional `--api-keys` and `--dry-run`.
- **FR-002**: Bundle parsing/upsert MUST go through `ProjectConfigPortability` (same schema as `089`).
- **FR-003**: `assignUuid()` MUST reject non-RFC UUID strings before persist.
- **FR-004**: Existing code + different UUID MUST fail (`uuid_mismatch`); create MUST fail on UUID already used (`uuid_conflict`).
- **FR-005**: Non-dry-run MUST use a single DB transaction for import + key create.
- **FR-006**: Docs MUST state secrets stay outside the application repository.

## Success Criteria

- PHPUnit covers preload command, portability UUID cases, and `assignUuid`.
- Documented in `[1.25.0]` changelog and PRODUCTION/INSTALL.

## Cross-refs

- Project bundle UI/portability: `specs/089-project-config-export/`
- Install layers: `docs/INSTALL.md`, `docs/PRODUCTION.md`
- Changelog: `docs/CHANGELOG.md` `[1.25.0]`
