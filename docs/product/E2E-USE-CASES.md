# Product use cases → Playwright E2E coverage

Living catalog of **operator / member / client** use cases for Symfony Beacon.
Use this to decide what to automate next. Status values:

| Status | Meaning |
|--------|---------|
| **Covered** | Asserted in `e2e/*.spec.ts` (smoke or mutation) |
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
| UC-AUTH-01 | Open `/` → redirect to login | Covered | `public.spec.ts` |
| UC-AUTH-02 | Login with valid credentials | Covered | `auth.setup.ts` |
| UC-AUTH-03 | Login with invalid credentials stays on login | Covered | `public.spec.ts` |
| UC-AUTH-04 | Localized login (`/en|es|de|fr/login`) | Covered | `public.spec.ts` |
| UC-AUTH-05 | Magic login page loads | Covered | `public.spec.ts` |
| UC-AUTH-06 | QR login page loads | Covered | `public.spec.ts` |
| UC-AUTH-07 | Password reset page loads | Covered | `public.spec.ts` |
| UC-AUTH-08 | Remember-me checkbox / cookie | Covered | `use-cases-auth-chrome.spec.ts` (checkbox); `use-cases-destructive-safe.spec.ts` (REMEMBERME survives session clear) |
| UC-AUTH-09 | Login throttle after N failures | Covered | `use-cases-oos-closing.spec.ts` (ephemeral username; suite start clears `login_attempts`) |
| UC-AUTH-10 | First-user registration when DB empty | Out of scope | Destructive / empty DB |
| UC-AUTH-11 | `/register` redirects to login when users exist | Covered | `use-cases-auth.spec.ts` |
| UC-AUTH-12 | Logout returns to login | Covered | `dashboard-project.spec.ts` |
| UC-AUTH-13 | Guest locale switch | Covered | `navigation-ui.spec.ts` |
| UC-AUTH-14 | Social login buttons when providers configured | Covered | `use-cases-partials-closing.spec.ts` (enabled provider → guest Continue; live IdP redirect Out of scope) |
| UC-AUTH-15 | Password policy / strength / toggle UI | Covered | `use-cases-auth-chrome.spec.ts` (login reveal + account security) |
| UC-AUTH-16 | Protected routes redirect guests to login | Covered | `navigation-ui.spec.ts` |

---

## 2. Legal & cookies

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-LEGAL-01 | Legal notice / privacy / terms / cookies (localized) | Covered | `public.spec.ts` |
| UC-LEGAL-02 | Bare `/legal/*` redirects | Covered | `public.spec.ts` |
| UC-LEGAL-03 | Guest cookie consent modal accept-all | Covered | `cookie-consent.spec.ts` |
| UC-LEGAL-04 | Consent persists across navigations | Covered | `cookie-consent.spec.ts` |
| UC-LEGAL-05 | Cookie consent config JSON endpoints | Covered | `cookie-consent.spec.ts` |
| UC-LEGAL-06 | Admin cookie consent settings / definitions | Covered | `kit-admin-deep.spec.ts` |

---

## 3. Health, metrics, errors

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-OPS-01 | `GET /health/live` → 200 | Covered | `public.spec.ts` |
| UC-OPS-02 | `GET /health/ready` (DB) | Covered | `public.spec.ts` |
| UC-OPS-03 | `GET /metrics` without 5xx | Covered | `navigation-ui.spec.ts` |
| UC-OPS-04 | Branded HTTP error pages (404/403) | Covered | `use-cases-auth.spec.ts` |
| UC-OPS-05 | Maintenance mode 503 surfaces | Covered | `use-cases-auth-chrome.spec.ts` (`/_maintenance_preview` + admin panel) |
| UC-OPS-06 | Admin ops overview | Covered | `use-cases-account-chrome.spec.ts` |

---

