# Legal pages and cookie consent

Beacon ships public **legal** pages and a GDPR-oriented **cookie consent** modal via [`nowo-tech/cookie-consent-bundle`](https://packagist.org/packages/nowo-tech/cookie-consent-bundle).

## Pages

| Path | Route | Purpose |
|------|-------|---------|
| `/legal/notice` | `legal_notice` | LSSI-CE art. 10 identification |
| `/legal/privacy` | `legal_privacy` | GDPR / LOPDGDD privacy policy |
| `/legal/terms` | `legal_terms` | Terms of use template for this instance |
| `/legal/cookies` | `legal_cookies` | Cookie categories + inventory |

All of these are **public** (`PUBLIC_ACCESS`). Until an administrator saves a locale, copy comes from `translations/messages.*.yaml` (`legal.*`) for every enabled locale (`en`, `es`, `de`, `nl`, `fr`, `it`, `pt`).

Administrators edit the published HTML at **Administration → Legal pages** (`/admin/legal`) with CKEditor 5 (`nowo-tech/ckeditor5-editor-bundle` via FormKit). Each slug (`notice`, `privacy`, `terms`, `cookies`) is stored per locale in `legal_document`. Empty installs keep the built-in seed, so a deployment can change the text without forking the repository. **Restore built-in text** deletes that locale’s row. Cookie consent stays on `nowo-tech/cookie-consent-bundle` — this screen does not replace the consent modal.

Saved HTML is filtered on save and again when the public page renders. Scripts, inline event handlers, `javascript:` / `data:` / `vbscript:` URLs, and iframes are removed (a YouTube embed will not survive). Brand name, session-cookie name, and the “manage cookies” control stay live only in the seed; a saved body is static HTML.

Run `bin/console assets:install` after installing the editor bundle so `ckeditor5-editor.js` is published under `public/bundles/` (Composer `auto-scripts` does this on install/update).

> **Operator duty:** default seed copy is **generic and editable** (`[Operator legal name — replace]`, `privacy@example.com`). It must **not** name nowo.tech or Nowo Insurance Services, S.L. Whoever deploys this software is the provider and controller of that deployment and must replace every placeholder before processing other people’s personal data. Nowo.tech identity belongs in [`nowo-tech-web`](../../../nowo-tech-web/). This file is an engineering record of the statutory checklist, not a law-firm opinion. The admin editor is how the operator publishes that replacement; it is not a substitute for counsel (REQ-CC-010).

## Current-law review (REQ-CC-010)

| Field | Beacon |
| ----- | ------ |
| **Jurisdictions in scope** | EU/EEA (GDPR) + Spain (LSSI-CE art. 10 / LOPDGDD / AEPD) — template structure |
| **Last legal-content review** | **2026-09-18** — default seed reverted to generic placeholders (REQ-CC-011). GDPR/LSSI headings, purposes, bases, recipients, retention, rights, AEPD, and `di_obs` stay. **Counsel pending — not production** until the operator fills identity |
| **Controller** | **Placeholder** — `[Operator legal name — replace]` · `[privacy@example.com — replace]`. Not Nowo Insurance Services, S.L. |
| **Published copy** | Editable template (notice / privacy / terms / cookies). Cookie table includes `di_obs` (1 hour), matching `nowo_cookie_consent.yaml` |
| **Production gate** | **Not met** until the operator replaces placeholders. Do not process other people’s personal data on the default seed |

Re-record the date in this table (and `docs/ops/ENGINEERING-AUDIT.md`) whenever AuthKit, analytics, mail, Beacon DSN, payments, or processors change.

## Cookie consent bundle

Configuration: `config/packages/nowo_cookie_consent.yaml`

| Setting | Beacon value |
|---------|----------------|
| Pin | `nowo-tech/cookie-consent-bundle` **1.9.0** |
| `ui_theme` | `tailwind` |
| `web_ui` | enabled; Administration → **Cookie consent** (`/admin/cookie-consent` → `/cookie-consent-config/{id}/settings/profile`, CookieConsent **1.5+**) |
| `form_action` | `nowo_cookie_consent.show` (`/cookie_consent` — required so XHR does not POST to the current page) |
| `use_database_config` | `true` (modal copy + display from DB; seeded by `app:seed-platform`) |
| `csrf_protection` | `true` (modal JS double-submits SameOrigin CSRF for XHR; keep enabled) |
| `color_theme` | `light` (Beacon SCSS remaps to moss tokens; follows `data-theme`) |
| `disable_page_interaction` | `true` (dimmed overlay until choice) |
| `categories` | `analytics`, `preferences` (plus always-on required) |
| `use_logger` | `true` (writes `dashboard_cookie_log`) |
| `use_cookie_inventory` | `true` (DB inventory after platform seed; YAML fallback until then) |
| `preferences_bubble_enabled` | `false` (AuthKit layout can include the bubble manually) |
| `enabled_locales` | `en`, `es`, `de`, `nl`, `fr`, `it`, `pt` |
| `route_targeting_mode` | `only` — auto-open on public AuthKit entry routes (login/register/magic/reset/QR start) |
| `disabled_routes` | Legal pages (`legal_*`) — no auto-open; fragment still renders for “Manage cookies” |
| `render_routes` | Public whitelist (`legal_*`, `nowo_auth_kit_*`, `nowo_site_backup_setup*`, `guest_locale_switch`, `app_home_redirect`) — consent markup only there |
| `skip_render_routes` | empty (whitelist is enough; deny would still win if set) |

Twig overrides (optional) live under `templates/bundles/NowoCookieConsentBundle/` — CookieConsent **1.4.5+** prepends that path automatically. **Do not** fork the public modal Twig for skinning.

### Public modal skin (CookieConsent ≥1.9)

Kit skin is **bundled into the Vite `app` CSS** (`assets/app.ts` imports `nowo-cookie-consent.css`). Layouts set `data-nowo-cookie-consent-external-css="true"` on `<html>` so `nowo-consent-modal.js` skips injecting a `<style>` tag.

Do **not** link `/bundles/nowocookieconsent/nowo-cookie-consent.css` on public pages — many ad blockers match that path and strip the modal chrome (unstyled top-left dump). Optional host token remaps still go through `--nowo-cc-*` / moss.

Host bridge (layout only):

- `assets/styles/tailwind.css` `@source`s CookieConsent vendor Twig so Tailwind utilities on the modal markup are generated.
- `assets/styles/_cookie_consent.scss` positions the bottom-left box via `data-nowo-*`, clears `.site-legal-footer`, and keeps the preferences bubble bottom-right.

Modal **layout / position / equal-weight buttons** live on the DB profile (`dashboard_cookie_config`), not YAML. `CookieConsentDemoSeeder` + `src/Setup/Demo/fixtures/cookie_consent.default.json` upsert:

- `consentModal`: `box` / `wide` / **`bottom`** / **`left`** / equal-weight buttons on
- `preferencesModal`: same corner + equal-weight buttons

Re-run `make seed-platform` after changing the fixture so existing instances pick up position/skin settings.

XHR CSRF double-submit lives in vendor `nowo-consent-modal.js` (**≥1.4.8**).

Routes: `config/routes/nowo_cookie_consent.yaml`  
Privacy link from the modal: `translations/NowoCookieConsentBundle.*.yaml` → `legal_privacy`.

Layouts embed the modal only on public routes:

```twig
{% if nowo_cookie_consent_should_render() %}
    {{ render(path('nowo_cookie_consent.show')) }}
{% endif %}
```

On app shells where the fragment is skipped, the footer “Manage cookies” link goes to `/legal/cookies` instead of `data-nowo-open-consent` (`templates/_legal_footer.html.twig`).

### Database

Run migrations so consent logging / config tables exist, then seed the default profile:

```bash
make console ARGS='doctrine:migrations:migrate -n'
make seed-platform
# or Setup wizard → step 1 (platform)
```

`CookieConsentDemoSeeder` creates the default enabled profile (`dashboard_cookie_config`), locale copy (`en`/`es`/`de`/`nl`/`fr`/`it`/`pt`), and first-party cookie definitions aligned with CookieConsentBundle `CookieNameEnum` plus Symfony session / remember-me / CSRF:

| Cookie | Category | Notes |
|--------|----------|--------|
| `SYMFONY_BEACON_SESSID` | required | Framework session (1 day) |
| `REMEMBERME` | required | AuthKit remember-me (30 days) |
| `csrf-token_*` | required | Symfony double-submit CSRF (`__Host-` on HTTPS) |
| `Cookie_Consent` | required | Consent decision marker (bundle) |
| `Cookie_Consent_Key` | required | Anonymous audit key (bundle) |
| `Cookie_Category_analytics` | required | Category choice flag (bundle; not a tracker) |
| `Cookie_Category_preferences` | required | Category choice flag (bundle) |
| `di_obs` | required | Device Intelligence observation pointer (HttpOnly; account security, not a credential) |

YAML `cookie_inventory` remains a fallback until the DB inventory exists. Re-run `app:seed-platform` (or Setup → platform) after upgrading the cookie-consent bundle so legacy `CookieConsent` / `CookieConsentKey` names are renamed.

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

### Account export and anonymize (`043`)

Signed-in users can download a JSON export of **their** account fields, project/group memberships metadata, and allowlisted security activity from **Account → Privacy** (`/account/privacy`). Instance admins can export or anonymize other accounts from **Admin → Users**.

Anonymize scrubs email/display name, disables login (UserKit), clears password history / social links / push subscriptions, and sets `anonymized_at`. It does **not** delete project ingest events or issues (those remain project telemetry until retention purge). Blocked when the account is the sole direct project owner or the last instance administrator.

Operators should mention these controls and event retention in `/legal/privacy` placeholder copy before production.

## HTTP request audit log

[`nowo-tech/http-log-bundle`](https://packagist.org/packages/nowo-tech/http-log-bundle) may store request metadata (path, route, status, duration), optional headers/bodies (redacted), **client IP**, and **user identifier** for operators at `/admin/http-log`.

Treat this as personal data when IPs or account identifiers are captured: document processing in your privacy policy, keep retention configured (`nowo_http_log.retention.days`, default 30), schedule `nowo:http-log:purge`, and restrict the admin UI to `ROLE_ADMIN`. Ingest (`/api`), health, and metrics paths are ignored by default in this host.

## References

- Bundle docs: [CONFIGURATION](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/CONFIGURATION.md), [USAGE](https://github.com/nowo-tech/CookieConsentBundle/blob/main/docs/USAGE.md)
- Mobile / PWA note: [NATIVE-MOBILE.md](../dev/NATIVE-MOBILE.md)
