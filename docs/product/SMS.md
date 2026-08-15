# SMS provider (AuthKit phone OTP Later; ops via `bin/console app:sms:send`)

Beacon can enqueue SMS through a pluggable provider. The first shipped transport is **SMS Bridge** ([`sms-bridge`](../../sms-bridge/) sibling app), using either:

- **Twilio-compatible** Messages API (`SMS_BRIDGE_API_MODE=twilio`, default when Account SID is set)
- **Native** `POST /api/v1/sms` (`SMS_BRIDGE_API_MODE=native`)

## Enable

In `.env.local`:

```bash
SMS_PROVIDER=sms_bridge
SMS_BRIDGE_BASE_URL=https://host.docker.internal:9451
# Twilio-compat (preferred):
SMS_BRIDGE_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SMS_BRIDGE_AUTH_TOKEN=smscli_…
SMS_BRIDGE_API_MODE=twilio
# Optional defaults:
# SMS_BRIDGE_FROM=+34600000001
# SMS_BRIDGE_DEVICE_ID=<device-uuid>
```

Both Beacon and SMS Bridge join `server_network`; from the PHP container you can also use the Compose service host for the bridge when published on that network.

Create the Account SID + `smscli_…` Auth Token in the SMS Bridge UI (**API keys**).

## Test send

```bash
docker compose exec php bin/console app:sms:send '+34600111222' 'Hello from Beacon'
```

## Code

Inject `App\Shared\Sms\SmsSenderInterface` (bound to `ConfiguredSmsSender`):

```php
$result = $smsSender->send(new SmsOutboundMessage('+34600111222', 'Your code is 123456'));
```

`SMS_PROVIDER=null` (default) keeps SMS disabled so QR phone OTP remains Later until credentials are set.
