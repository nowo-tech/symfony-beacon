# Product roadmap

Living plan for **symfony-beacon** and the companion client **[nowo-tech/beacon-bundle](https://github.com/nowo-tech/BeaconBundle)**. Priorities follow product completeness analysis (post-v0.6.0): close the operator loop (alerts), keep self-hosting safe, then deepen automatic instrumentation and product depth.

Related: [ARCHITECTURE.md](ARCHITECTURE.md), [CHANGELOG.md](CHANGELOG.md), feature specs under `specs/`.

## Guiding principles

1. Spec-first — each slice maps to a `specs/NNN-*` feature (or a BeaconBundle spec).
2. Efficient ingest — outbound work stays on Messenger; Envelope ACK never waits on Slack/webhooks.
3. Prefer Nowo.tech kits for auth/ops UX; keep Beacon focused on telemetry.
4. English docs / PHPDoc / default UI copy.

## Status legend

| Status | Meaning |
|--------|---------|
| **Done** | Shipped in a tagged release |
| **In progress** | Active implementation |
| **Next** | Immediate queue |
| **Planned** | Ordered backlog |
| **Later** | Explicitly deferred |

---

## Phase 0 — Foundation (Done → v0.6.0)

| Item | Repo | Notes |
|------|------|--------|
| Envelope ingest + Messenger | Beacon | `003-ingest` |
| Issues triage UI (fingerprint, assignee, status, history, DataTables) | Beacon | `004-issues` |
| Performance + N+1 UI | Beacon | `006-performance` |
| Daily analytics | Beacon | `005-analytics` |
| AuthKit, projects, Settings, danger zone | Beacon | `002`, `011` |
| Rich event context + stack source context | Beacon + Bundle | `010`, Bundle ≥ 1.3.0 |
| PWA + Hotwire Native server contract | Beacon | `008-ux-native` |
| Architecture rationale + Mermaid flows | Beacon | `docs/ARCHITECTURE.md` |

---

## Phase 1 — Close the loop (Done)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 1.1 | **Project notifications** (Slack, Discord, Teams, Telegram, email, generic HTTP) | Beacon | `009-project-notifications` | **Done** |
| 1.2 | **Regression rules**: reopen `resolved` **and** `ignored` → unresolved on matching event; notify on new + regression only | Beacon | `009` / ingest | **Done** |
| 1.3 | Settings UI: destinations CRUD, category filters, masked URLs, send-test, **setup guides** | Beacon | `009` | **Done** |
| 1.4 | Async delivery + bounded retries via Messenger + **SSRF guard** | Beacon | `009` | **Done** |
| 1.5 | **API docs in Panel** (Nelmio OpenAPI / Swagger in app shell) | Beacon | `013-api-docs-panel` | **Done** |

---

## Phase 2 — Safe self-hosting (Done)

| # | Item | Repo | Status |
|---|------|------|--------|
| 2.1 | Configurable **retention** + purge job (max age / max events per project) | Beacon | **Done** |
| 2.2 | **Ingest rate limit** per project / API key | Beacon | **Done** |
| 2.3 | **Health / ready** endpoints + Messenger queue depth signal | Beacon | **Done** |
| 2.4 | Production scaling / backup notes in `docs/PRODUCTION.md` | Beacon | **Done** |
| 2.5 | `nowo-tech/login-throttle-bundle` on AuthKit login (**database** storage shared across workers) | Beacon | **Done** |

---

## Phase 3 — Client instrumentation depth (Done; 3.1–3.6)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 3.1 | Capture **Messenger** worker failures (`WorkerMessageFailedEvent`) | Bundle | — | **Done** |
| 3.2 | **Auto HTTP transaction** (route/controller + duration) | Bundle | — | **Done** (opt-in) |
| 3.3 | Opt-in **Doctrine** + **HttpClient** breadcrumbs/spans | Bundle (+ Beacon UI) | `024-client-spans` | **Done** |
| 3.4 | Public **`tags`** API + **`before_send`** scrubbing hook | Bundle (+ Beacon UI) | `023-client-tags-scrubbing` | **Done** |
| 3.5 | Non-blocking client transport (async/queue) + versioned User-Agent | Bundle | — | **Done** (Bundle **v1.6.0**) |
| 3.6 | Contract tests: golden Envelope ↔ Beacon `ProcessEnvelopeHandler` | Bundle (+ Beacon) | — | **Done** |

---

## Phase 4 — Product depth (Done — v0.10.0)

Ordered Speckit program (Beacon `014`→`022`; Bundle `023`/`024`):

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 4.1 | **Releases**: filter, “new in release”, compare environments | Beacon | `014-releases` | **Done** |
| 4.2 | **Issue workflow**: comments, priority, mark-duplicate (+ merge), saved views | Beacon | `015-issue-workflow` | **Done** |
| 4.3 | **Issue search & scale**: full-text, tag/URL/user/release filters, SQL-only sorts | Beacon | `016-issue-search` | **Done** |
| 4.4 | **Export + issue lifecycle webhooks** (CSV/JSON; not only alert notify) | Beacon | `017-export-webhooks` | **Done** |
| 4.5 | **Project governance**: per-project retention/rate/quota in Settings; key revoke/rotate | Beacon | `018-project-governance` | **Done** |
| 4.6 | **Admin project ops**: stats, suspend ingest, admin audit, view-as-member | Beacon | `019-admin-projects-ops` | **Done** |
| 4.7 | **Notification digest / quiet hours** (no native PagerDuty) | Beacon | `020-notification-digest` | **Done** |
| 4.8 | **Project health UI**: Messenger queue, webhook failures, last deliveries | Beacon | `021-project-health-ui` | **Done** |
| 4.9 | Analytics + Performance **functional** tests + CI coverage | Beacon | `022-analytics-perf-ci` | **Done** |

---

## Phase 5 — Access & insights (In progress)

Ordered Speckit program. Prefer AuthKit / Symfony login-link for magic login; do not hand-roll auth. **SSO/OIDC** stays Later (separate from `026`).

### High impact

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.1 | **Analytics charts**: period presets / range + filters (env, release) | Beacon | `025-analytics-charts` | **Done** |
| 5.2 | **Magic login links** + project **viewer** role; optional signed share links | Beacon | `026-magic-links-viewer` | **Done** |
| 5.3 | **Threshold alerts**: e.g. &gt; N errors in M minutes (plus existing new/regression) | Beacon | `027-threshold-alerts` | **Next** |

### Medium impact

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.4 | **Release health** panel: new-in-release counts + compare (builds on `014`) | Beacon | `028-release-health` | **Planned** |
| 5.5 | **Issue FULLTEXT** search (upgrade `016` `LIKE` path) | Beacon | `029-issue-fulltext` | **Planned** |
| 5.6 | **Delivery history**: last N attempts per notification destination (extends `021`) | Beacon | `030-delivery-history` | **Planned** |
| 5.7 | **Admin project audit timeline** on Admin → Project show (extends `019`) | Beacon | `031-admin-project-audit` | **Planned** |

### Later (still in backlog)

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 5.8 | **Monthly event quota** (alongside daily; extends `018`) | Beacon | `032-monthly-quota` | **Later** |
| 5.9 | **CI coverage report** (informational / soft threshold; not 100% gate) | Beacon | `033-coverage-ci` | **Later** |
| — | **SSO/SAML/OIDC** via AuthKit / dedicated enterprise spec | Beacon | — | **Later** |

Do **not** reinvent: native PagerDuty, session replay, multi-org SaaS control plane — use HTTP webhooks + digests instead.

---

## Explicitly out of scope (for now)

- Multi-region SaaS control plane / multi-org tenancy
- **SSO/SAML/OIDC** until an enterprise dedicated spec (AuthKit); not the same as magic links in `026`
- Source maps / session replay / profiling
- Uptime monitors / cron check-ins as first-class products
- Native store apps inside this repo (server contract only)
- **PagerDuty-native** (generic HTTP webhook / digests may still target it)
- Public anonymous issue boards (share links in `026` still require constrained auth / viewer semantics)
- Enforcing **100%** code coverage

See `docs/ARCHITECTURE.md` non-goals and constitution.

---

## Suggested release slicing

| Release (indicative) | Contents |
|----------------------|----------|
| **v0.7.0** | Phase 1 — project notifications + ignored regression reopen |
| **v0.8.0** | Phase 2 — retention, rate limit, health |
| **Bundle v1.4.0** | Phase 3.1–3.2 (Messenger + auto HTTP tx) |
| **Bundle v1.5.0** | Phase 3.3–3.4 (spans, tags, before_send) |
| **Bundle v1.6.0** | Phase 3.3–3.5 (spans, tags, before_send, transport sync/async/messenger + versioned UA) |
| **Bundle v1.6.1** | Phase 3.6 golden Envelope contract fixtures + ingest tests |
| **v0.9.0+** | Phase 4 slices; admin Projects; transfer ownership |
| **v0.10.0** | Phase 4 product depth (`014`–`022`) + Bundle companion docs (`023`–`024`) |
| **v0.10.1** | Issue aside / duplicate-modal UX; admin menu seeder sync; Phase 5 specs started |
| **v0.10.2** | Phase 5 backlog specs `027`–`033`; unified confirm/kit modal chrome |
| **v0.11.0** | Analytics charts (`025`); locales de/nl/fr/it/pt; UI density/motion; danger colors; shared table pagination |
| **v0.11.1** | Magic login + viewer + share links (`026`); golden Envelope contract (3.6) |
| **v0.12+** | Phase 5 medium: release health, FULLTEXT, delivery history, admin audit (`028`–`031`); threshold alerts (`027`) |

Versions are indicative; cut releases when exit criteria for a phase (or a coherent subset) are met.

---

## How to work this roadmap

1. Pick the highest **In progress** / **Next** row that is unblocked.
2. Ensure a feature spec exists (`/speckit-specify` or update existing).
3. Plan → tasks → implement → tests → changelog/upgrading.
4. Mark the row **Done** and bump the indicative release when shipping.

Last updated: 2026-07-21.
