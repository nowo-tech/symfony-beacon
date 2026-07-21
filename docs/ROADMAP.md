# Product roadmap

Living plan for **symfony-beacon** and the companion client **[nowo-tech/beacon-bundle](https://github.com/nowo-tech/BeaconBundle)**. Priorities follow product completeness analysis (post-v0.6.0): close the operator loop (alerts), keep self-hosting safe, then deepen automatic instrumentation.

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

Goal: a team can be notified when something new or regressing happens, without blocking ingest.

| # | Item | Repo | Spec | Status |
|---|------|------|------|--------|
| 1.1 | **Project notifications** (Slack, Discord, Teams, Telegram, email, generic HTTP) | Beacon | `009-project-notifications` | **Done** |
| 1.2 | **Regression rules**: reopen `resolved` **and** `ignored` → unresolved on matching event; notify on new + regression only | Beacon | `009` / ingest | **Done** |
| 1.3 | Settings UI: destinations CRUD, category filters, masked URLs, send-test, **setup guides** | Beacon | `009` | **Done** |
| 1.4 | Async delivery + bounded retries via Messenger + **SSRF guard** | Beacon | `009` | **Done** |
| 1.5 | **API docs in Panel** (Nelmio OpenAPI / Swagger in app shell) | Beacon | `013-api-docs-panel` | **Done** |

### Exit criteria (Phase 1)

- [x] Owner/admin can add Slack + HTTP destinations with level + N+1 filters.
- [x] New issue and regression notify; duplicates on open issues stay silent.
- [x] Ingest ACK does not wait on destination HTTP.
- [x] PHPUnit covers permissions, filters, occurrence rules, async dispatch.
- [x] Changelog / upgrading notes for the release that ships 009 (Unreleased until tag).

---

## Phase 2 — Safe self-hosting (Done)

| # | Item | Repo | Status |
|---|------|------|--------|
| 2.1 | Configurable **retention** + purge job (max age / max events per project) | Beacon | **Done** |
| 2.2 | **Ingest rate limit** per project / API key | Beacon | **Done** |
| 2.3 | **Health / ready** endpoints + Messenger queue depth signal | Beacon | **Done** |
| 2.4 | Production scaling / backup notes in `docs/PRODUCTION.md` | Beacon | **Done** |
| 2.5 | `nowo-tech/login-throttle-bundle` on AuthKit login (**database** storage shared across workers) | Beacon | **Done** |

### Exit criteria (Phase 2)

- [x] Operators can bound disk growth and storm traffic.
- [x] Orchestrators can probe liveness without scraping logs.
- [x] Login brute-force baseline in place.

---

## Phase 3 — Client instrumentation depth (In progress)

Feed Performance/N+1 and richer events without hand-rolled demo code.

| # | Item | Repo | Status |
|---|------|------|--------|
| 3.1 | Capture **Messenger** worker failures (`WorkerMessageFailedEvent`) | Bundle | **Done** (Unreleased) |
| 3.2 | **Auto HTTP transaction** (route/controller + duration) | Bundle | **Done** (opt-in `auto_http_transaction`) |
| 3.3 | Opt-in **Doctrine** + **HttpClient** breadcrumbs/spans | Bundle | Planned |
| 3.4 | Public **`tags`** API + **`before_send`** scrubbing hook | Bundle | Planned |
| 3.5 | Non-blocking client transport (async/queue) + versioned User-Agent | Bundle | Planned |
| 3.6 | Contract tests: golden Envelope ↔ Beacon `ProcessEnvelopeHandler` | Bundle (+ Beacon) | Planned |

---

## Phase 4 — Product depth (Later)

| # | Item | Repo | Status |
|---|------|------|--------|
| 4.1 | **Releases** entity + “new in release” / filter by `release_version` | Beacon | Later |
| 4.2 | Server-side issue search/pagination beyond soft caps | Beacon | Later |
| 4.3 | CSV/JSON **export** of issues/events | Beacon | Later |
| 4.4 | Analytics + Performance **functional** tests; coverage in CI | Beacon | Later |
| 4.5 | AuditKit / UserKit migration where AuthKit alone is thin | Beacon | Later |

---

## Explicitly out of scope (for now)

Documented so the roadmap does not silently expand mission:

- Multi-region SaaS control plane / multi-org tenancy
- SSO/SAML/OIDC (until a dedicated spec)
- Source maps / session replay / profiling
- Uptime monitors / cron check-ins as first-class products
- Native store apps inside this repo (server contract only)
- Email digests, PagerDuty-native, Discord-native bots (generic webhook may still target them)

See `docs/ARCHITECTURE.md` non-goals and constitution.

---

## Suggested release slicing

| Release (indicative) | Contents |
|----------------------|----------|
| **v0.7.0** | Phase 1 — project notifications + ignored regression reopen |
| **v0.8.0** | Phase 2 — retention, rate limit, health |
| **Bundle v1.4.0** | Phase 3.1–3.2 (Messenger + auto HTTP tx) |
| **Bundle v1.5.0** | Phase 3.3–3.5 (spans, tags, async transport) |
| **v0.9.0+** | Phase 4 slices as capacity allows; **v0.9.1** admin unlink; **v0.9.2** transfer ownership; **v0.9.4** admin Projects CRUD |

Versions are indicative; cut releases when exit criteria for a phase (or a coherent subset) are met.

---

## How to work this roadmap

1. Pick the highest **In progress** / **Next** row that is unblocked.
2. Ensure a feature spec exists (`/speckit-specify` or update existing).
3. Plan → tasks → implement → tests → changelog/upgrading.
4. Mark the row **Done** and bump the indicative release when shipping.

Last updated: 2026-07-21.
