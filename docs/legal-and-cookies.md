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
| `categories` | `analytics`, `preferences` (plus always-on required) |
| `use_logger` | `true` (writes `dashboard_cookie_log`) |
| `use_cookie_inventory` | `true` (YAML inventory for session / remember-me / consent cookies) |
| `preferences_bubble_enabled` | `true` (re-open settings after consent) |
| `enabled_locales` | `en`, `es` |
| `disabled_routes` | legal pages (banner does not auto-open there) |

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

## References

- Bundle docs: [CONFIGURATION](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/CONFIGURATION.md), [USAGE](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/USAGE.md)
- Native mobile store reminder: [docs/native-mobile.md](native-mobile.md)
