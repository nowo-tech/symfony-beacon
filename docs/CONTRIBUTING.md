# Contributing

1. Follow Spec-Driven Development (see `.specify/memory/constitution.md`).
2. Open or update a feature under `specs/NNN-name/` before large changes.
3. Prefer official [`nowo-tech/*`](https://packagist.org/packages/nowo-tech/) kits (AuthKit, UserKit, AuditKit, cookie consent, …) over reinventing auth/user/legal UX — see `.cursor/rules/nowo-tech-kits-and-legal.mdc`.
4. Keep application code FrankenPHP **worker-safe** (`docs/ops/FRANKENPHP-CODING.md`).
5. Read [docs/ARCHITECTURE.md](ARCHITECTURE.md) before proposing structural changes (modular Symfony vs DDD, ingest vs UI boundaries). **`Shared` is presentation / instance config only** — put domain enums, domain demo seeders, and write-path orchestration in the owning module (`Issues`, `Project`, `Ingest`, …). Instance ops overview, retention purge, and metrics collection live under `Ops` (`085-architecture-convergence`). Platform/sample seeders live under `Setup\Demo` (see `083-module-boundaries`). **Do not** reintroduce project-admin HTTP under `Identity` — `AdminProjectController` belongs in `Project`.
6. Add PHPUnit coverage for behavior changes. PHPUnit lives under `tests/Unit/` (pure `TestCase`), `tests/Functional/` (HTTP / `DatabaseWebTestCase` browser), and `tests/Integration/` (kernel + DB/commands/services without HTTP). Shared helpers are in `tests/Support/`. Analytics and Performance suites stay in the default run (`make test` / CI `vendor/bin/phpunit`) — do not exclude those modules. For a local HTML/Clover report: `make test-coverage` (writes `var/coverage/clover.xml` and `var/coverage-html/`). CI runs a separate **Coverage** job (PCOV) that uploads those artifacts, and includable `src/` is gated at `COVERAGE_MIN=100`. Use local overrides such as `COVERAGE_MIN=0 make test-coverage` only while diagnosing gaps; the expected end state is 100% statement coverage for the includable tree documented in [docs/COVERAGE.md](COVERAGE.md). Frontend unit tests (Vitest + jsdom) live next to sources as `assets/**/*.test.ts`; run `make test-unit-js` or `make test-unit-js-coverage` (→ `var/coverage-js/`). Browser E2E: `make test-e2e` after `make up` + `make seed` (+ `make seed-sample` for issue/performance coverage) mutates the dogfood MySQL schema. Prefer `make up-e2e && make ready-e2e && make test-e2e-isolated` locally (`app_e2e` / `:9460`) so development data stays clean — see `e2e/README.md`. CI also runs a Playwright job (`PLAYWRIGHT_REQUIRE_SAMPLE=1`). See `e2e/README.md`.
7. Frontend: TypeScript + SCSS + Tailwind 4 under `assets/` (do not put Tailwind `@apply` inside SCSS).
8. Run `make test` (and ideally `make qa`) before opening a PR. CI also runs **Gitleaks** (`make secrets-scan`) and fails if committed secrets are detected — never commit `.env`, Halite keys, or real API tokens (see [SECURITY.md](../SECURITY.md)).
9. English only for **docs**, **specs**, and **PHPDoc**. User-facing UI may be translated (see [Internationalization](#internationalization)); keep the default locale `en`.
10. Public-facing UI must include legal pages and cookie consent (`docs/product/LEGAL-AND-COOKIES.md`, `nowo-tech/cookie-consent-bundle`) when adding cookies, analytics, or marketing surfaces.
11. Dependency bumps: preview with `make composer-outdated` ([`nowo-tech/composer-update-helper`](https://packagist.org/packages/nowo-tech/composer-update-helper)); apply with **`make update-deps`** (`generate-composer-require.sh --run` rewrites exact pins, then `composer update` + `pnpm update`). Do not expect `composer update` alone to move exact pins. Symfony Flex `extra.symfony.require` stays `8.1.*`.
12. New Doctrine migrations MUST use [`nowo-tech/migrations-kit-bundle`](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) MDK definitions (`CreateTablesService` + `AppliesMdkDefinition` / `migrations/FieldDictionary/`). Prefer idempotent declarative tables/columns over raw `CREATE TABLE` SQL.
13. Use GitHub issue / PR templates under `.github/`. Report vulnerabilities via [SECURITY.md](../SECURITY.md) (private advisory), never as a public issue.
14. **Kit Twig overrides** — prefer host `layout_template` shells under `templates/kit/` over copying full dashboard/admin pages into `templates/bundles/Nowo*Bundle/`. When a page must diverge, extend the bundle original with `{% extends '@Nowo…/…' %}` and override blocks only (Password Strength is the reference). Full forks of `dashboard/*.html.twig` / `admin/*.html.twig` break on kit upgrades. Kit shells must wrap with `body_before` / `body_after` (not `body`) because kit pages also define `{% block body %}`. Avoid `@!Nowo…` bang namespaces — kit TwigPathsPass does not register them.
15. **Env defaults** — do **not** add `env(VAR_NAME): '…'` entries in `config/parameters.yaml` (constitution Principle IX). Put defaults in `.env.dist` (app / dogfood) and `.env.e2e.dist` (isolated Playwright); never commit `.env.local` or `.env.e2e.local`; use `when@…` package YAML for env-specific overrides; typed `%env(…)%` aliases remain fine.
16. **Symfony forms (host + kit forks)** — **all** host forms use FormKit ([REQ-FORMKIT-001](../../OTHER_FULL_SPECS_DETAILS.md#REQ-FORMKIT-001) in `other/` specs). Do not hand-roll HTML forms in Twig; use `form_start` / `form_end` with Form Types. Every host Form Type in `src/**/Form/` MUST extend `App\Shared\Form\FormKitAbstractType` (→ vendor FormKit; use `withBuilder` / `add*Field` / `mergeFieldOptions`; call `parent::__construct` when adding a custom constructor). GET list filters MUST extend `App\Shared\Form\AbstractGetFilterType` (extends kit base + dashboard helpers; profile `filter`). CSRF-only POSTs use FormKit `Nowo\FormKitBundle\Form\Type\CsrfOnlyType` (+ `CsrfOnlyFormFactory` / `csrf_action_form()`), and GET search forms should use `search_query_form()` / `AdminSearchType`. Paint fields with `{% include 'form/_fields.html.twig' %}` / `form_row` (Type order; skip already rendered) — prefer that over hand-rolled `form_widget` + `form_help`. Put `attr` / `row_attr` on the Form Type; put labels/placeholders/help in `translations/form.*.yaml` via FormKit auto (profiles `beacon` / `filter` — see `081` FR-003a / FR-003c). After custom layout `form_row`/`form_widget` calls, still include the loop before submit actions (`specs/077-form-type-field-loop/`). Kit forks under `templates/bundles/` MUST follow the same rule (`REQ-TWIG-005`). AuthKit magic-login confirm uses Form CSRF via `MagicLoginConfirmType` (AuthKit ≥ 1.17).
17. **Shared Twig shells** — prefer includes over cloning chrome: confirm modals → `shared/_confirm_dialog.html.twig`; admin users/groups/projects lists → `admin/_list_page.html.twig`; dashboard feed panels → `dashboard/_feed_layout.html.twig` (`specs/086-dry-refactor/`). Pass optional vars with `|default` for `strict_variables`. Confirm dialogs MUST paint `{% block form %}` with Symfony `form_start` / `form_end` (FormKit Types) and explicit `confirm-dialog__header` / `__content` / `__actions` chrome — the shell no longer emits a hand-rolled HTML form. For `open_on_connect`, pass a boolean (or omit); never emit an empty `data-confirm-dialog-open-on-connect-value` (Stimulus Boolean treats empty as true — `086` FR-003c). Modal scrims MUST stay black (not `--color-ink`); rebuild assets after SCSS backdrop changes (`086` FR-003d).
18. **Platform checkboxes** — native `input[type=checkbox]` must use class `checkbox` (or live under `.confirm-dialog__check` / `.checkbox-label` / `.nowo-ui-check`) so moss/sand/surface tokens apply; do not rely on browser `accent-color` or Tailwind `text-*` alone. FormKit profiles set `field_types.checkbox.attr.class: checkbox`. Rebuild assets after SCSS changes (`make vite-build`).
19. **Password toggle** — layout lives in `_components.scss` (`.input-group.form-password-toggle`: flex, eye on the right, `gap: 0.5rem`). Keep `nowo_password_toggle.button_classes` including `input-group-text`; do not put Tailwind `flex`/`gap-*` on `toggle_container_classes` (they fight host SCSS). Icon show/hide uses `is-password-visible` (PasswordToggleBundle ≥2.1.1 CSS + host SCSS mirror for pages that do not link `toggle_password.css`). Host form theme `_toggle_password_csp.html.twig` + Stimulus `password-toggle` remain required: prod `script-src` has no `'unsafe-inline'`, so the bundle’s inline `onclick` cannot run. Password-manager extensions (NordPass, etc.) may inject icons on focus — that is not a Beacon layout bug.
20. **Compose / Make** — `make ensure-up` starts the stack with `docker compose up -d` when `php` does not respond to `exec` (no `--build`, no Vite). Exec-based targets (`test`, `phpstan`, `shell`, `console`, `cs-fix`, …) depend on it. Use `make up` for first boot / rebuild + asset build. The Makefile sets `MAKEFLAGS += --no-print-directory` so recursive `$(MAKE)` does not spam `Entering/Leaving directory`.
21. **Module boundaries (REQ-BP-004)** — run `make check-module-boundaries` (also part of `make qa` / CI). The script under `.scripts/check-module-boundaries.sh` fails if project-admin HTTP leaves `Project`, Identity imports `ProjectMembershipManager` / `ProjectCreationFormFactory`, OTLP leaves `Ingest/Otlp`, Metrics leave `Ops`, or Shared owns Issues/Performance write-path types. Prefer Ports/Adapters for cross-module triggers (`083` / `085` / `089`). Large Doctrine collections use `EXTRA_LAZY`; user/text `LIKE` queries use `App\Shared\Doctrine\SqlLikeEscaper` with `ESCAPE`.

## Kit Twig strategy (nowo-tech)

| Bundle | What Beacon customizes | What must stay out of git forks |
|--------|------------------------|----------------------------------|
| Dashboard Menu | `kit/menu_dashboard_layout.html.twig` + `dashboard.css_framework: tailwind`; restyle via `kit/_kit_admin_styles.html.twig` (`.kit-admin` / `--nowo-ui-*`) | Host forks `dashboard/base` + `index|show|show_items_reorder`: Administration chrome (`panel`, `kit_admin_header_actions`, sand/ink tables); row actions `btn-*` + `_action_inner` (`nowo_ui_kit.row_actions_display: text`); kit modal/JS hooks kept |
| Breadcrumb Kit | `kit/breadcrumb_dashboard_layout.html.twig` + same (`tailwind`); hub intro via `messages` | Host forks `dashboard/base` + `collection/index` + `item/index` (same Administration chrome / text row actions; wider actions column `min-w-*` + nowrap cluster; `btn-bk-*` hooks) |
| RoutingKit | `kit/routing_kit_panel_layout.html.twig` + `web_ui.css_framework: tailwind` | Host forks `panel/base` + `index` + `form` (header CTAs, panel table/form, Import modal, text row actions; no double `nowo-ui.css`) |
| Cookie Consent | `kit/cookie_consent_admin_layout.html.twig` + admin `web_ui.css_framework: tailwind`; modal = **vendor** `nowo-cookie-consent.css` (`ui_theme: tailwind`, CookieConsent ≥1.9) | Prefer layout-only; **current host forks** area/section tabs (`shared/_tabs`), `config/settings`, cookie definition `index`/`form`, `_pagination` / `_table`, and `admin/base` (kit remaps after `nowo-ui.css`) until upstream drops `bootstrap_5_layout` |
| SiteBackup | `kit/site_backup_*_layout.html.twig` + root `css_framework: tailwind`; restyle via `.nowo-site-backup-*` / `--nowo-ui-*` | Prefer layout-only; **current host forks** `panel/index` + `history` |
| Http Log | `kit/http_log_admin_layout.html.twig` + `web_ui.css_framework: tailwind`; pages fill `{% block nowo_ui_content %}` | Host overrides `admin/index|_filter|show`: header export/purge; results `panel` + sand/ink table + text row actions; **filters** = Issues/admin in a `panel` (`.input` + `btn-ghost`, placeholders, no labels) |
| AuthKit | `layout.html.twig` (`auth_brand` + guest_shell); YAML `css` / `form_theme` / `outbound_mail_ready_checker` | Thin `security/*` forks only for FormKit `form/_fields` painting + brand title (prefer upstream blocks; no full page rewrites) |
| PWA | YAML `install_prompt.*` button classes + `_components.scss` BEM chrome; host hint beside `nowo_pwa_install_links()` | Prefer **no** `install_links` fork; keep `install_prompt` only while brand mark needs Twig (YAML first) |

**Thin SCSS theme bridges (not Twig forks):** Cookie Consent `_cookie_consent.scss` (footer/position, `103`); PhoneInput `_phone_input.scss` imported from `app.scss` (kit Bootstrap `--bs-*` → Beacon `--color-*` / `data-theme`, including the portaled prefix dropdown). Do not re-fork kit picker JS or widget Twig. FrankenPHP hot reload is `nowo-tech/hot-reload-bundle` ≥**1.4.0** (`config/packages/nowo_hot_reload.yaml` nonce only; `nowo:hot-reload:check`) — no host Vite/Twig Idiomorph client.

**FormKit profiles:** `config/packages/nowo_form_kit.yaml` defines `beacon`, **`filter`** (product GET lists), plus kit profiles (`auth_kit`, `dashboard_menu`, `breadcrumb_kit`, `http_log`, `cookie_consent`, `routing_kit`). Host profile **`beacon`** sets `translation_domain: form` with **`auto_help` / `auto_placeholder: true`** (and auto labels). Profile **`filter`** is label-less with the same auto placeholder/help catalogue. Host forms extend `App\Shared\Form\FormKitAbstractType` (or `AbstractGetFilterType` for GET filters) so FormKit merger + Symfony inheritance stay on the `form` catalogue (`translations/form.*.yaml`). **Always use translation keys** in Form Types / Twig (`admin_user.email.placeholder`, …) — never hard-coded locale prose, and never pass `|trans` output into a field that still has a translation domain (double-translate). Do **not** set `translation_domain: messages` on host field chrome — put labels/placeholders/help in `form.*.yaml`. Twig paints with `form_row` + `form/_fields.html.twig` (`077` / `081` FR-003c). **Choice options:** default `choice_translation_domain: form` (keys in `form.*.yaml`). Literal / DB / entity labels (users, groups, projects, autocomplete `choice_label`) → `choice_translation_domain: false` only. Kit profiles keep kit catalogues + `auto_help` / `auto_placeholder: false`. Profile `field_types.checkbox.attr.class` is **`checkbox`**. Details: `.cursor/rules/formkit-profiles.mdc`, `specs/081-formkit-uikit-kit-sync/`.

**UiKit shared chrome:** product shell uses `@NowoUiKitBundle/partials/_shell_open` / `_shell_close` from `base.html.twig` (Beacon slots for brand / aside / header). Product + kits share `kit/_pagination.html.twig` → `@NowoUiKitBundle/partials/_pagination` (UiKit **1.7+** ships `« »` + page numbers; host override adds Beacon `table-pagination` classes). Product tabs use `shared/_tabs.html.twig` → UiKit `_tabs`. Flashes → UiKit `_toasts`; page loader embeds UiKit `_thinking_orb`. Host `nowo_ui_kit.row_actions_display: text` for kit table actions (Administration forks use `btn-sm btn-label` + `_action_inner`; kit CSS must not square-crush labels).

**Tailwind host rule:** kit admin UIs use `css_framework: tailwind` + `.kit-admin` token remap (not Bootstrap). Prefer Administration chrome on host forks: explicit `panel` + sand/ink tables + `btn-primary` / `btn-ghost` / `btn-danger` + `kit_admin_header_actions` (do not rely on `data-kit-split-filters` when the page already wraps results in `panel`). Kit dashboard `base` overrides MUST NOT re-append vendor `nowo-ui.css` after app CSS. Surfaces still on UiKit macros remap `nowo-ui-btn--*` / `bg-blue-600` → moss. Vite `kit-admin` is CSP boot / modal portals / optional split-filters only — **do not** import Bootstrap. Host kit `<style>` blocks need `csp_nonce()`. See OTHER_FULL_SPECS `REQ-MENU-001` / `REQ-TWIG-APP-001` and `specs/081-formkit-uikit-kit-sync/`.

After upgrading kits: `composer update`, `bin/console assets:install`, `bin/console cache:clear`, then smoke menu/breadcrumb/cookie **modal + admin**, AuthKit login, SiteBackup setup, PWA install prompt, `/admin/_routing/`, `/admin/http-log`. Fast AuthKit regression suite: `make kit-smoke` (login bootstrap, magic login, password reset, login throttle).

### Local kit development (path repositories)

When iterating on a sibling kit under `repositories/bundles/`, prefer a **Composer path repository** over editing `vendor/`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../bundles/CookieConsentBundle",
      "options": { "symlink": true }
    }
  ]
}
```

In Docker, also mount that path into the PHP service (Composer resolves relative to `/app`). Use path only while developing; remove before release so Packagist pins stay authoritative. Host apps should keep customizing via `layout_template` / CSS / block extends — push reusable hooks **upstream into the kit** instead of growing Beacon forks.

## Documentation map

See the categorized index: [README.md](README.md).

| Doc | Audience |
|-----|----------|
| [INSTALL.md](INSTALL.md) | First install + seed layers (platform / demo / sample) |
| [README.md](../README.md) | Product overview + quick start |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Why modular Symfony / flows |
| [ROLES.md](product/ROLES.md) | Instance `ROLE_*` vs project membership roles |
| [API.md](API.md) | Ingest, health, OpenAPI pointer |
| [DSN.md](DSN.md) | Client DSN + auth |
| [NOTIFICATIONS.md](product/NOTIFICATIONS.md) | Outbound channels |
| [PRODUCTION.md](PRODUCTION.md) | Self-host ops |
| [ROADMAP.md](ROADMAP.md) / [CHANGELOG.md](CHANGELOG.md) / [UPGRADING.md](UPGRADING.md) | Plan / history / upgrades |
| [LEGAL-AND-COOKIES.md](product/LEGAL-AND-COOKIES.md) / [ADDING-LOCALES.md](dev/ADDING-LOCALES.md) | Compliance / i18n |
| `specs/NNN-*` | Feature SDD artifacts |

## Issues and pull requests

**Issues** (chooser on “New issue”):

| Template | When |
| --- | --- |
| Bug report | Something broken in the self-hosted server |
| Feature request | Product / DX improvement |
| Documentation | Docs / specs / README gaps |
| Question / support | How-to / ops configuration |
| CI / Docker / ops | Actions, images, Compose, Makefile |
| Security | Use the private advisory link (not a public issue) |

**PRs:** default [`.github/PULL_REQUEST_TEMPLATE.md`](../.github/PULL_REQUEST_TEMPLATE.md), plus typed templates under [`.github/PULL_REQUEST_TEMPLATE/`](../.github/PULL_REQUEST_TEMPLATE/) (`bugfix`, `feature`, `docs`, `chore`). Pick one via the compare URL, e.g. `?template=feature.md`. Reviewers come from [`.github/CODEOWNERS`](../.github/CODEOWNERS).

## Git hygiene

**Constitution Principle X:** do not attribute work to Cursor Agent (or similar IDE agents) in **commits**, **GitHub issues**, or **pull / merge requests**.

Forbidden examples: `Co-authored-by: Cursor`, `Co-authored-by: Cursor Agent`, `*@cursor.com` / `*@cursor.so` trailers, `Made-with: Cursor` / `Made with Cursor` in commit messages, issue text, or PR/MR bodies. Use only the human author’s git / GitHub identity.

Run once per clone:

```bash
make setup-hooks
```

This points `core.hooksPath` at `.githooks/`, which strips Cursor `Co-authored-by` / `Made-with` trailers from commit messages.

CI enforces the same rule on every push/PR via the **Git hygiene** job (`.scripts/check-no-cursor-coauthor.sh` on full `HEAD` history). A PR that introduces (or sits on history that already has) those trailers will fail.

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

Public AuthKit URLs use **dual paths** controlled by `DEFAULT_LOCALE` (`locale.in_path: both` + `unlocalized: serve`). Cold-start setup uses SiteBackup **≥ 1.7.0** with the same dual model (`setup.locale`):

| Context | Paths / behaviour |
| --- | --- |
| Default locale (bare) | `/login`, `/register`, `/logout`, `/setup` serve that locale |
| Other locales | `/{locale}/login`, `/{locale}/register`, `/{locale}/setup`, … |
| Setup / backup | `/setup` + `/{locale}/setup`; panel `/_site_backup` (SiteBackupBundle) |
| Legal | Bare `/legal/…` redirects to `/{DEFAULT_LOCALE}/legal/…` |
| App shell | `/dashboard`, `/account/…`, `/projects/…` (locale from account preference; no `_locale` in path) |
| RoutingKit (optional app routes) | `/admin/_routing/` admin; `#[Routable]` dual paths (`064`) |
| HTTP error preview | `/_error/{404\|403\|500}` **dev only** (`063`) |

Guest language: path switcher or `GET|POST /locale/{locale}` (session). Signed-in users: `preferredLocale` via `POST /account/locale/{locale}`. Enabled locales: `en`, `es`, `de`, `nl`, `fr`, `it`, `pt`. `.env.dist` defaults to `en`; this project’s local `.env` may use `es`.

**Full operator/developer manual:** [ADDING-LOCALES.md](dev/ADDING-LOCALES.md) (enable config lists, catalogues, security regexes, seeders, tests, smoke checklist).

## HTTP error pages

Branded Twig overrides live under `templates/bundles/TwigBundle/Exception/` (`error404`, `error403`, `error500`) with art in `public/illustrations/` and the mascot in `public/brand/mascot.png`.

Preview routes are registered **only when `APP_ENV=dev`** (not `test` / `prod`):

| Code | URL |
| --- | --- |
| 404 | `https://localhost:9447/_error/404` |
| 403 | `https://localhost:9447/_error/403` |
| 500 | `https://localhost:9447/_error/500` |

With `APP_DEBUG=0` (any env), real missing routes / access denials / exceptions still use the same templates; `/_error/*` itself is unavailable outside `dev`.

### Catalogue layout

| Domain / files | Purpose |
| --- | --- |
| `translations/messages.{locale}.yaml` | App UI strings (nav, locale switcher labels, AuthKit page chrome, activity actions, choice labels) **and** password-strength requirement/generator strings when `PasswordStrengthType` uses `translation_domain: messages` (e.g. `/account/security`) |
| `translations/form.{locale}.yaml` | FormKit field catalogues (`{form}.{field}.label\|placeholder\|help`). Beacon profile default domain (`nowo_form_kit.profiles.beacon`) |
| `translations/NowoAuthKitBundle.{locale}.yaml` | AuthKit label overrides **and** password-strength requirement/generator strings (AuthKit sets its translation domain on those fields) |
| Bundle catalogues in vendor | AuthKit / PasswordStrength / … defaults; override in `translations/` when needed |

Ship complete `messages.*.yaml` catalogues for every enabled locale (key parity with English). Missing keys still fall back to `en` via translator `fallbacks`.

Twig: use `|trans` / `trans` with the right domain. HTML documents keep `lang="{{ app.request.locale|default('en') }}"`. The locale switcher loops Twig global `enabled_locales`.

Password-strength and password-toggle UX strings live under the AuthKit domain overrides described above when those kits are enabled; prefer reusing vendor wording from `PasswordStrengthBundle.{locale}.yaml` rather than inventing new copy.
