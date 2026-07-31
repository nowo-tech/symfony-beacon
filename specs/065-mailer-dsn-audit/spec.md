# Feature Specification: Mailer DSN Change Audit

**Feature Branch**: `065-mailer-dsn-audit`  
**Created**: 2026-07-31  
**Status**: Implemented (v0.17.0)  

**Input**: Extend encrypted instance Mailer (`034`) so Administration → Mailer save/clear actions are audited without leaking DSN secrets, and reject Mailer DSNs whose schemes are outside an allowlist.

**Parent**: [`034-encrypted-mailer-dsn`](../034-encrypted-mailer-dsn/spec.md). Roadmap **6.15**.

## Summary

- On Mailer DSN/From save or clear, record `UserAction` type `instance.mailer_updated` with redacted metadata (`scheme`, `host` only — never password/userinfo).
- `MailerDsnValidator` allowlists `smtp` / `smtps` / `sendmail` / `native` plus common provider schemes.

## Success Criteria

- Functional coverage in `InstanceMailerSettingsTest` / `MailerDsnAuditTest` / `MailerDsnValidatorTest`.
- Docs: CHANGELOG, UPGRADING, ROADMAP, PRODUCTION notes as needed.

## Out of Scope

- Local Mailpit catcher (see `066-local-mailpit`).
- Changing encryption or Admin Mailer UI layout beyond audit + validation.
