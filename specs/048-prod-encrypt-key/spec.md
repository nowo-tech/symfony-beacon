# Feature Specification: Durable Halite encrypt key in production Compose

**Feature Branch**: `048-prod-encrypt-key`  
**Created**: 2026-07-21  
**Status**: Implemented (v0.12.2; PHPUnit durable key — 2026-07-22; Make `ensure-halite-secrets` — 2026-07-30) — retrospective SDD artifact

**Input**: Production Compose must persist `/app/var/secrets` so Halite keys survive container recreate. Tests must also use a file-backed key so KernelBrowser reboots keep decrypting API secrets. Local Make seed/dogfood paths must create `var/secrets/` before Halite auto-creates `.Halite.default.key`.

## User Scenarios & Testing

### User Story 1 - Secrets volume mounted (Priority: P1)

**Acceptance Scenarios**:

1. **Given** `compose.prod.yaml`, **When** `php` and `messenger` start, **Then** they share a durable `php_secrets` volume at `/app/var/secrets`.
2. **Given** PRODUCTION.md, **When** operators prepare backup, **Then** Halite key / `APP_ENCRYPT_KEY` backup steps are documented.

### User Story 2 - PHPUnit Halite key survives reboots (Priority: P1)

**Acceptance Scenarios**:

1. **Given** PHPUnit bootstrap, **When** `var/secrets/.Halite.default.key` is missing, **Then** bootstrap creates the directory and generates the key (via `doctrine:encrypt:generate-secret-key`) before tests run.
2. **Given** KernelBrowser default reboot between HTTP requests, **When** two Envelope ingest requests use the same API secret, **Then** the second request still authenticates (decrypt uses the same file key).

### User Story 3 - Local Make targets create secrets directory (Priority: P1)

**Acceptance Scenarios**:

1. **Given** a fresh checkout where `var/secrets/` is missing, **When** an operator runs `make dogfood`, `make seed-platform`, `make seed-sample`, or `make bootstrap`, **Then** the Makefile ensures `var/secrets/` exists before console commands that encrypt fields (Halite key auto-create must not fail with `file_put_contents(…/.Halite.default.key): No such file or directory`).
2. **Given** `make ensure-halite-secrets` (or the same `mkdir -p` as a dependency of those targets), **When** it runs inside the `php` container, **Then** `/app/var/secrets` exists afterward.

## Requirements

- Volume mount in prod Compose; PRODUCTION.md Halite section.
- PHPUnit `tests/bootstrap.php` MUST ensure a durable Halite key file under `var/secrets/` (gitignored via `/var/`) so in-memory-only keys are not regenerated on each kernel reboot.
- Dev/local Make targets that persist encrypted columns (`seed-platform`, `seed` / `dogfood`, `seed-sample`, `bootstrap`) MUST create `var/secrets/` before invoking console commands. The encrypt bundle writes `.Halite.default.key` but does not create the parent directory.
