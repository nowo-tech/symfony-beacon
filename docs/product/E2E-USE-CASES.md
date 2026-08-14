# Product use cases → Playwright E2E coverage

Living catalog of **operator / member / client** use cases for Symfony Beacon.
Use this to decide what to automate next. Status values:

| Status | Meaning |
|--------|---------|
| **Covered** | Asserted in `e2e/**/*.spec.ts` (smoke or mutation) |
| **Partial** | Route/shell visited; happy-path mutation or edge cases still open |
| **Gap** | No dedicated E2E yet (may still have PHPUnit) |
| **Out of scope** | Needs external systems, destructive production ops, or is Later |

Run: `make up && make seed && make seed-sample && make test-e2e` — see [`e2e/README.md`](../../e2e/README.md).

---

## Actors

| Actor | How authenticated | Typical capabilities |
|-------|-------------------|----------------------|
| **Guest** | None | Login, legal, cookie consent, health, share-token open |
| **Member** | Session (`ROLE_USER`) + project membership | Dashboard, issues triage per role, account prefs |
| **Project owner / admin** | Membership `owner` / `admin` / `full` | Settings, keys, notifications, share links, governance |
| **Viewer / share viewer** | Membership `viewer` or share link | Read-only issues / scoped issue |
| **Instance admin** | `ROLE_ADMIN` | Administration hub, users, kits, mailer, appearance |
| **Client SDK** | Project DSN (`X-Beacon-Auth` / envelope `dsn`) | Envelope + OTLP ingest |
| **Read API client** | Bearer `brt_…` | List/get issues |
| **Channel bot** | Slack/Teams signed payloads | Resolve / Assign interactive actions |

Membership roles: see [ROLES.md](ROLES.md).

---

## 1. Public & auth (AuthKit)

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-AUTH-01 | Open `/` → redirect to login | Covered | `smoke/public.spec.ts` |
| UC-AUTH-02 | Login with valid credentials | Covered | `setup/auth.setup.ts` |
| UC-AUTH-03 | Login with invalid credentials stays on login | Covered | `smoke/public.spec.ts` |
| UC-AUTH-04 | Localized login (`/en|es|de|fr/login`) | Covered | `smoke/public.spec.ts` |
| UC-AUTH-05 | Magic login page loads | Covered | `smoke/public.spec.ts` |
| UC-AUTH-06 | QR login page loads | Covered | `smoke/public.spec.ts` |
| UC-AUTH-07 | Password reset page loads | Covered | `smoke/public.spec.ts` |
| UC-AUTH-08 | Remember-me checkbox / cookie | Covered | `smoke/use-cases-auth-chrome.spec.ts` (checkbox); `flows/use-cases-destructive-safe.spec.ts` (REMEMBERME survives session clear) |
| UC-AUTH-09 | Login throttle after N failures | Covered | `flows/use-cases-oos-closing.spec.ts` (ephemeral username; suite start clears `login_attempts`) |
| UC-AUTH-10 | First-user registration when DB empty | Out of scope | Destructive / empty DB |
| UC-AUTH-11 | `/register` redirects to login when users exist | Covered | `smoke/use-cases-auth.spec.ts` |
| UC-AUTH-12 | Logout returns to login | Covered | `project/dashboard-project.spec.ts` |
| UC-AUTH-13 | Guest locale switch | Covered | `smoke/navigation-ui.spec.ts` |
| UC-AUTH-14 | Social login buttons when providers configured | Covered | `flows/use-cases-partials-closing.spec.ts` (enabled provider → guest Continue; live IdP redirect Out of scope) |
| UC-AUTH-15 | Password policy / strength / toggle UI | Covered | `smoke/use-cases-auth-chrome.spec.ts` (login reveal + account security) |
| UC-AUTH-16 | Protected routes redirect guests to login | Covered | `smoke/navigation-ui.spec.ts` |

---

