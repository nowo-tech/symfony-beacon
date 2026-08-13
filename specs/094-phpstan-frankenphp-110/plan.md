# Implementation Plan: PHPStan FrankenPHP 1.1.0 production gate

**Branch**: `094-phpstan-frankenphp-110` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/094-phpstan-frankenphp-110/spec.md`

## Summary

Bump `nowo-tech/phpstan-frankenphp` to 1.1.0, switch `phpstan.neon.dist` to the package **production gate** (`rules.neon` = classic + worker + hardening), keep `src/` clean under new process-state identifiers, and document the PHPUnit-only `umask` ignore.

## Technical Context

**Language/Version**: PHP >= 8.5  

**Primary Dependencies**: `nowo-tech/phpstan-frankenphp` 1.1.0, `phpstan/phpstan` 2.2.x (+ doctrine/symfony extensions)  

**Storage**: N/A  

**Testing**: `make phpstan`; full `make qa-fix` regression  

**Target Platform**: FrankenPHP classic/worker (Docker Compose)  

**Project Type**: Symfony 8 self-hosted Beacon server  

**Performance Goals**: N/A (static analysis gate)  

**Constraints**: Worker-safe coding target (`FRANKENPHP_MODE=worker` + `RESET_KERNEL=false`); no global suppress of new FrankenPHP identifiers  

**Scale/Scope**: Dev dependency + neon config + docs  

## Constitution Check

- [x] Spec-first: `specs/094-phpstan-frankenphp-110/`
- [x] Canonical stack / Docker-first / worker-safe
- [x] English docs/PHPDoc/UI; QA planned
- [x] No new `env():` defaults in `parameters.yaml`
- [x] Prefer `nowo-tech/*` kits (this package is the official FrankenPHP PHPStan kit)
- [x] No Cursor / agent attribution

## Project Structure

### Documentation (this feature)

```text
specs/094-phpstan-frankenphp-110/
├── plan.md
├── research.md
├── spec.md
└── tasks.md
```

### Touched paths

```text
composer.json / composer.lock
phpstan.neon.dist
docs/ops/FRANKENPHP-CODING.md
docs/CHANGELOG.md
docs/ROADMAP.md
tests/bootstrap.php          # unchanged call; ignore scoped in neon
tests/...                    # only remove obsolete @phpstan-ignore if unmatched
```

## Implementation approach

1. `composer update nowo-tech/phpstan-frankenphp` → 1.1.0.
2. Replace separate `ruleset-*.neon` includes with `rules.neon` (semantically equivalent aggregate).
3. Run PHPStan; remediate `src/` findings; path-scope CLI umask ignore if needed.
4. Align FRANKENPHP-CODING / CHANGELOG / ROADMAP.

## Complexity Tracking

None — dependency + config + docs only.
