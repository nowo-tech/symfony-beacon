# Adding a UI language

English guide for enabling a new locale in **symfony-beacon**. Documentation, specs, and PHPDoc stay English; this manual covers **user-facing** UI catalogues and config.

Default UI locale is controlled by **`DEFAULT_LOCALE`** in `.env` (feeds `framework.default_locale`).

| Source | Value | Purpose |
|--------|--------|---------|
| `.env.dist` | `en` | Template for fresh clones / upstream distribution |
| This project's `.env` | `es` | Operator default for this Beacon instance |
| PHPUnit (`phpunit.dist.xml`) | `en` | Stable CI matching `.env.dist` |

Translator catalogue **fallbacks** remain `[en]` so missing keys still resolve to English.

## Current locales

| Code | Notes |
|------|--------|
| `en` | Shipped in `.env.dist`; translator fallback catalogues |
| `es` | This project's default via local `.env` (`DEFAULT_LOCALE=es`) |
| `de`, `nl`, `fr`, `it`, `pt` | Enabled alongside English/Spanish |

Public surfaces:

| Surface | Behaviour |
|---------|-----------|
| AuthKit (login/register/logout/reset/magic) | `locale.in_path: both` + `unlocalized: serve` — bare serves `DEFAULT_LOCALE`; other locales use `/{_locale}/…` |
| Setup | Same bare-vs-prefixed rule via `LocalizedPublicPath`; prefixed **default** locale redirects to bare |
| Legal | Bare `/legal/…` redirects to `/{DEFAULT_LOCALE}/legal/…` |

Guests change language via the path switcher (links to another `/{locale}/…`) or `GET|POST /locale/{locale}?redirect=…` (session `_locale` + localize public paths). After sign-in, the app shell stores the preferred locale on the user account (`POST /account/locale/{locale}`) and does **not** put `_locale` in dashboard URLs.

## Default locale (`.env`)

```env
# Distribution template (.env.dist):
DEFAULT_LOCALE=en

# This project (local .env):
DEFAULT_LOCALE=es
```

This feeds `framework.default_locale` (`config/packages/translation.yaml`) and is reused by AuthKit, cookie consent, breadcrumb kit, and dashboard menu via `%kernel.default_locale%`. The value **must** be one of `framework.enabled_locales`.

## Checklist (summary)

1. Pick a standard IETF language tag (e.g. `pl`, `ca`, `pt_BR` — use the same code everywhere).
2. Enable the locale in **all** config lists below (keep them in sync).
3. Add translation catalogues under `translations/`.
4. Add `locale.{code}` labels in every `messages.*.yaml`.
5. Extend security firewall path regexes and `account_locale_switch` / `guest_locale_switch` / bare-redirect + prefixed public route requirements.
6. Extend menu / breadcrumb seeder translation maps (and re-seed).
7. Smoke-test AuthKit + app shell + cookie consent; extend PHPUnit if needed.
8. Update this doc’s “Current locales” table and `docs/CHANGELOG.md` when shipping.

---

## 1. Enable the locale in configuration

Update **every** list so Twig, AuthKit, consent, menus, and breadcrumbs agree:

| File | Key |
|------|-----|
| `.env` / `.env.dist` | `DEFAULT_LOCALE` → `framework.default_locale` |
| `config/packages/translation.yaml` | `framework.enabled_locales` (+ `default_locale: '%env(DEFAULT_LOCALE)%'`) |
| `config/packages/nowo_auth_kit.yaml` | `nowo_auth_kit.locale.enabled` (+ `locale.default` / `in_path: both`) |
| `config/packages/nowo_cookie_consent.yaml` | `nowo_cookie_consent.enabled_locales` |
| `config/packages/nowo_breadcrumb_kit.yaml` | `nowo_breadcrumb_kit.locales` |
| `config/packages/nowo_dashboard_menu.yaml` | `nowo_dashboard_menu.locales` |

Twig already exposes `enabled_locales: '%kernel.enabled_locales%'` (`config/packages/twig.yaml`). The locale switcher loops that global — no template change if the lists stay aligned.

Change the instance default with `DEFAULT_LOCALE` in `.env` (must stay in `enabled_locales`). Keep `.env.dist` at `en` for distribution. Translator `fallbacks` remain `[en]` so missing keys still resolve to English catalogues.

---

## 2. Add translation catalogues

Copy English sources and translate **values** only (keep keys identical):

| Source | New file |
|--------|----------|
| `translations/messages.en.yaml` | `translations/messages.{locale}.yaml` |
| `translations/NowoAuthKitBundle.en.yaml` | `translations/NowoAuthKitBundle.{locale}.yaml` |
| `translations/NowoCookieConsentBundle.en.yaml` | `translations/NowoCookieConsentBundle.{locale}.yaml` |
| `translations/AutocompleteBundle.en.yaml` | `translations/AutocompleteBundle.{locale}.yaml` |

Notes:

- `messages.*` holds app UI (nav, flashes, project Settings, admin, etc.) and any FormKit/password strings that use `translation_domain: messages`.
- AuthKit password-strength / toggle strings often live under the AuthKit domain — prefer translating `NowoAuthKitBundle.{locale}.yaml` (and vendor wording) over inventing new keys.
- Missing keys fall back to English via `fallbacks: [en]`. Still ship complete catalogues for enabled locales when possible.
- Do **not** invent locale codes; use the same tag in filenames, config, and route requirements.