## 2. Legal & cookies

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-LEGAL-01 | Legal notice / privacy / terms / cookies (localized) | Covered | `smoke/public.spec.ts` |
| UC-LEGAL-02 | Bare `/legal/*` redirects | Covered | `smoke/public.spec.ts` |
| UC-LEGAL-03 | Guest cookie consent modal accept-all | Covered | `smoke/cookie-consent.spec.ts` |
| UC-LEGAL-04 | Consent persists across navigations | Covered | `smoke/cookie-consent.spec.ts` |
| UC-LEGAL-05 | Cookie consent config JSON endpoints | Covered | `smoke/cookie-consent.spec.ts` |
| UC-LEGAL-06 | Admin cookie consent settings / definitions | Covered | `admin/kit-admin-deep.spec.ts` |

---

## 3. Health, metrics, errors

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-OPS-01 | `GET /health/live` → 200 | Covered | `smoke/public.spec.ts` |
| UC-OPS-02 | `GET /health/ready` (DB) | Covered | `smoke/public.spec.ts` |
| UC-OPS-03 | `GET /metrics` without 5xx | Covered | `smoke/navigation-ui.spec.ts` |
| UC-OPS-04 | Branded HTTP error pages (404/403) | Covered | `smoke/use-cases-auth.spec.ts` |
| UC-OPS-05 | Maintenance mode 503 surfaces | Covered | `smoke/use-cases-auth-chrome.spec.ts` (`/_maintenance_preview` + admin panel) |
| UC-OPS-06 | Admin ops overview | Covered | `account/use-cases-account-chrome.spec.ts` |

---

## 4. Account preferences

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ACC-01 | Profile overview | Covered | `account/account.spec.ts`, `account/account-deep.spec.ts` |
| UC-ACC-02 | Security / history / activity panels | Covered | `account/account-deep.spec.ts` |
| UC-ACC-03 | Account projects & groups lists | Covered | `account/account-deep.spec.ts` |
| UC-ACC-04 | Privacy GDPR export download | Covered | `account/account.spec.ts` |
| UC-ACC-05 | Privacy anonymize panel present | Covered | `account/account-deep.spec.ts` |
| UC-ACC-06 | Execute anonymize (self) | Covered | `flows/use-cases-oos-closing.spec.ts` (ephemeral user; not demo admin) |
| UC-ACC-07 | Display prefs (theme, density, motion, font, contrast, sidebar) | Covered | `account/use-cases-account-chrome.spec.ts` |
| UC-ACC-08 | Collapsed panels prefs | Covered | `account/use-cases-account-chrome.spec.ts` |
| UC-ACC-09 | Product tour replay | Covered | `account/account-deep.spec.ts` |
| UC-ACC-10 | Authenticated locale switch | Covered | `account/account-deep.spec.ts` |
| UC-ACC-11 | Theme toggle chrome | Covered | `smoke/navigation-ui.spec.ts` |
| UC-ACC-12 | Content-width toggle | Covered | `smoke/navigation-ui.spec.ts` |
| UC-ACC-13 | Member alert matrix (Live / Push / scope / projects) | Covered | `account/member-alerts.spec.ts` |
| UC-ACC-14 | Web Push subscribe / unsubscribe | Covered | `flows/use-cases-oos-closing.spec.ts` (unavailable shell without VAPID); live browser Push permission Out of scope |
| UC-ACC-15 | Mercure realtime config endpoint | Covered | `account/use-cases-account-chrome.spec.ts` |
| UC-ACC-16 | PWA manifest / SW / offline | Covered | `smoke/misc.spec.ts` |

---

## 5. Dashboard & navigation

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-DASH-01 | Dashboard project list | Covered | `project/dashboard-project.spec.ts` |
| UC-DASH-02 | User menu links (account / admin) | Covered | `project/dashboard-project.spec.ts` |
| UC-DASH-03 | Summary metric cards | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-04 | Assignments list + filters | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-05 | Mentions list + filters | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-06 | Mentions mark-one / mark-all-read | Covered | `project/use-cases-dashboard.spec.ts` (controls); mention create → inbox `project/use-cases-members-viewer.spec.ts` |
| UC-DASH-07 | Activity feed + filters | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-08 | Alerts feed + filters | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-09 | New-in-release feed + filters | Covered | `project/dashboard-panels.spec.ts` |
| UC-DASH-10 | Area switch Preferences / Dashboard / Administration | Covered | `account/use-cases-account-chrome.spec.ts` |
| UC-DASH-11 | Product tour on first dashboard visit | Covered | `flows/use-cases-partials-closing.spec.ts` (`?tour=1`); replay Covered in `account/account-deep.spec.ts` |

