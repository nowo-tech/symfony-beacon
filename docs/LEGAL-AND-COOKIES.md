# Legal pages and cookie consent

Beacon ships public **legal** pages and a GDPR-oriented **cookie consent** modal via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle).

## Pages

| Path | Route | Purpose |
|------|-------|---------|
| `/legal/notice` | `legal_notice` | Legal notice / imprint (operator identity placeholders) |
| `/legal/privacy` | `legal_privacy` | Privacy policy template |
| `/legal/terms` | `legal_terms` | Terms of use template |
| `/legal/cookies` | `legal_cookies` | Cookie categories + inventory |

All of these are **public** (`PUBLIC_ACCESS`). Copy is English by default and translated for `es` under `translations/messages.*.yaml` (`legal.*` keys).

> **Operator duty:** replace placeholders (legal name, address, contact email, registry IDs, retention schedule) before exposing the instance to the public or shipping store apps. The templates are starting points, not legal advice.

## Cookie consent bundle

Configuration: `config/packages/nowo_cookie_consent.yaml`

| Setting | Beacon value |
|---------|----------------|
| `ui_theme` | `tailwind` |
| `form_action` | `nowo_cookie_consent.show` (`/cookie_consent` — required so XHR does not POST to the current page) |
| `csrf_protection` | `false` (vendor modal JS posts via XHR without firing `submit`, so Symfony stateless CSRF never hydrates) |
| `color_theme` | `light` (Beacon SCSS remaps to moss tokens; follows `data-theme`) |
| `disable_page_interaction` | `true` (dimmed overlay until choice) |
| `categories` | `analytics`, `preferences` (plus always-on required) |
| `use_logger` | `true` (writes `dashboard_cookie_log`) |
| `use_cookie_inventory` | `true` (YAML inventory for session / remember-me / consent cookies) |
| `preferences_bubble_enabled` | `false` (AuthKit layout can include the bubble manually) |
| `enabled_locales` | `en`, `es`, `de`, `nl`, `fr`, `it`, `pt` |
| `disabled_routes` | legal pages (banner does not auto-open there) |

Twig overrides live under `templates/bundles/NowoCookieConsentBundle/` (registered in `config/packages/twig.yaml`) and style tokens in `assets/styles/_cookie_consent.scss`.

Routes: `config/routes/nowo_cookie_consent.yaml`  
Privacy link from the modal: `translations/NowoCookieConsentBundle.*.yaml` → `legal_privacy`.

Layouts embed the modal when needed:

```twig
{% if nowo_cookie_consent_should_embed_modal() %}
    {{ render(path('nowo_cookie_consent.show_if_not_set')) }}
{% endif %}
```

Footer links and `data-nowo-open-consent` appear on app + AuthKit layouts (`templates/_legal_footer.html.twig`).

### Database

Run migrations so consent logging tables exist:

```bash
make console ARGS='doctrine:migrations:migrate -n'
```

### Assets

After install / upgrade:

```bash
docker compose exec -T php bin/console assets:install
```

Published under `public/bundles/nowocookieconsent/` (`nowo-consent-modal.js`).

## Adding third-party / analytics scripts later

Gate any non-essential script with the Twig helper:

```twig
{% if nowo_cookie_consent_is_category_allowed('analytics') %}
    {# load analytics only after consent #}
{% endif %}
```

Do **not** load marketing/analytics tags before consent.

## Operational email (magic login)

Magic-login messages sent via Symfony Mailer are **operational** account emails (not marketing) and do not add tracking cookies. Mention account-security email in the privacy policy when using a real Mailer DSN (Administration → Mailer). SSO/OIDC (roadmap Later) needs a separate privacy review.

## Field encryption (at rest)

Beacon encrypts selected secrets with [`nowo-tech/doctrine-encrypt-bundle`](https://packagist.org/packages/nowo-tech/doctrine-encrypt-bundle) (Halite):

| Entity field | Purpose |
|--------------|---------|
| `ProjectApiKey.secretKey` | Envelope DSN secret |
| `NotificationDestination.endpointUrl` | Slack / HTTP webhook URL (often contains tokens) |

Key material lives in `var/secrets/.Halite.default.key` by default (never commit). Document key handling and retention in your privacy / security notices when operating a public instance. Prefer `anonymize-bundle` for erasure workflows when personal data beyond auth essentials is stored.

## Admin activity history

Beacon stores an immutable **user action** trail (`user_action`) for:

- Administrative and membership events (create/role/enable, group CRUD, project member/group link changes)
- Explicit **product** actions (open project issues/settings/performance/analytics, open issue/event, create API key, clear/delete project, change issue assignee/status)

Each row may include the actor, subject user, structured context (emails, roles, project/issue titles, status from/to), and the **client IP** of the request. Form bodies and secrets are not stored.

Treat this as personal data: document it in your privacy policy, define retention, and restrict `/admin/users` (and per-user activity) to operators who need it. Per-issue assignee/status history (`issue_history`) remains on the issue page and is separate from this instance-wide timeline. AuditKit timestamps/blame on entities are also separate and do not replace this timeline.

## References

- Bundle docs: [CONFIGURATION](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/CONFIGURATION.md), [USAGE](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/USAGE.md)
- Mobile / PWA note: [docs/NATIVE-MOBILE.md](NATIVE-MOBILE.md)