### Switcher labels

In **every** `messages.*.yaml` (including `en` and existing locales), add:

```yaml
locale:
    nav: Language   # already present
    pl: Polski      # example — native endonym for the new locale
```

The switcher renders `('locale.' ~ locale)|trans`.

---

## 3. Security and account locale route

### AuthKit firewall paths

In `config/packages/security.yaml`, extend the public AuthKit regexes so the new code is allowed:

```yaml
- path: ^/(en|es|de|nl|fr|it|pt|pl)/(login|register|logout)
- path: ^/(login|register|logout)
- path: ^/locale/
```

(Replace `pl` with your locale; keep the full pipe-separated list. Include both bare and prefixed AuthKit paths.)

Also extend requirements on `GuestLocaleController` (`guest_locale_switch`), setup/legal route locale requirements, and `account_locale_switch`.

### Authenticated switcher

In `src/Identity/Controller/AccountLocaleController.php`, extend the route requirement:

```php
#[Route(
    '/account/locale/{locale}',
    name: 'account_locale_switch',
    requirements: ['locale' => 'en|es|de|nl|fr|it|pt|pl'],
    methods: ['POST'],
)]
```

The action also checks `kernel.enabled_locales` at runtime; the requirement and the config list must both include the new code.

---

## 4. Menu and breadcrumb seeders

Demo/admin seeders embed per-locale labels for Administration and other nav items, for example:

- `src/Shared/Menu/DashboardMenuDemoSeeder.php`
- `src/Shared/Breadcrumb/BreadcrumbDemoSeeder.php`

When you add a locale, append an entry to each translation map, e.g.:

```php
['en' => 'Appearance', 'es' => 'Apariencia', /* … */, 'pl' => 'Wygląd']
```

Then re-run seeding so existing DB menus pick up the new language:

```bash
docker compose exec -T php php bin/console app:seed-demo
# or your project’s make seed / bootstrap target
```

Admin can also edit labels under **Administration → Menus** / **Breadcrumbs** if you prefer not to rely on seeders alone.

---

## 5. Tests

Update locale-sensitive tests when the switcher or AuthKit paths change, for example:

- `tests/Identity/AccountLocaleRoutingTest.php` — asserts switcher forms for each enabled locale
- `tests/Identity/AuthKitBootstrapTest.php` — AuthKit `/en/…` smoke paths

Add at least one assertion that `/account/locale/{new}` (or the switcher form) exists for the new code.

Run:

```bash
make test
# ideally
make qa
```

---

## 6. Manual smoke checklist

1. Clear cache: `docker compose exec -T php php bin/console cache:clear`
2. Anonymous: open `/{locale}/login` and `/{locale}/register` (if first-user registration still open).
3. Locale switcher on AuthKit layout changes the path locale.
4. Sign in; switch locale from the header; confirm preference persists after reload (no `_locale=` in the URL).
5. Account → Display language list includes the new locale.
6. Cookie consent modal shows translated copy for the new locale (or English fallback).
7. Spot-check a dense screen (Issues filters, Project Settings, Admin hub).

---

## 7. Documentation when shipping

- Update the **Current locales** table in this file.
- Mention the new locale in `docs/CHANGELOG.md` and, if operators must re-seed menus, `docs/UPGRADING.md`.
- Keep `docs/CONTRIBUTING.md` pointing here for the full procedure.

---

## Design rules (do not skip)

- **English** remains the source of truth for docs, specs, PHPDoc, and the default UI catalogue.
- Prefer **endonyms** in `locale.{code}` (e.g. `Deutsch`, `Español`, `Polski`).
- Do not hand-roll a second i18n stack; use Symfony Translator + AuthKit dual URLs (`in_path: both` / `unlocalized: serve`) + setup `LocalizedPublicPath` + guest session locale + account preference (no `_locale` on dashboard paths).
- Prefer shipping complete `messages.{locale}.yaml` catalogues (key parity with English); translator `fallbacks: [en]` covers gaps only as a safety net.
- Legal / cookie UX: when adding locales, translate consent catalogues and keep [`LEGAL-AND-COOKIES.md`](LEGAL-AND-COOKIES.md) operator placeholders in English for docs.

## Related files (quick index)

```text
config/packages/translation.yaml
config/packages/nowo_auth_kit.yaml
config/packages/nowo_cookie_consent.yaml
config/packages/nowo_breadcrumb_kit.yaml
config/packages/nowo_dashboard_menu.yaml
config/packages/security.yaml
config/packages/twig.yaml
src/Shared/Locale/LocalizedPublicPath.php
src/Shared/Locale/BarePublicLocaleRedirectController.php
src/Identity/Controller/AccountLocaleController.php
src/Identity/Controller/GuestLocaleController.php
src/Identity/EventSubscriber/UserPreferredLocaleSubscriber.php
src/Identity/EventSubscriber/GuestSessionLocaleSubscriber.php
src/Shared/Menu/DashboardMenuDemoSeeder.php
src/Shared/Breadcrumb/BreadcrumbDemoSeeder.php
templates/_locale_switcher.html.twig
translations/messages.*.yaml
translations/NowoAuthKitBundle.*.yaml
translations/NowoCookieConsentBundle.*.yaml
translations/AutocompleteBundle.*.yaml
```
