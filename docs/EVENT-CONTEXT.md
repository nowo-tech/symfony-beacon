# Event context

Beacon stores the full Envelope event JSON in `event.payload` and promotes common fields for UI and filters.

## Promoted columns

| Column | Source |
|--------|--------|
| `environment` | `payload.environment` |
| `release_version` | `payload.release` |
| `platform` | `payload.platform` |
| `php_version` | `payload.contexts.runtime.version` |
| `symfony_version` | `payload.contexts.framework` when `name` is `symfony` |
| `user_identifier` | `payload.user.id` / `username` / `email` |
| `event_timestamp` | `payload.timestamp` (fractional Unix) or `payload.datetime` — stored as `DATETIME(6)` |
| `received_at` | Server receive time — `DATETIME(6)` |

## Client configuration

Use `nowo-tech/beacon-bundle` `nowo_beacon.send.*` switches to control what the client attaches (stacktrace, request, user, PHP/Symfony versions, etc.). See the bundle [CONFIGURATION.md](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/CONFIGURATION.md).

`send.user` is **off by default** because it may transmit personal data. If you enable it, keep Beacon legal/privacy pages and cookie consent aligned with your processing (see [LEGAL-AND-COOKIES.md](LEGAL-AND-COOKIES.md)).

## Tags and before_send (BeaconBundle)

Primary implementation: [BeaconBundle USAGE — Tags / before_send](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/USAGE.md).

| Client API / config | Envelope field | Beacon UI |
|---------------------|----------------|-----------|
| `setTag` / `setTags` | `payload.tags` | Event detail → Tags → **Client tags** |
| `before_send` service | Mutates or drops payload pre-transport | Absent fields simply do not appear |

Also see [DSN.md](DSN.md#client-capabilities-beaconbundle) and specs `023-client-tags-scrubbing` / `024-client-spans` (Bundle-primary).

## Spans and breadcrumbs (BeaconBundle)

Opt-in `instrumentation.doctrine` / `instrumentation.http_client` attach SQL and HTTP spans to transactions and dual-write breadcrumbs on events. Config keys: [BeaconBundle CONFIGURATION](https://github.com/nowo-tech/BeaconBundle/blob/main/docs/CONFIGURATION.md). Spans render under Performance transaction detail; breadcrumbs under event Breadcrumbs.

## Stack source context

When the client includes readable frame context (BeaconBundle `v1.3.0+` with `send.stacktrace: true`), each stack frame may contain:

| Payload field | Meaning |
|---|---|
| `abs_path` | Absolute path on the reporting host |
| `pre_context` | Lines before the crash line |
| `context_line` | The line that threw / was current |
| `post_context` | Lines after the crash line |

The Issues UI renders these under each stack frame (first frame expanded by default).