---

## 6. Projects — lifecycle & settings

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-PROJ-01 | Create project from dashboard | Covered | `flows/mutations.spec.ts` |
| UC-PROJ-02 | Project show overview | Covered | `project/dashboard-project.spec.ts` |
| UC-PROJ-03 | Project nav tabs (issues / analytics / performance / releases / settings) | Covered | `smoke/navigation-ui.spec.ts` |
| UC-PROJ-04 | Settings sections (general, keys, members, governance, notifications, health, danger) | Covered | `project/use-cases-project.spec.ts`, `flows/use-cases-partials-closing.spec.ts` (general/access/alerts/data/danger) |
| UC-PROJ-05 | Create / rotate / revoke API key + DSN flash | Covered | `project/project-settings-deep.spec.ts`, `notifications/use-cases-notifications-keys.spec.ts` |
| UC-PROJ-06 | Add / change role / deactivate / remove member | Covered | `project/use-cases-members-viewer.spec.ts`, `flows/use-cases-partials-closing.spec.ts` |
| UC-PROJ-07 | Add / role / remove group access | Covered | `flows/use-cases-partials-closing.spec.ts` |
| UC-PROJ-08 | Save governance (retention / rate / quota) | Covered | `flows/mutations.spec.ts` |
| UC-PROJ-09 | Create share link (project-wide) | Covered | `flows/mutations.spec.ts`, `project/share-access.spec.ts` |
| UC-PROJ-10 | Create share link (issue-scoped + max uses) | Covered | `project/use-cases-share.spec.ts` |
| UC-PROJ-11 | Revoke share link | Covered | `project/use-cases-share.spec.ts` |
| UC-PROJ-12 | Guest opens valid share token (viewer) | Covered | `project/use-cases-share.spec.ts` (login then consume) |
| UC-PROJ-13 | Invalid share token | Covered | `project/share-access.spec.ts` |
| UC-PROJ-14 | Mint / revoke read API token | Covered | `flows/mutations.spec.ts` |
| UC-PROJ-15 | Member-alerts project override save | Covered | `account/member-alerts.spec.ts` |
| UC-PROJ-16 | Config export / import (project) | Covered | `project/project-config.spec.ts` |
| UC-PROJ-17 | Clear history (danger zone) | Covered | `flows/use-cases-destructive-safe.spec.ts` (ephemeral project) |
| UC-PROJ-18 | Transfer ownership | Covered | `flows/use-cases-destructive-safe.spec.ts` (ephemeral project + second member) |
| UC-PROJ-19 | Delete project | Covered | `flows/use-cases-destructive-safe.spec.ts` (ephemeral project; type-to-confirm) |
| UC-PROJ-20 | Notification help page | Covered | `project/project-settings-deep.spec.ts` |
| UC-PROJ-21 | Health / delivery history panel | Covered | `notifications/use-cases-thresholds-health.spec.ts` |

---

