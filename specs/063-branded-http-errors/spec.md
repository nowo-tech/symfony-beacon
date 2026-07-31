# Feature Specification: Branded HTTP error pages

**Feature Branch**: `063-branded-http-errors`  
**Created**: 2026-07-31  
**Status**: Implemented (2026-07-31)  

**Input**: Ship operator-friendly 404 / 403 / 500 HTML pages with Beacon mascot illustrations, calm English-first copy (catalogue parity for enabled locales), and a light guest chrome that still works when the DB or heavy services fail. Preview routes MUST exist only in `APP_ENV=dev`.

## User Scenarios

### US1 — Missing route (P1)

As a guest or member who opens an unknown URL, **When** `APP_DEBUG=0` (or the error controller renders the Twig override), **Then** I see the branded 404 page with `public/illustrations/error-404.png`, a short explanation, and a home CTA.

### US2 — Access denied (P1)

As a user who hits a forbidden surface, **When** the app returns 403, **Then** I see the branded 403 page with `error-403.png`, a sign-in CTA, and a home CTA.

### US3 — Server failure (P1)

As any visitor, **When** an unhandled exception becomes a 500 response (debug off), **Then** I see the branded 500 page with `error-500.png` and calm “try again later” copy (no stack traces).

### US4 — Dev preview (P2)

As a developer with `APP_ENV=dev`, **When** I open `/_error/404`, `/_error/403`, or `/_error/500`, **Then** I preview the same Twig overrides without disabling debug globally. Those routes MUST NOT be registered in `test` or `prod`.

## Requirements

- **FR-001**: Twig overrides under `templates/bundles/TwigBundle/Exception/` (`error.html.twig`, `error404`, `error403`, `error500`) MUST extend a light `templates/error/layout.html.twig` (theme toggle + Vite app CSS; no cookie-consent render / no dashboard shell).
- **FR-002**: Illustrations MUST live at `public/illustrations/error-{404,403,500}.png`; mascot at `public/brand/mascot.png`; mark PNG at `public/brand/beacon-mark.png` (vector mark remains `beacon-mark.svg`).
- **FR-003**: `messages.*` keys under `error.*` MUST have parity across enabled locales (EN source of truth). Copy MUST inform calmly (no blame, no urgency panic) and MAY include a light humorous `hint` line.
- **FR-004**: Framework `_errors` import (`prefix: /_error`) MUST be `when@dev` only. Security `access_control` MAY allow `^/_error` as `PUBLIC_ACCESS` so previews are anonymous in dev.
- **FR-005**: Setup/restore gates MUST exclude `/_error` so catalog-empty redirects do not steal previews.
- **FR-006**: Automated tests MAY render Twig overrides directly (test env has no `/_error` routes) and MUST assert published assets exist.

## Out of Scope

- 503 maintenance / PWA offline / 429 branded art (optional follow-ups).
- Replacing Symfony profiler exception pages while `APP_DEBUG=1` on non-preview URLs.
- Translating illustration text baked into PNGs (numbers are visual only; UI strings are Twig/i18n).
