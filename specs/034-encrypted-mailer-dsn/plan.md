# Implementation Plan: Encrypted Instance Mailer DSN

**Branch**: `034-encrypted-mailer-dsn`  
**Date**: 2026-07-21  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented (as-built)

## Summary

Move the operational Symfony Mailer DSN from `.env` into encrypted instance settings, expose an Administration UI, and resolve the transport at send-time so magic login and email notification destinations no longer depend on secrets in environment files.

## Technical Context

| Area | Decision |
|------|----------|
| Storage | Singleton `instance_settings` (id=1), pattern copied from `SiteAppearance` |
| Encryption | `#[Encrypted]` on `mailer_dsn` via `nowo-tech/doctrine-encrypt-bundle` (Halite) |
| From | Plain `mailer_from` column; effective default `beacon@localhost` |
| Runtime | `App\Shared\Mailer\ConfiguredMailer` aliased as `MailerInterface`; lazy `Transport::fromDsn`; `ResetInterface` |
| Fallback | `%env(MAILER_DSN)%` when DB DSN empty |
| UI | `/settings/mailer`, hub card, menu/breadcrumb seeders |
| Mapping | Doctrine mapping `SharedSettings` + routes `controllers_settings` |

## Constitution Check

- Prefer kits: doctrine-encrypt-bundle (already in stack); FormKit/PasswordToggle for secret field
- English docs / PHPDoc / UI strings
- No new tracking cookies; operational email privacy note updated in LEGAL-AND-COOKIES

## Project Structure

```text
src/Shared/Settings/Entity/InstanceSettings.php
src/Shared/Settings/Repository/InstanceSettingsRepository.php
src/Shared/Settings/Controller/MailerSettingsController.php
src/Shared/Settings/Form/InstanceMailerSettingsType.php
src/Shared/Mailer/ConfiguredMailer.php
templates/settings/mailer.html.twig
templates/admin/hub.html.twig
migrations/Version20260721193000.php
config/packages/doctrine.yaml
config/routes.yaml
config/services.yaml
tests/Shared/InstanceMailerSettingsTest.php
```

## Related specs

- Updates assumptions in `009-project-notifications` (email transport source).
- Complements `026-magic-links-viewer` (Mailer used for magic-link delivery).