## 4. Account preferences

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ACC-01 | Profile overview | Covered | `account.spec.ts`, `account-deep.spec.ts` |
| UC-ACC-02 | Security / history / activity panels | Covered | `account-deep.spec.ts` |
| UC-ACC-03 | Account projects & groups lists | Covered | `account-deep.spec.ts` |
| UC-ACC-04 | Privacy GDPR export download | Covered | `account.spec.ts` |
| UC-ACC-05 | Privacy anonymize panel present | Covered | `account-deep.spec.ts` |
| UC-ACC-06 | Execute anonymize (self) | Covered | `use-cases-oos-closing.spec.ts` (ephemeral user; not demo admin) |
| UC-ACC-07 | Display prefs (theme, density, motion, font, contrast, sidebar) | Covered | `use-cases-account-chrome.spec.ts` |
| UC-ACC-08 | Collapsed panels prefs | Covered | `use-cases-account-chrome.spec.ts` |
| UC-ACC-09 | Product tour replay | Covered | `account-deep.spec.ts` |
| UC-ACC-10 | Authenticated locale switch | Covered | `account-deep.spec.ts` |
| UC-ACC-11 | Theme toggle chrome | Covered | `navigation-ui.spec.ts` |
| UC-ACC-12 | Content-width toggle | Covered | `navigation-ui.spec.ts` |
| UC-ACC-13 | Member alert matrix (Live / Push / scope / projects) | Covered | `member-alerts.spec.ts` |
| UC-ACC-14 | Web Push subscribe / unsubscribe | Covered | `use-cases-oos-closing.spec.ts` (unavailable shell without VAPID); live browser Push permission Out of scope |
| UC-ACC-15 | Mercure realtime config endpoint | Covered | `use-cases-account-chrome.spec.ts` |
| UC-ACC-16 | PWA manifest / SW / offline | Covered | `misc.spec.ts` |

---

## 5. Dashboard & navigation

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-DASH-01 | Dashboard project list | Covered | `dashboard-project.spec.ts` |
| UC-DASH-02 | User menu links (account / admin) | Covered | `dashboard-project.spec.ts` |
| UC-DASH-03 | Summary metric cards | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-04 | Assignments list + filters | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-05 | Mentions list + filters | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-06 | Mentions mark-one / mark-all-read | Covered | `use-cases-dashboard.spec.ts` (controls); mention create → inbox `use-cases-members-viewer.spec.ts` |
| UC-DASH-07 | Activity feed + filters | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-08 | Alerts feed + filters | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-09 | New-in-release feed + filters | Covered | `dashboard-panels.spec.ts` |
| UC-DASH-10 | Area switch Preferences / Dashboard / Administration | Covered | `use-cases-account-chrome.spec.ts` |
| UC-DASH-11 | Product tour on first dashboard visit | Covered | `use-cases-partials-closing.spec.ts` (`?tour=1`); replay Covered in `account-deep.spec.ts` |

---

## 6. Projects — lifecycle & settings

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-PROJ-01 | Create project from dashboard | Covered | `mutations.spec.ts` |
| UC-PROJ-02 | Project show overview | Covered | `dashboard-project.spec.ts` |
| UC-PROJ-03 | Project nav tabs (issues / analytics / performance / releases / settings) | Covered | `navigation-ui.spec.ts` |
| UC-PROJ-04 | Settings sections (general, keys, members, governance, notifications, health, danger) | Covered | `use-cases-project.spec.ts`, `use-cases-partials-closing.spec.ts` (general/access/alerts/data/danger) |
| UC-PROJ-05 | Create / rotate / revoke API key + DSN flash | Covered | `project-settings-deep.spec.ts`, `use-cases-notifications-keys.spec.ts` |
| UC-PROJ-06 | Add / change role / deactivate / remove member | Covered | `use-cases-members-viewer.spec.ts`, `use-cases-partials-closing.spec.ts` |
| UC-PROJ-07 | Add / role / remove group access | Covered | `use-cases-partials-closing.spec.ts` |
| UC-PROJ-08 | Save governance (retention / rate / quota) | Covered | `mutations.spec.ts` |
| UC-PROJ-09 | Create share link (project-wide) | Covered | `mutations.spec.ts`, `share-access.spec.ts` |
| UC-PROJ-10 | Create share link (issue-scoped + max uses) | Covered | `use-cases-share.spec.ts` |
| UC-PROJ-11 | Revoke share link | Covered | `use-cases-share.spec.ts` |
| UC-PROJ-12 | Guest opens valid share token (viewer) | Covered | `use-cases-share.spec.ts` (login then consume) |
| UC-PROJ-13 | Invalid share token | Covered | `share-access.spec.ts` |
| UC-PROJ-14 | Mint / revoke read API token | Covered | `mutations.spec.ts` |
| UC-PROJ-15 | Member-alerts project override save | Covered | `member-alerts.spec.ts` |
| UC-PROJ-16 | Config export / import (project) | Covered | `project-config.spec.ts` |
| UC-PROJ-17 | Clear history (danger zone) | Covered | `use-cases-destructive-safe.spec.ts` (ephemeral project) |
| UC-PROJ-18 | Transfer ownership | Covered | `use-cases-destructive-safe.spec.ts` (ephemeral project + second member) |
| UC-PROJ-19 | Delete project | Covered | `use-cases-destructive-safe.spec.ts` (ephemeral project; type-to-confirm) |
| UC-PROJ-20 | Notification help page | Covered | `project-settings-deep.spec.ts` |
| UC-PROJ-21 | Health / delivery history panel | Covered | `use-cases-thresholds-health.spec.ts` |

