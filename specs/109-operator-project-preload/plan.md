# Implementation Plan: Operator project preload

**Feature**: `109-operator-project-preload`  
**Status**: Done (shipped **v1.25.0** / Phase 6.61)

## Approach

1. Extend `ProjectConfigPortability` with validated UUID normalize + mismatch/conflict errors; `countValidatedProjects()` for dry-run.
2. Harden `PublicUuidTrait::assignUuid()` with `Uuid::isValid()`.
3. Implement `PreloadProjectsCommand` with dry-run + transactional apply + optional api-keys file.
4. Document in INSTALL/PRODUCTION; cover with PHPUnit.

## Ship

- Tag: `v1.25.0` (2026-09-09)
- Spec folder added retroactively 2026-09-10 for Spec Kit traceability.
