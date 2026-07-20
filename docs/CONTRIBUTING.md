# Contributing

1. Follow Spec-Driven Development (see `.specify/memory/constitution.md`).
2. Open or update a feature under `specs/NNN-name/` before large changes.
3. Prefer official [`nowo-tech/*`](https://packagist.org/packages/nowo-tech/) kits (AuthKit, UserKit, AuditKit, cookie consent, …) over reinventing auth/user/legal UX — see `.cursor/rules/nowo-tech-kits-and-legal.mdc`.
4. Keep application code FrankenPHP **worker-safe** (`docs/FRANKENPHP-CODING.md`).
5. Read [docs/ARCHITECTURE.md](ARCHITECTURE.md) before proposing structural changes (modular Symfony vs DDD, ingest vs UI boundaries).
6. Add PHPUnit coverage for behavior changes.
7. Frontend: TypeScript + SCSS + Tailwind 4 under `assets/` (do not put Tailwind `@apply` inside SCSS).
8. Run `make test` (and ideally `make qa`) before opening a PR.
9. English only for **docs**, **specs**, and **PHPDoc**. User-facing UI may be translated (see [Internationalization](#internationalization)); keep the default locale `en`.
10. Public-facing UI must include legal pages and cookie consent (`docs/LEGAL-AND-COOKIES.md`, `nowo-tech/cookie-consent-bundle`) when adding cookies, analytics, or marketing surfaces.

## Git hygiene

Run once per clone:

```bash
make setup-hooks
```

This points `core.hooksPath` at `.githooks/`, which strips Cursor `Co-authored-by` / `Made-with` trailers from commit messages.

Before push / release:

```bash
make check-no-cursor-coauthor
```

If history already contains forbidden trailers:

```bash
make strip-cursor-coauthor-from-history
# then: git push --force-with-lease origin main
# recreate/force-push affected tags if needed
```

The client Symfony bundle is **out of scope** for this repository.

## Internationalization

AuthKit pages use Symfony Translator with **locale in the URL path**. Default and fallback locale is `en`. Current enabled locales: `en`, `es`.

| Locale | Example paths |
| --- | --- |
| Default (`en`) | `/en/login`, `/en/register`, `/en/logout` |
| Other (`es`, …) | `/es/login`, `/es/register`, `/es/logout` |

Bare `/`, `/login`, `/register`, and `/logout` redirect to the default-locale AuthKit paths (`config/routes/auth_locale_redirects.yaml`). Authenticated app home is `/dashboard`.

### Catalogue layout

| Domain / files | Purpose |
| --- | --- |
| `translations/messages.{locale}.yaml` | App UI strings (nav, locale switcher labels, AuthKit page chrome) |
| `translations/NowoAuthKitBundle.{locale}.yaml` | AuthKit label overrides **and** password-strength requirement/generator strings (AuthKit sets its translation domain on those fields) |
| Bundle catalogues in vendor | AuthKit / PasswordStrength / … defaults; override in `translations/` when needed |

Twig: use `|trans` / `trans` with the right domain. HTML documents keep `lang="{{ app.request.locale|default('en') }}"`. The locale switcher loops Twig global `enabled_locales`.

### Adding a new language (example: `fr`)

1. **Enable the locale** in both places (keep lists in sync):
   - `config/packages/translation.yaml` → `framework.enabled_locales`
   - `config/packages/nowo_auth_kit.yaml` → `nowo_auth_kit.enabled_locales`
2. **Add catalogues** under `translations/`:
   - Copy `messages.en.yaml` → `messages.fr.yaml` and translate values.
   - Copy `NowoAuthKitBundle.en.yaml` → `NowoAuthKitBundle.fr.yaml` and translate values (include password-strength keys if that file has them).
   - Optionally override other bundle domains the same way (`VendorBundle.fr.yaml`).
3. **Switcher labels** — add `locale.fr: Français` (and keep `locale.nav`) in every `messages.*.yaml`. The switcher already loops `enabled_locales`.
4. **Security** — extend the public AuthKit path regex in `config/packages/security.yaml`, e.g. `^/(en|es|fr)/login` (same for `register` / `logout`).
5. **Smoke-check** — open `/en/login`, `/fr/login`, `/fr/register`, switch locales, submit forms, and confirm strength requirement strings are translated.
6. **Tests** — extend AuthKit/locale coverage if you add assertions for the new locale (see `tests/Identity/AuthKitBootstrapTest.php`).

Do not invent locale codes; use standard IETF language tags (`fr`, `de`, `pt_BR`, …) consistently in config, filenames, and catalogues.

Password-strength and password-toggle UX strings live under the AuthKit domain overrides described above when those kits are enabled; prefer reusing vendor wording from `PasswordStrengthBundle.{locale}.yaml` rather than inventing new copy.
