# Feature Specification: PHPStan FrankenPHP 1.1.0 production gate

**Feature Branch**: `094-phpstan-frankenphp-110`  
**Created**: 2026-08-13  
**Status**: Implemented (v1.9.0)  
**Roadmap**: Phase 6.43  

**Input**: Bump `nowo-tech/phpstan-frankenphp` to **1.1.0**, adopt the package production gate (`rules.neon`), keep CI PHPStan green under the new worker/hardening identifiers, and document ignore policy for intentional CLI-only shims.

## Summary

Maintainers need the Beacon CI static-analysis gate to track **FrankenPHP worker process-state rules** shipped in `phpstan-frankenphp` 1.1.0 (chdir/locale/timezone/mbstring/`error_reporting`/`umask` mutations; `pcntl_signal*` hardening) without weakening classic/worker coverage already enabled on this repo.

## Scope

| ID | Area | Deliverable |
|----|------|-------------|
| P1 | Composer | Pin `nowo-tech/phpstan-frankenphp` **1.1.0** (dev); PHPStan core/plugins remain at current Packagist latest when unchanged |
| P2 | Config | `phpstan.neon.dist` includes `extension.neon` + **`rules.neon`** (classic + worker + hardening aggregate) |
| P3 | Remediation | Application `src/` clean under new identifiers; no new request-path process-state leaks |
| P4 | Ignores | Documented identifier ignores only: keep `doctrine.associationType` on entities; allow `frankenphp.worker.noUmask` **only** on `tests/bootstrap.php` (PHPUnit CLI Flex `umask`) |
| P5 | Docs | `docs/ops/FRANKENPHP-CODING.md`, CHANGELOG, ROADMAP 6.43 aligned with `rules.neon` |

## Non-goals

- Enabling `ruleset-worker-strict.neon` (request `$_GET`/`$_POST`/… — framework-heavy)
- Removing `doctrine.associationType` entity ignore (separate entity-typing cleanup)
- Changing default local `FRANKENPHP_MODE` from `classic` to `worker`
- Proving zero memory leaks under load (LOOP_MAX remains the safety net)
- Switching password-policy `flash_throttle_storage` from `session` to `cache`

## User Scenarios & Testing *(mandatory)*

### User Story 1 - CI gate adopts 1.1.0 rules (Priority: P1)

As a maintainer, `make phpstan` / CI PHPStan fails if application code introduces process-wide mutations banned by `phpstan-frankenphp` 1.1.0 worker/hardening rules.

**Why this priority**: Prevents regressions that only appear under `FRANKENPHP_MODE=worker`.

**Independent Test**: Run `vendor/bin/phpstan analyse -c phpstan.neon.dist`; expect zero errors with current tree; temporarily add `umask(0)` or `date_default_timezone_set(...)` under `src/` and see the matching `frankenphp.worker.*` identifier.

**Acceptance Scenarios**:

1. **Given** `nowo-tech/phpstan-frankenphp` 1.1.0 and `rules.neon` included, **When** PHPStan analyses `src/` + `tests/`, **Then** there are no FrankenPHP findings except the documented `tests/bootstrap.php` umask ignore.
2. **Given** the production gate, **When** a contributor reads `phpstan.neon.dist`, **Then** it references `rules.neon` (not three separate ruleset includes) plus optional commented `ruleset-worker-strict.neon`.

---

### User Story 2 - Intentional CLI shim stays explicit (Priority: P2)

As a maintainer, the Symfony Flex debug `umask(0000)` in PHPUnit bootstrap remains allowed via a **path-scoped** identifier ignore, not a global suppress of `frankenphp.worker.noUmask`.

**Independent Test**: Inspect `phpstan.neon.dist` `ignoreErrors`; only `tests/bootstrap.php` matches that identifier.

**Acceptance Scenarios**:

1. **Given** `tests/bootstrap.php` calls `umask(0000)` under `APP_DEBUG`, **When** PHPStan runs, **Then** analysis succeeds.
2. **Given** the same call under `src/`, **When** PHPStan runs without a matching ignore, **Then** it reports `frankenphp.worker.noUmask`.

## Requirements *(mandatory)*

- **FR-001**: Require `nowo-tech/phpstan-frankenphp` **1.1.0** in `composer.json` / lock.
- **FR-002**: `phpstan.neon.dist` MUST include `vendor/nowo-tech/phpstan-frankenphp/extension.neon` and `vendor/nowo-tech/phpstan-frankenphp/rules.neon`.
- **FR-003**: `src/` MUST be clean under classic + worker + hardening rules from 1.1.0.
- **FR-004**: `frankenphp.worker.noUmask` ignore, if present, MUST be limited to `tests/bootstrap.php`.
- **FR-005**: Operator/dev docs that describe the PHPStan FrankenPHP includes MUST mention `rules.neon` as the production gate.

## Success Criteria

- **SC-001**: `make phpstan` exits 0 on the feature tree.
- **SC-002**: Spec + ROADMAP 6.43 + FRANKENPHP-CODING reference match `phpstan.neon.dist`.
- **SC-003**: CHANGELOG Unreleased records the 1.1.0 bump (and corrects any earlier false pin claim).

## Assumptions

- Beacon already ran classic + worker + hardening under 1.0.x; 1.1.0 adds identifiers inside those rulesets.
- Worker-strict remains opt-in.
- `doctrine.associationType` entity ignore is orthogonal and retained.

## Amendment (empty baseline + injectable seams, 2026-08-25 / `106`)

`phpstan-baseline.neon` is empty (`parameters: {}`). `phpstan.neon.dist` MUST NOT list `ignoreErrors`: association nullability uses `doctrine.allowNullablePropertyForRequiredField`; FrankenPHP worker/hardening stays green via injectable Clock, `HostnameDnsLookup`, and `HaliteSecretsFilesystem` (no process-wide umask in PHPUnit bootstrap, no path-scoped `frankenphp.worker.*` ignores on `src/`). Issue query traits MUST NOT use `phpstan-require-extends` (unit harnesses compose them without pretending to be `ServiceEntityRepository`). `tests/` MUST analyse clean at level 6 without ignores. Rector owns semantic upgrades only; CS-Fixer owns PER-CS / imports / native `\fn()`; `make qa-fix` runs Rector then CS-Fixer. Production gate remains `rules.neon` (`094` P2). See `specs/106-ops-ingest-hardening/` H10.
