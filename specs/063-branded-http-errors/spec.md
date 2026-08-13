# Feature Specification: Branded HTTP error pages

**Feature Branch**: `063-branded-http-errors`  
**Created**: 2026-07-31  
**Status**: Implemented (2026-07-31; **503 + extended codes** follow-up 2026-08-13)

**Input**: Ship operator-friendly branded HTTP error HTML with Beacon mascot illustrations, calm English-first copy (catalogue parity for enabled locales), and a light guest chrome that still works when the DB or heavy services fail. Preview routes MUST exist only in `APP_ENV=dev`.

## User Scenarios

### US1 — Missing route (P1)

As a guest or member who opens an unknown URL, **When** `APP_DEBUG=0` (or the error controller renders the Twig override), **Then** I see the branded 404 page with `public/illustrations/error-404.png`, a short explanation, and a home CTA.

### US2 — Access denied (P1)

As a user who hits a forbidden surface, **When** the app returns 403, **Then** I see the branded 403 page with `error-403.png`, a sign-in CTA, and a home CTA.

### US3 — Server failure (P1)

As any visitor, **When** an unhandled exception becomes a 500 response (debug off), **Then** I see the branded 500 page with `error-500.png` and calm “try again later” copy (no stack traces).

### US4 — Dev preview (P2)

As a developer with `APP_ENV=dev`, **When** I open `/_error/{code}` for a supported status, **Then** I preview the same Twig overrides without disabling debug globally. Those routes MUST NOT be registered in `test` or `prod`.

### US5 — Maintenance / unavailable (P1) — follow-up

As any visitor, **When** the app returns **503** (Symfony error controller) **or** site-wide maintenance is enabled (`092-maintenance-mode`), **Then** I see branded art from `public/illustrations/error-503.png` (mascot + “UNDER MAINTENANCE” scene). The maintenance public page MAY use kit-owned copy from `NowoMaintenanceModeBundle` while reusing the same illustration asset and error-page chrome.

### US6 — Other client / gateway codes (P2) — follow-up

As a visitor, **When** the app returns **400**, **401**, **408**, **429**, or **502**, **Then** I see the matching branded Twig override and `public/illustrations/error-{code}.png` (same layout as US1–US3).

## Requirements

- **FR-001**: Twig overrides under `templates/bundles/TwigBundle/Exception/` (`error.html.twig`, `error{code}.html.twig` for supported codes) MUST extend a light `templates/error/layout.html.twig` (theme toggle + Vite app CSS; no cookie-consent render / no dashboard shell).
- **FR-002**: Illustrations MUST live at `public/illustrations/error-{code}.png` for supported codes: **400, 401, 403, 404, 408, 429, 500, 502, 503**. Mascot source art remains `public/brand/mascot.png`; mark PNG at `public/brand/beacon-mark.png` (vector mark remains `beacon-mark.svg`).
- **FR-003**: `messages.*` keys under `error.{code}.*` (at least `title`, `lead`, `hint`, `image_alt`) MUST have parity across enabled locales (EN source of truth). Copy MUST inform calmly (no blame, no urgency panic) and MAY include a light humorous `hint` line.
- **FR-004**: Framework `_errors` import (`prefix: /_error`) MUST be `when@dev` only. Security `access_control` MAY allow `^/_error` (and maintenance preview) as `PUBLIC_ACCESS` so previews are anonymous in dev.
- **FR-005**: Setup/restore gates MUST exclude `/_error` so catalog-empty redirects do not steal previews.
- **FR-006**: Automated tests MAY render Twig overrides directly (test env has no `/_error` routes) and MUST assert published assets exist (including `error-503.png`).
- **FR-007**: The public maintenance page (`092`) MUST use `illustrations/error-503.png` as its hero figure (same asset as `error503.html.twig`), not a separate hand-rolled SVG.

## Out of Scope

- PWA offline branded art (optional follow-up).
- Replacing Symfony profiler exception pages while `APP_DEBUG=1` on non-preview URLs.
- Translating illustration text baked into PNGs (numbers / “UNDER MAINTENANCE” are visual only; UI strings are Twig/i18n).
- Maintenance enable/disable UX and admin panel (see `092-maintenance-mode`).
