# Research: PHPStan FrankenPHP 1.1.0

**Feature**: `094-phpstan-frankenphp-110`  
**Date**: 2026-08-13

## Decision: adopt `rules.neon` instead of three ruleset includes

**Choice**: Include `vendor/nowo-tech/phpstan-frankenphp/rules.neon` (plus `extension.neon`).

**Rationale**: Package docs call `rules.neon` the production gate; it expands to classic + worker + hardening — same coverage Beacon already enabled under 1.0.x. Fewer include lines; matches CONFIGURATION.md.

**Alternatives considered**: Keep three explicit ruleset includes (migration pedagogy). Rejected for this mature worker-targeted app; phased adoption already done.

## Decision: keep path-scoped `frankenphp.worker.noUmask` on tests bootstrap

**Choice**: Ignore only `tests/bootstrap.php`.

**Rationale**: Symfony Flex debug `umask(0000)` is a PHPUnit CLI shim, not an HTTP worker request path. UPGRADING.md allows identifier ignores for intentional CLI shims. Removing `umask` without verifying Docker/test file modes risks flaky permission failures.

**Alternatives considered**: Remove `umask` from bootstrap; move to container entrypoint. Deferred — out of scope for the pin bump.

## Decision: retain `doctrine.associationType` entity ignore

**Choice**: Keep existing ignore on `src/*/Entity/*`.

**Rationale**: ~36 association nullability mismatches; fixing requires a dedicated entity typing pass, not part of FrankenPHP 1.1.0.

## 1.1.0 rule delta (consumer impact)

New worker identifiers (process-wide state): `noChdir`, `noSetLocale`, `noLocaleSetDefault`, `noDateDefaultTimezoneSet`, `noMbEncodingMutation`, `noErrorReportingMutation`, `noUmask`.

New hardening: `noPcntlSignal` (and related `pcntl_*` signal APIs).

Beacon `src/` had zero hits after upgrade; only test bootstrap umask surfaced.

## Historical correction

CHANGELOG **v1.7.0** incorrectly listed `phpstan-frankenphp` **1.1.0** while `composer.lock` still resolved **1.0.3**. Correct that bullet to **1.0.3** and record the real bump under Unreleased / this feature.