---

## 7. Issues — list, search, triage

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ISS-01 | Issue list table after sample seed | Covered | `issues-deep.spec.ts` |
| UC-ISS-02 | Filter by level + status | Covered | `mutations.spec.ts` |
| UC-ISS-03 | Filter by environment / release / priority (query string) | Covered | `use-cases-issues.spec.ts`, `use-cases-partials-closing.spec.ts` (tag/url/user/q; MySQL url LIKE ESCAPE fixed) |
| UC-ISS-04 | FULLTEXT search query param | Covered | `dashboard-project.spec.ts` |
| UC-ISS-05 | Sort + pagination + per_page | Covered | `issues-deep.spec.ts` |
| UC-ISS-06 | Environment compare panel | Covered | `issues-deep.spec.ts` |
| UC-ISS-07 | Save named view | Covered | `mutations.spec.ts` |
| UC-ISS-08 | Apply saved view | Covered | `use-cases-issues.spec.ts` |
| UC-ISS-09 | Delete saved view | Covered | `use-cases-issues.spec.ts` |
| UC-ISS-10 | Export issues CSV/JSON | Covered | `dashboard-project.spec.ts` |
| UC-ISS-11 | Export events CSV/JSON | Covered | `dashboard-project.spec.ts` |
| UC-ISS-12 | Open issue detail | Covered | `dashboard-project.spec.ts`, `issues-deep.spec.ts` |
| UC-ISS-13 | Add comment | Covered | `mutations.spec.ts` |
| UC-ISS-14 | Resolve / reopen status | Covered | `mutations.spec.ts` |
| UC-ISS-15 | Ignore status | Covered | `use-cases-issues.spec.ts` |
| UC-ISS-16 | Change priority | Covered | `mutations.spec.ts` |
| UC-ISS-17 | Assign / clear assignee | Covered | `mutations.spec.ts` |
| UC-ISS-18 | Mark duplicate (+ optional merge) | Covered | `use-cases-partials-closing.spec.ts` (without merge); `use-cases-destructive-safe.spec.ts` (with merge_events) |
| UC-ISS-19 | Similar issues panel | Covered | `use-cases-thresholds-health.spec.ts` (attached when present) |
| UC-ISS-20 | Copy for AI (md/json export) | Covered | `issues-deep.spec.ts` |
| UC-ISS-21 | Open event detail from issue | Covered | `use-cases-issues.spec.ts` |
| UC-ISS-22 | Stack / request / tags / contexts panels | Covered | `use-cases-thresholds-health.spec.ts` |
| UC-ISS-23 | Assignment & status history | Covered | `use-cases-thresholds-health.spec.ts` |
| UC-ISS-24 | Viewer read-only chrome | Covered | `use-cases-members-viewer.spec.ts` |
| UC-ISS-25 | @mention in comment → dashboard mentions | Covered | `use-cases-members-viewer.spec.ts` |

---

## 8. Analytics, performance, releases

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-AN-01 | Analytics charts shell + filters | Covered | `issues-deep.spec.ts` |
| UC-AN-02 | Analytics period presets / custom range / env filters | Covered | `use-cases-analytics-admin.spec.ts` |
| UC-PERF-01 | Performance index | Covered | `issues-deep.spec.ts` |
| UC-PERF-02 | Performance transaction detail | Covered | `issues-deep.spec.ts` |
| UC-PERF-03 | N+1-only filter (`?nplus1=1`) | Covered | `use-cases-issues.spec.ts` |
| UC-REL-01 | Releases page + compare controls | Covered | `issues-deep.spec.ts`, `project-settings-deep.spec.ts` |
| UC-REL-02 | Release health compare query params | Covered | `project-settings-deep.spec.ts` |

