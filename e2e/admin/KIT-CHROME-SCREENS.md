# Kit chrome screen inventory (Beacon)

Scope: authenticated + guest **product UI** GET screens relevant to FormKit / tables / tabs / modals / panels.
Excluded on purpose: Envelope API, OTLP, Mercure, `_profiler`, `_wdt`, hooks webhooks, JSON exports, POST-only mutations.

## Covered in `kit-chrome-standardization.spec.ts` (baseline)

| Area | Paths |
| ---- | ----- |
| Admin hub / identity | `/admin`, `/admin/users`, `/admin/groups`, `/admin/projects`, creates `…/new`, `/admin/roles`, `/admin/permissions` |
| Ops settings | `/admin/mailer`, `/admin/appearance`, `/admin/instance-config`, `/admin/ops-defaults` |
| Kit admin | `/admin/http-log`, `/admin/menus/`, `/admin/_routing/`, `/admin/_routing/new`, `/breadcrumb-kit-admin/collections`, `/admin/cookie-consent`, `/admin/maintenance`, `/_site_backup` |
| Account | `/account/profile`, `/account/display`, `/account/security`, `/account/privacy`, `/account/display/notifications` |
| Project | `/projects/{uuid}/issues`, `/projects/{uuid}/settings` (+ portability / tabs) |
| Shell | `/dashboard` (background, user menu, new-project modal), `/login` |

## Gap-close batch (added smoke + CSS sampling)

| Area | Paths |
| ---- | ----- |
| Dashboard panels | `/dashboard/activity`, `/dashboard/alerts`, `/dashboard/assignments`, `/dashboard/mentions`, `/dashboard/summary`, `/dashboard/new-in-release` |
| Account remainder | `/account`, `/account/preferences`, `/account/projects`, `/account/groups`, `/account/security/activity`, `/account/security/devices`, `/account/security/history`, `/account/display/panels`, `/account/display/tours` |
| Admin remainder | `/admin/ops`, `/admin/social-login`, `/admin/social-login/new`, `/admin/permissions/new`, `/breadcrumb-kit-admin/collections/new`, `/_site_backup/history`, menus **new** via `#modal-menu-new` on `/admin/menus/` (GET `/admin/menus/menu/new` is modal partial only) |
| Project remainder | `/projects/{uuid}`, `/projects/{uuid}/analytics`, `/projects/{uuid}/performance`, `/projects/{uuid}/releases`, `/projects/{uuid}/notifications/new`, `/projects/{uuid}/threshold-rules/new`, `/projects/new` |
| Guest / legal | `/register`, `/reset-password`, `/login/magic`, `/login/qr`, `/en/legal/privacy`, `/en/legal/terms`, `/en/legal/cookies`, `/en/legal/notice` |

## Still intentionally light / dynamic

- Admin **show/edit** with `{id}` — resolved by following first list link when present (users/groups/projects/roles).
- Issue **detail tabs / duplicate modal** — needs an issue row; skipped gracefully if empty.
- Setup wizard `/setup` — token-gated; not smoked here (dedicated setup e2e).
- Share viewer `/share/{token}` — dedicated share specs.

## CSS layer

`e2e/support/kit-chrome.ts` samples `.btn-primary`, text inputs, and `.panel` padding/font-size across the gap-close crawl and asserts:

1. Cross-page drift ≤ 2px for button + input metrics.
2. Primary buttons near `$beacon-btn-*` targets (14px / 8px / 16px).