## 7. Issues — list, search, triage

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ISS-01 | Issue list table after sample seed | Covered | `issues/issues-deep.spec.ts` |
| UC-ISS-02 | Filter by level + status | Covered | `flows/mutations.spec.ts` |
| UC-ISS-03 | Filter by environment / release / priority (query string) | Covered | `issues/use-cases-issues.spec.ts`, `flows/use-cases-partials-closing.spec.ts` (tag/url/user/q; MySQL url LIKE ESCAPE fixed) |
| UC-ISS-04 | FULLTEXT search query param | Covered | `project/dashboard-project.spec.ts` |
| UC-ISS-05 | Sort + pagination + per_page | Covered | `issues/issues-deep.spec.ts` |
| UC-ISS-06 | Environment compare panel | Covered | `issues/issues-deep.spec.ts` |
| UC-ISS-07 | Save named view | Covered | `flows/mutations.spec.ts` |
| UC-ISS-08 | Apply saved view | Covered | `issues/use-cases-issues.spec.ts` |
| UC-ISS-09 | Delete saved view | Covered | `issues/use-cases-issues.spec.ts` |
| UC-ISS-10 | Export issues CSV/JSON | Covered | `project/dashboard-project.spec.ts` |
| UC-ISS-11 | Export events CSV/JSON | Covered | `project/dashboard-project.spec.ts` |
| UC-ISS-12 | Open issue detail | Covered | `project/dashboard-project.spec.ts`, `issues/issues-deep.spec.ts` |
| UC-ISS-13 | Add comment | Covered | `flows/mutations.spec.ts` |
| UC-ISS-14 | Resolve / reopen status | Covered | `flows/mutations.spec.ts` |
| UC-ISS-15 | Ignore status | Covered | `issues/use-cases-issues.spec.ts` |
| UC-ISS-16 | Change priority | Covered | `flows/mutations.spec.ts` |
| UC-ISS-17 | Assign / clear assignee | Covered | `flows/mutations.spec.ts` |
| UC-ISS-18 | Mark duplicate (+ optional merge) | Covered | `flows/use-cases-partials-closing.spec.ts` (without merge); `flows/use-cases-destructive-safe.spec.ts` (with merge_events) |
| UC-ISS-19 | Similar issues panel | Covered | `notifications/use-cases-thresholds-health.spec.ts` (attached when present) |
| UC-ISS-20 | Copy for AI (md/json export) | Covered | `issues/issues-deep.spec.ts` |
| UC-ISS-21 | Open event detail from issue | Covered | `issues/use-cases-issues.spec.ts` |
| UC-ISS-22 | Stack / request / tags / contexts panels | Covered | `notifications/use-cases-thresholds-health.spec.ts` |
| UC-ISS-23 | Assignment & status history | Covered | `notifications/use-cases-thresholds-health.spec.ts` |
| UC-ISS-24 | Viewer read-only chrome | Covered | `project/use-cases-members-viewer.spec.ts` |
| UC-ISS-25 | @mention in comment → dashboard mentions | Covered | `project/use-cases-members-viewer.spec.ts` |

---

## 8. Analytics, performance, releases

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-AN-01 | Analytics charts shell + filters | Covered | `issues/issues-deep.spec.ts` |
| UC-AN-02 | Analytics period presets / custom range / env filters | Covered | `admin/use-cases-analytics-admin.spec.ts` |
| UC-PERF-01 | Performance index | Covered | `issues/issues-deep.spec.ts` |
| UC-PERF-02 | Performance transaction detail | Covered | `issues/issues-deep.spec.ts` |
| UC-PERF-03 | N+1-only filter (`?nplus1=1`) | Covered | `issues/use-cases-issues.spec.ts` |
| UC-REL-01 | Releases page + compare controls | Covered | `issues/issues-deep.spec.ts`, `project/project-settings-deep.spec.ts` |
| UC-REL-02 | Release health compare query params | Covered | `project/project-settings-deep.spec.ts` |

---