---

## 9. Notifications & thresholds

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-NOTIF-01 | Create destination form loads | Covered | `issues-deep.spec.ts` |
| UC-NOTIF-02 | Create Slack/Discord/Teams/Telegram/email/HTTP destination | Covered | `use-cases-notifications-keys.spec.ts` (HTTP) |
| UC-NOTIF-03 | Edit / toggle / resume / delete destination | Covered | `use-cases-notifications-keys.spec.ts` (toggle+delete) |
| UC-NOTIF-04 | Send test notification | Covered | `use-cases-destructive-safe.spec.ts` (HTTP destination → queued flash; delivery async) |
| UC-NOTIF-05 | Quiet hours + digests + lifecycle categories | Covered | `use-cases-thresholds-health.spec.ts` (form fields) |
| UC-NOTIF-06 | Threshold rule create form | Covered | `issues-deep.spec.ts` |
| UC-NOTIF-07 | Threshold rule CRUD + toggle | Covered | `use-cases-thresholds-health.spec.ts` |
| UC-NOTIF-08 | Circuit breaker resume after failures | Covered | `use-cases-oos-closing.spec.ts` (threshold=1 + failing HTTP dest + Resume) |
| UC-NOTIF-09 | Destinations list shell | Covered | `project-settings-deep.spec.ts` |

---

## 10. Ingest & APIs

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ING-01 | Envelope POST with DSN auth ACK | Covered | `ingest.spec.ts` |
| UC-ING-02 | Envelope rejects query-string auth (401) | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-03 | Envelope rejects missing secret | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-04 | OTLP logs WARN+ → ACK | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-05 | OTLP traces ERROR span → ACK | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-06 | OTLP metrics failure-like point → ACK | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-07 | OTLP rejects query auth | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-08 | Suspend ingest / quota exceeded | Covered | `use-cases-thresholds-health.spec.ts` (suspend→403); `use-cases-oos-closing.spec.ts` (daily quota→429) |
| UC-ING-09 | Read API list issues with Bearer | Covered | `mutations.spec.ts` |
| UC-ING-10 | Read API get single issue | Covered | `use-cases-ingest.spec.ts` |
| UC-ING-11 | Read API rate limit 429 | Out of scope | Needs low limit + reset between suites |
| UC-ING-12 | OpenAPI JSON for admin | Covered | `admin.spec.ts` |

---

## 11. Interactive hooks & inbound email

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-HOOK-01 | Slack interactions reject unsigned / bad body | Covered | `use-cases-hooks.spec.ts` |
| UC-HOOK-02 | Teams actions reject bad body | Covered | `use-cases-hooks.spec.ts` |
| UC-HOOK-03 | Teams assign-me reject bad body | Covered | `use-cases-hooks.spec.ts` |
| UC-HOOK-04 | Inbound email reject unauthenticated | Covered | `use-cases-hooks.spec.ts` |
| UC-HOOK-05 | Happy-path Slack Resolve / Assign | Covered | `use-cases-hooks-happy.spec.ts` (forged Slack signature + linked Slack user id) |
| UC-HOOK-06 | Happy-path Teams actions | Covered | `use-cases-hooks-happy.spec.ts` (forged assign-me + anonymous Resolve with restore) |
| UC-HOOK-07 | Inbound email → issue comment | Covered | `use-cases-hooks-happy.spec.ts` (ops inbound secret + forged reply token) |

---

