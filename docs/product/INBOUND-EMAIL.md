# Inbound email → issue comment

Opt-in feature: members reply to **mention** / **assign** emails and Beacon turns the reply into an issue comment.

## Enable

1. Under **Administration → Ops defaults**, enable inbound email, set the mail domain, and store the webhook secret (encrypted at rest).

2. Point your inbound provider (Mailgun Routes, etc.) to:

   ```text
   POST https://<beacon-host>/hooks/email/inbound
   ```

   Send header `X-Beacon-Inbound-Secret: <same secret>` only (body `beacon_secret` is rejected).

3. Migrate: `php bin/console doctrine:migrations:migrate` (`inbound_email_message` table + ops columns).

## Behaviour

- Outbound personal mail includes `Reply-To: reply+{hmac-token}@{inbound mail domain}`.
- Webhook expects Mailgun-style fields: `sender` / `from`, `recipient` / `To`, `body-plain` / `stripped-text`, `Message-Id`.
- Author must match a Beacon user email with project **triage**.
- Quoted replies are stripped; empty bodies are ignored.
- Duplicate `Message-Id` returns `duplicate` without a second comment.

## Privacy

Comment bodies and sender addresses are stored as issue discussion. Update operator privacy/terms accordingly. Prefer TLS to the webhook and a strong shared secret.