## 9. Notifications & thresholds

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-NOTIF-01 | Create destination form loads | Covered | `issues/issues-deep.spec.ts` |
| UC-NOTIF-02 | Create Slack/Discord/Teams/Telegram/email/HTTP destination | Covered | `notifications/use-cases-notifications-keys.spec.ts` (HTTP) |
| UC-NOTIF-03 | Edit / toggle / resume / delete destination | Covered | `notifications/use-cases-notifications-keys.spec.ts` (toggle+delete) |
| UC-NOTIF-04 | Send test notification | Covered | `flows/use-cases-destructive-safe.spec.ts` (HTTP destination → queued flash; delivery async) |
| UC-NOTIF-05 | Quiet hours + digests + lifecycle categories | Covered | `notifications/use-cases-thresholds-health.spec.ts` (save quiet hours + digest on HTTP dest) |
| UC-NOTIF-06 | Threshold rule create form | Covered | `issues/issues-deep.spec.ts` |
| UC-NOTIF-07 | Threshold rule CRUD + toggle | Covered | `notifications/use-cases-thresholds-health.spec.ts` |
| UC-NOTIF-08 | Circuit breaker resume after failures | Covered | `flows/use-cases-oos-closing.spec.ts` (threshold=1 + failing HTTP dest + Resume) |
| UC-NOTIF-09 | Destinations list shell | Covered | `project/project-settings-deep.spec.ts` |

---

## 10. Ingest & APIs

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ING-01 | Envelope POST with DSN auth ACK | Covered | `ingest/ingest.spec.ts` |
| UC-ING-02 | Envelope rejects query-string auth (401) | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-03 | Envelope rejects missing secret | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-04 | OTLP logs WARN+ → ACK | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-05 | OTLP traces ERROR span → ACK | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-06 | OTLP metrics failure-like point → ACK | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-07 | OTLP rejects query auth | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-08 | Suspend ingest / quota exceeded | Covered | `notifications/use-cases-thresholds-health.spec.ts` (suspend→403); `flows/use-cases-oos-closing.spec.ts` (daily quota→429) |
| UC-ING-09 | Read API list issues with Bearer | Covered | `flows/mutations.spec.ts` |
| UC-ING-10 | Read API get single issue | Covered | `ingest/use-cases-ingest.spec.ts` |
| UC-ING-11 | Read API rate limit 429 | Covered | `z-late/read-api-ratelimit.spec.ts` (burn default 120/min IP window + cooldown) |
| UC-ING-12 | OpenAPI JSON for admin | Covered | `admin/admin.spec.ts` |

---

## 11. Interactive hooks & inbound email

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-HOOK-01 | Slack interactions reject unsigned / bad body | Covered | `hooks/use-cases-hooks.spec.ts` |
| UC-HOOK-02 | Teams actions reject bad body | Covered | `hooks/use-cases-hooks.spec.ts` |
| UC-HOOK-03 | Teams assign-me reject bad body | Covered | `hooks/use-cases-hooks.spec.ts` |
| UC-HOOK-04 | Inbound email reject unauthenticated | Covered | `hooks/use-cases-hooks.spec.ts` |
| UC-HOOK-05 | Happy-path Slack Resolve / Assign | Covered | `hooks/use-cases-hooks-happy.spec.ts` (forged Slack signature + linked Slack user id) |
| UC-HOOK-06 | Happy-path Teams actions | Covered | `hooks/use-cases-hooks-happy.spec.ts` (forged assign-me + anonymous Resolve with restore) |
| UC-HOOK-07 | Inbound email → issue comment | Covered | `hooks/use-cases-hooks-happy.spec.ts` (ops inbound secret + forged reply token) |

---

