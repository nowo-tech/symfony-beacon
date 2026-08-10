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

## Amendment (`087-security-audit-hardening`, 2026-08-10)

6. Outside `dev`/`test`, empty `APP_SECRET`, the documented `.env.dist` value `ChangeMePleaseUseARealSecret`, or secrets shorter than **16** characters MUST also fail closed (same guard).
7. Unit tests cover APP_SECRET documented-default and short-secret reject for `prod`/`staging`.

## Out of scope

- Share-link max uses (`061`)
- Changing SiteBackup authentication mechanism
- Show-once API DSN / seed-demo env gate (see `087`)
