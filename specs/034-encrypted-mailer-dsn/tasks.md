# Tasks: Encrypted Instance Mailer DSN

**Spec**: [spec.md](./spec.md) · **Plan**: [plan.md](./plan.md)

## Phase 1: Persistence

- [x] T001 Create `InstanceSettings` entity (`mailer_dsn` encrypted, `mailer_from`) + repository `getOrCreate`/`save`
- [x] T002 Migration `Version20260721193000` + singleton INSERT id=1
- [x] T003 Register Doctrine mapping `SharedSettings` and routes `controllers_settings`

## Phase 2: Runtime mailer

- [x] T004 Implement `ConfiguredMailer` (DB → env fallback, From helper, `ResetInterface`)
- [x] T005 Alias `MailerInterface` → `ConfiguredMailer` in `services.yaml`
- [x] T006 Wire magic login + email notification delivery to use From from settings

## Phase 3: Admin UI and i18n

- [x] T007 `/settings/mailer` form (unmapped DSN password field, clear checkbox, From)
- [x] T008 Hub card + dashboard menu + breadcrumb seeders
- [x] T009 English + Spanish catalogues (other locales fall back / nav key)

## Phase 4: Validation and docs

- [x] T010 PHPUnit `InstanceMailerSettingsTest` (403, encrypt-at-rest, clear fallback)
- [x] T011 Update CHANGELOG / UPGRADING / NOTIFICATIONS / SECURITY / LEGAL / `.env.dist`
- [x] T012 Speckit artifacts `034-encrypted-mailer-dsn` + ROADMAP pointer