## 12. Administration

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ADM-01 | Admin hub + deep cards | Covered | `admin/admin.spec.ts`, `admin/settings-deep.spec.ts` |
| UC-ADM-02 | Users list / new form / activity | Covered | `admin/admin.spec.ts` |
| UC-ADM-03 | Create user / toggle enabled / change role | Covered | `admin/use-cases-analytics-admin.spec.ts`, `admin/use-cases-admin-remaining.spec.ts`, `flows/use-cases-partials-closing.spec.ts` |
| UC-ADM-04 | Admin user GDPR export / anonymize | Covered | export `admin/use-cases-admin.spec.ts`; anonymize ephemeral user `flows/use-cases-destructive-safe.spec.ts` |
| UC-ADM-05 | Groups CRUD + members / projects | Covered | `admin/use-cases-admin-remaining.spec.ts`, `flows/use-cases-partials-closing.spec.ts` (add/remove member) |
| UC-ADM-06 | Admin projects list / show / edit / ops | Covered | `admin/admin.spec.ts`, `admin/admin-project-deep.spec.ts` |
| UC-ADM-07 | Suspend ingest from admin | Covered | `admin/use-cases-analytics-admin.spec.ts` |
| UC-ADM-08 | View-as-member enable / disable | Covered | `admin/use-cases-admin.spec.ts` |
| UC-ADM-09 | Admin project audit timeline | Covered | `admin/admin-project-deep.spec.ts` |
| UC-ADM-10 | Admin project config export/import | Covered | `project/project-config.spec.ts` |
| UC-ADM-11 | Roles / permissions RBAC UI | Covered | `admin/admin-rbac.spec.ts` |
| UC-ADM-12 | Social login provider CRUD forms | Covered | `admin/use-cases-admin.spec.ts` |
| UC-ADM-13 | Mailer DSN form + sample email | Covered | `admin/use-cases-admin.spec.ts` + `admin/use-cases-admin-remaining.spec.ts` (sample control / unavailable) |
| UC-ADM-14 | Mercure settings form | Covered | `smoke/navigation-ui.spec.ts` |
| UC-ADM-15 | Appearance tabs / colors / presets | Covered | `admin/settings-deep.spec.ts`, `smoke/misc.spec.ts` |
| UC-ADM-16 | Ops defaults tabs | Covered | `admin/settings-deep.spec.ts` |
| UC-ADM-17 | Instance config export / import | Covered | `admin/settings-deep.spec.ts` |
| UC-ADM-18 | Legacy `/settings/*` redirects | Covered | `admin/settings-deep.spec.ts` |
| UC-ADM-19 | Kit: HTTP log / menus / breadcrumbs / RoutingKit | Covered | `admin/kit-admin-deep.spec.ts` |
| UC-ADM-20 | Unlink user↔project / group↔project | Covered | `admin/use-cases-admin-remaining.spec.ts` (affordance when linked) |

---

## 13. Setup & install

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-SETUP-01 | SiteBackup `/setup` wizard (empty catalogs) | Covered | Marker hygiene (`smoke/use-cases-auth.spec.ts`); full cold wizard remains Out of scope — never leave `setup.required` |
| UC-SETUP-02 | Platform catalog redirect when incomplete | Out of scope | Needs incomplete catalog fixture |
| UC-SETUP-03 | Seed layers / demo project | Out of scope | Makefile / console |

---

## 14. Suggested next E2E batches (priority)

**Automable catalog closed** including ephemeral danger-zone / transfer / delete, admin + self anonymize, remember-me, merge_events, send-test, login throttle, daily quota 429, circuit resume, forged Slack/Teams/inbound hooks, Read API 429, and quiet-hours save (`flows/use-cases-destructive-safe.spec.ts`, `flows/use-cases-oos-closing.spec.ts`, `hooks/use-cases-hooks-happy.spec.ts`, `z-late/read-api-ratelimit.spec.ts`). **No Gaps. No Partials.**

**Still Out of scope (external / instance-wide / destructive):**

1. Live OAuth IdP redirect after Continue (UC-AUTH-14 deep).
2. First-user empty DB registration (UC-AUTH-10).
3. Live browser Web Push permission / push service subscribe (UC-ACC-14 deep).
4. Full cold SiteBackup wizard / incomplete catalog fixture (UC-SETUP-01 cold / UC-SETUP-02).
5. Seed layers via Makefile (UC-SETUP-03).

When adding a case: give it a **UC-*** ID here, set status, and name the spec file in the table.

---

## Spec ↔ feature map (high level)

Product Spec Kit folders under `specs/` map roughly to the UC areas above (`003-ingest` → §10, `004-issues` → §7, `009-project-notifications` → §9, `026-magic-links-viewer` → UC-AUTH-05 / UC-PROJ-09, `043-gdpr-user-export` → UC-ACC-04, `067`–`074` OTLP → UC-ING-04..07, etc.). Prefer this catalog over inventing duplicate matrices in feature specs.