## 12. Administration

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-ADM-01 | Admin hub + deep cards | Covered | `admin.spec.ts`, `settings-deep.spec.ts` |
| UC-ADM-02 | Users list / new form / activity | Covered | `admin.spec.ts` |
| UC-ADM-03 | Create user / toggle enabled / change role | Covered | `use-cases-analytics-admin.spec.ts`, `use-cases-admin-remaining.spec.ts`, `use-cases-partials-closing.spec.ts` |
| UC-ADM-04 | Admin user GDPR export / anonymize | Covered | export `use-cases-admin.spec.ts`; anonymize ephemeral user `use-cases-destructive-safe.spec.ts` |
| UC-ADM-05 | Groups CRUD + members / projects | Covered | `use-cases-admin-remaining.spec.ts`, `use-cases-partials-closing.spec.ts` (add/remove member) |
| UC-ADM-06 | Admin projects list / show / edit / ops | Covered | `admin.spec.ts`, `admin-project-deep.spec.ts` |
| UC-ADM-07 | Suspend ingest from admin | Covered | `use-cases-analytics-admin.spec.ts` |
| UC-ADM-08 | View-as-member enable / disable | Covered | `use-cases-admin.spec.ts` |
| UC-ADM-09 | Admin project audit timeline | Covered | `admin-project-deep.spec.ts` |
| UC-ADM-10 | Admin project config export/import | Covered | `project-config.spec.ts` |
| UC-ADM-11 | Roles / permissions RBAC UI | Covered | `admin-rbac.spec.ts` |
| UC-ADM-12 | Social login provider CRUD forms | Covered | `use-cases-admin.spec.ts` |
| UC-ADM-13 | Mailer DSN form + sample email | Covered | `use-cases-admin.spec.ts` + `use-cases-admin-remaining.spec.ts` (sample control / unavailable) |
| UC-ADM-14 | Mercure settings form | Covered | `navigation-ui.spec.ts` |
| UC-ADM-15 | Appearance tabs / colors / presets | Covered | `settings-deep.spec.ts`, `misc.spec.ts` |
| UC-ADM-16 | Ops defaults tabs | Covered | `settings-deep.spec.ts` |
| UC-ADM-17 | Instance config export / import | Covered | `settings-deep.spec.ts` |
| UC-ADM-18 | Legacy `/settings/*` redirects | Covered | `settings-deep.spec.ts` |
| UC-ADM-19 | Kit: HTTP log / menus / breadcrumbs / RoutingKit | Covered | `kit-admin-deep.spec.ts` |
| UC-ADM-20 | Unlink user↔project / group↔project | Covered | `use-cases-admin-remaining.spec.ts` (affordance when linked) |

---

## 13. Setup & install

| ID | Use case | Status | E2E file(s) |
|----|----------|--------|-------------|
| UC-SETUP-01 | SiteBackup `/setup` wizard (empty catalogs) | Partial | Marker hygiene Covered (`use-cases-auth.spec.ts`); full cold wizard Out of scope — never leave `setup.required` |
| UC-SETUP-02 | Platform catalog redirect when incomplete | Out of scope | Needs incomplete catalog fixture |
| UC-SETUP-03 | Seed layers / demo project | Out of scope | Makefile / console |

---

## 14. Suggested next E2E batches (priority)

**Automable catalog closed** including ephemeral danger-zone / transfer / delete, admin + self anonymize, remember-me, merge_events, send-test, login throttle, daily quota 429, circuit resume, and forged Slack/Teams/inbound hooks (`use-cases-destructive-safe.spec.ts`, `use-cases-oos-closing.spec.ts`, `use-cases-hooks-happy.spec.ts`). **No Gaps.** Remaining Partials are only cold SiteBackup wizard hygiene vs full cold DB.

**Still Out of scope (external / instance-wide):**

1. Live OAuth IdP redirect after Continue (UC-AUTH-14 deep).
2. First-user empty DB registration (UC-AUTH-10).
3. Live browser Web Push permission / push service subscribe (UC-ACC-14 deep).
4. Read API 429 with low limit (UC-ING-11) — limit is IP-wide env only; no per-token/UI knob.
5. Full cold SiteBackup wizard / incomplete catalog fixture (UC-SETUP-01/02).
6. Seed layers via Makefile (UC-SETUP-03).

When adding a case: give it a **UC-*** ID here, set status, and name the spec file in the table.

---

## Spec ↔ feature map (high level)

Product Spec Kit folders under `specs/` map roughly to the UC areas above (`003-ingest` → §10, `004-issues` → §7, `009-project-notifications` → §9, `026-magic-links-viewer` → UC-AUTH-05 / UC-PROJ-09, `043-gdpr-user-export` → UC-ACC-04, `067`–`074` OTLP → UC-ING-04..07, etc.). Prefer this catalog over inventing duplicate matrices in feature specs.
