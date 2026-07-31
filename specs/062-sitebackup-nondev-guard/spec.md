# Spec: SiteBackup fail-closed outside dev/test (`062`)

## Problem

`SiteBackupSecurityDefaultsGuard` only rejected empty/local-default setup secrets when `kernel.environment === prod`. Staging and other non-dev env names could still boot with documented local defaults, leaving `/setup` and `/_site_backup` exposed.

## Goal

Fail closed in **every environment except `dev` and `test`**.

## Requirements

1. Guard skips checks only for `dev` and `test`.
2. Empty or documented-local `SITE_SETUP_TOKEN` / `SITE_BACKUP_PASSWORD_HASH` throw in `prod`, `staging`, and any other env.
3. Unit tests cover `dev` allow, `prod` reject, and at least one non-prod alias (e.g. `staging`) reject.
4. Docs: PRODUCTION.md, SECURITY.md, CHANGELOG, UPGRADING as needed.
5. Console `cache:clear` / `cache:warmup` / `assets:install` MUST skip the guard so `frankenphp_prod` Docker builds (`composer post-install-cmd`) can warm caches without embedding runtime SiteBackup secrets. HTTP and other console commands still enforce.

## Out of scope

- Share-link max uses (`061`)
- Changing SiteBackup authentication mechanism
