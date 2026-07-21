# Feature Specification: Setup Wizard UI

**Feature Branch**: `056-setup-wizard`  
**Created**: 2026-07-21  
**Status**: Implemented (platform-empty auto-redirect + required platform step + default-locale bare URLs — 2026-07-21)  

**Input**: After CLI seed layers (`055`), add a light admin UI that runs platform / optional sample seed steps and can be dismissed. Complements AuthKit first-user register; does not replace Docker/Compose install.

## User Scenarios

### US1 — Forced setup when catalogs are empty (P1)

As an operator on a fresh (or wiped) instance, any HTML page redirects to the locale-aware setup URL while menus, breadcrumbs, or cookie consent are missing (except setup itself, assets, health, AuthKit login/register, legal, cookie-consent, and ingest API). For `DEFAULT_LOCALE`, that URL is bare `/setup`; for any other enabled locale it is `/{_locale}/setup`.

### US2 — Required platform then optional steps (P1)

As an anonymous or admin operator on setup, I must install platform catalogs first. After that I can register the first admin via AuthKit, optionally run Full sample load, then mark setup complete.

### US3 — Existing instances skip the wizard (P1)

As an operator upgrading from a prior release, I do not see the setup wizard by default (migration marks setup complete) when platform catalogs already exist.

### US4 — Discoverability (P2)

As an admin who has not finished setup, I see a banner on the dashboard and a card on the admin hub linking to setup (locale-aware public URL via `LocalizedPublicPath` / Twig helpers).

### US5 — Guest locale on setup (P2)

As an anonymous operator on first boot, I can switch language on the setup guest shell without leaving the wizard. Examples when `DEFAULT_LOCALE=es`: `/setup` (Spanish), `/en/setup` (English); `/es/setup` redirects to `/setup`. When `DEFAULT_LOCALE=en`: `/setup` (English), `/es/setup` (Spanish); `/en/setup` redirects to `/setup`.

## Requirements

- **FR-001**: `instance_settings.setup_completed_at` nullable; null means wizard pending (dismissible after platform is present).
- **FR-002**: Upgrade migration sets `setup_completed_at` on existing singleton row so prod upgrades are not interrupted.
- **FR-003**: Setup URLs follow the dual public-path rule shared with AuthKit (`unlocalized: serve`): default locale is bare `/setup` (+ `/setup/run`); other locales use `/{_locale}/setup` (+ `/{_locale}/setup/run`). Prefixed URLs for the default locale redirect to the bare path. Access remains `ROLE_ADMIN` when users exist (anonymous bootstrap when no users); POST actions CSRF-protected; invoke same seeders as CLI (`055`).
- **FR-004**: Required action: platform seed. Optional: AuthKit register (link), Full sample load (`load` profile; ensures demo project), complete/dismiss. Admin mode may still run demo / sample `dev`.
- **FR-005**: UI catalogues under `translations/messages.{locale}.yaml` for every enabled locale (`en`, `es`, `de`, `nl`, `fr`, `it`, `pt`) keep key parity with English (translator fallback remains `[en]`). Docs pointer in INSTALL.md and ADDING-LOCALES.md; guests switch locale via path / guest locale endpoint.
- **FR-006**: Auto-redirect targets the bare or prefixed setup path for the current/request locale (see US1 exclusions and FR-003). Authenticated non-admins are not redirected (cannot access wizard).

## Out of Scope

- Replacing AuthKit register / Docker bootstrap.
- Running `huge` sample from the UI (CLI only with `--force`).
- Aligning every legal/cookie public page to bare-default dual URLs (AuthKit + setup already follow this pattern; legal may still redirect bare → prefixed until a follow-up).
