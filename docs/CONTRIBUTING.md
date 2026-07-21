# Contributing

1. Follow Spec-Driven Development (see `.specify/memory/constitution.md`).
2. Open or update a feature under `specs/NNN-name/` before large changes.
3. Prefer official [`nowo-tech/*`](https://packagist.org/packages/nowo-tech/) kits (AuthKit, UserKit, AuditKit, cookie consent, …) over reinventing auth/user/legal UX — see `.cursor/rules/nowo-tech-kits-and-legal.mdc`.
4. Keep application code FrankenPHP **worker-safe** (`docs/FRANKENPHP-CODING.md`).
5. Read [docs/ARCHITECTURE.md](ARCHITECTURE.md) before proposing structural changes (modular Symfony vs DDD, ingest vs UI boundaries).
6. Add PHPUnit coverage for behavior changes. Analytics (`tests/Analytics/`) and Performance (`tests/Performance/`) access tests are part of the default suite (`make test` / CI `vendor/bin/phpunit`) — do not exclude those directories.
7. Frontend: TypeScript + SCSS + Tailwind 4 under `assets/` (do not put Tailwind `@apply` inside SCSS).
8. Run `make test` (and ideally `make qa`) before opening a PR.
9. English only for **docs**, **specs**, and **PHPDoc**. User-facing UI may be translated (see [Internationalization](#internationalization)); keep the default locale `en`.
10. Public-facing UI must include legal pages and cookie consent (`docs/LEGAL-AND-COOKIES.md`, `nowo-tech/cookie-consent-bundle`) when adding cookies, analytics, or marketing surfaces.
11. Dependency bumps: run `make composer-outdated` ([`nowo-tech/composer-update-helper`](https://packagist.org/packages/nowo-tech/composer-update-helper)) and apply suggested exact pins carefully (Symfony Flex `extra.symfony.require` stays `8.1.*`).
12. New Doctrine migrations MUST use [`nowo-tech/migrations-kit-bundle`](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) MDK definitions (`CreateTablesService` + `AppliesMdkDefinition` / `migrations/FieldDictionary/`). Prefer idempotent declarative tables/columns over raw `CREATE TABLE` SQL.
13. Use GitHub issue / PR templates under `.github/`. Report vulnerabilities via [SECURITY.md](../SECURITY.md) (private advisory), never as a public issue.

## Pull requests

PRs use [`.github/PULL_REQUEST_TEMPLATE.md`](../.github/PULL_REQUEST_TEMPLATE.md). Requested reviewers come from [`.github/CODEOWNERS`](../.github/CODEOWNERS).

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

AuthKit pages use Symfony Translator with **locale in the URL path**. Default and fallback locale is `en`. Current enabled locales: `en`, `es`, `de`, `nl`, `fr`, `it`, `pt`.

| Locale | Example paths |
| --- | --- |
| Default (`en`) | `/en/login`, `/en/register`, `/en/logout` |
| Other (`es`, `de`, `nl`, `fr`, `it`, `pt`, …) | `/es/login`, `/de/login`, … |

Bare `/`, `/login`, `/register`, and `/logout` redirect to the default-locale AuthKit paths (`config/routes/auth_locale_redirects.yaml`). Authenticated app home is `/dashboard`.

**Full operator/developer manual:** [ADDING-LOCALES.md](ADDING-LOCALES.md) (enable config lists, catalogues, security regexes, seeders, tests, smoke checklist).

### Catalogue layout

| Domain / files | Purpose |
| --- | --- |
| `translations/messages.{locale}.yaml` | App UI strings (nav, locale switcher labels, AuthKit page chrome) **and** password-strength requirement/generator strings when `PasswordStrengthType` uses `translation_domain: messages` (e.g. `/account/security`) |
| `translations/NowoAuthKitBundle.{locale}.yaml` | AuthKit label overrides **and** password-strength requirement/generator strings (AuthKit sets its translation domain on those fields) |
| Bundle catalogues in vendor | AuthKit / PasswordStrength / … defaults; override in `translations/` when needed |

Twig: use `|trans` / `trans` with the right domain. HTML documents keep `lang="{{ app.request.locale|default('en') }}"`. The locale switcher loops Twig global `enabled_locales`.

Password-strength and password-toggle UX strings live under the AuthKit domain overrides described above when those kits are enabled; prefer reusing vendor wording from `PasswordStrengthBundle.{locale}.yaml` rather than inventing new copy.
