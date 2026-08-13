# Feature Specification: Module boundary hardening

**Feature Branch**: `083-module-boundaries`  
**Created**: 2026-08-06  
**Status**: Implemented (2026-08-06; Port + Metrics→Ops + creation fragment in **v1.11.0** / `095`)

**Input**: Architecture audit (2026-08-05): keep the deliberate modular Symfony shape (no DDD/hexagonal/SPA rewrite); harden eroded module boundaries — Shared catch-all growth, Identity↔Project cycle, Issues hotspots, Ingest orchestration/OTLP weight, shared async queue contention, docs/spec catalog drift (module map missing `Api`/`Setup`, duplicate `079-*` feature numbers), and thin Analytics/Performance automated coverage.

## Summary

Contributors and operators need a **stable module map** that matches the codebase and stays reviewable as features land. This feature restores **directional boundaries** documented in `docs/ARCHITECTURE.md` without changing product UX or the Envelope fast-ACK mission.

There is no new end-user product surface. Success is measurable as: docs ↔ code alignment, dependency direction, bounded Shared growth, maintainable Issues/Ingest surfaces, isolated async drain under burst, unique Spec Kit feature numbers, and stronger read-model tests.

## Scope (as-built)

| Area | Delivered |
|------|-----------|
| Module map | Constitution **1.4.1** + `docs/ARCHITECTURE.md` list `Api` / `Setup`; Shared growth rules in CONTRIBUTING |
| Domain enums | `App\Issues\Enum\{IssueStatus,IssuePriority,IssueLevel}`; `App\Project\Enum\ProjectRole` (left `Shared`) |
| Admin projects | `App\Project\Controller\AdminProjectController` — same `/admin/projects*` route names |
| Identity ↔ Project | `ProjectMembershipAdminPort` for admin unlink; dashboard new-project form via Project fragment (`095`) |
| Metrics scrape | `App\Ops\Metrics` (not Shared) — guarded by `.scripts/check-module-boundaries.sh` (`095`) |
| Issues queries | `IssueSearchRepository` (list/filter/search); `IssueRepository` (fingerprint/uuid/similarity lookups) |
| Issues HTTP | `IssueController` (index + saved views); `IssueDetailController` (show / triage / event); AI export → `IssueAiExportController` (`085`) |
| OTLP | `App\Ingest\Otlp\{Controller,Service}` (`OtlpIngestPipeline` + mappers in `086`); route import `controllers_ingest_otlp` |
| Messenger | Transports `async_ingest` vs `async`; Compose process split completed in `085` (`messenger` / `messenger-notify`) |
| Spec hygiene | `079-ops-env-to-db` renumbered → `084-ops-env-to-db`; `079-dashboard-assignments` kept |
| Tests | `AnalyticsPeriodResolverTest` + `AnalyticsSeriesServiceTest`; Performance list + `nplus1` filter |
| Demo seed | `Setup\Demo\*` + `Setup\Command\{SeedPlatform,SeedSample}` (left Shared) |
| Project HTTP | `AdminProjectController` + `AdminProjectAccessController`; `ProjectController` + `ProjectApiKeyController` + `ProjectDangerZoneController` |
| CI guardrail | `.scripts/check-module-boundaries.sh` (+ `make check-module-boundaries` / CI git-hygiene) |

### Deferred (convergence)

- ~~Further split of fat `IssueController`~~ — **Done**: `IssueController` (list/views) + `IssueDetailController` (detail/mutations); AI export extracted in `085`.
- ~~Moving large Shared demo seeders~~ — **Done**: `Setup\Demo\*` + `Setup\Command\{SeedPlatform,SeedSample}` (JSON fixtures in `085`).
- Residual structural follow-ups (Envelope writers, `Ops` module, channel formatters, Compose notify consumer) → **`085-architecture-convergence`** (Implemented).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Module map matches the product (Priority: P1)

As a maintainer reading the constitution and architecture rationale, I see every top-level package under `src/` (`Identity`, `Project`, `Ingest`, `Issues`, `Performance`, `Analytics`, `Notifications`, `Shared`, plus **`Api`** and **`Setup`**) with a clear responsibility and “why separate” note. Shared is defined as cross-cutting presentation / instance config — **not** a home for new domain enums, demo seeders of other domains, or write-path orchestration.

**Why this priority**: Doc drift already misleads Spec Kit and PR review; fixing the map is the cheapest high-leverage control.

**Independent Test**: Diff constitution Principle II + `docs/ARCHITECTURE.md` module map against `ls src/`; checklist that Shared responsibility text forbids domain catch-all growth.

**Acceptance Scenarios**:

1. **Given** the as-built tree under `src/`, **When** a contributor opens the architecture module map, **Then** `Api` (JSON read API) and `Setup` (first-run / SiteBackup bootstrap) appear with responsibilities.
2. **Given** a new feature that needs a domain enum or seeder owned by Issues/Performance/…, **When** the contribution guide / architecture rules are applied, **Then** the change is expected under that domain module — not under Shared — unless it is truly cross-cutting instance chrome.
3. **Given** Spec Kit directories, **When** feature numbers are listed, **Then** each numeric prefix is unique (the duplicate `079-dashboard-assignments` / `084-ops-env-to-db` conflict is resolved by renumbering one directory and updating cross-references).

---

### User Story 2 - Tenancy ownership direction (Priority: P1)

As a maintainer, **project administration** (list/suspend/ops stats / view-as-member for `ROLE_ADMIN`) and membership/tenancy code live under the **Project** (or a dedicated Admin facade owned by Project) capability boundary. Identity remains users, account prefs, AuthKit glue, and instance Security roles. Dependencies prefer **Project → Identity** (membership needs User), not Identity owning Project HTTP surfaces long-term.

**Why this priority**: The audit found ~symmetric cross-imports and `AdminProjectController` under Identity — the largest structural cycle vs the documented graph.

**Independent Test**: Admin project routes and Project-facing admin services no longer require Identity controllers to own Project entities’ primary admin UI; import inventory shows no Identity→Project controller ownership of project CRUD/ops (entity associations on User remain allowed).

**Acceptance Scenarios**:

1. **Given** `ROLE_ADMIN` opens Administration → Projects, **When** they suspend ingest or view ops stats, **Then** behaviour is unchanged for operators (URLs may redirect once if moved).
2. **Given** the post-change import graph, **When** reviewers inspect Identity controllers, **Then** they do not permanently own project tenancy administration (moved or thin-delegating).
3. **Given** membership and share-link flows, **When** exercised, **Then** Project still resolves User/Identity correctly (no regression on invite/member/share).

---

### User Story 3 - Issues surface stays maintainable (Priority: P2)

As a contributor changing issue list filters, search, or detail actions, I can locate **query/filter** behaviour and **HTTP orchestration** in focused units rather than thousand-line repository/controller files. Operator-visible behaviour (filters, FULLTEXT, saved views, status/assignee) remains the same.

**Why this priority**: `IssueRepository` / `IssueController` hotspots dominate regression risk on the primary debugging UX.

**Independent Test**: File size / responsibility split review + existing Issues PHPUnit/e2e scenarios still pass; new units cover extracted query paths where behaviour moved.

**Acceptance Scenarios**:

1. **Given** project Issues list with filters and pagination, **When** a member uses the table, **Then** results and URL state match pre-change behaviour.
2. **Given** issue detail mutations (status, assignee, comments entry points), **When** exercised, **Then** authorization and side effects (notifications hooks) still fire.
3. **Given** the Issues module after the split, **When** a reviewer measures the former hotspot files, **Then** each remaining entry point has a single primary responsibility (HTTP vs query vs mutation service).

---

### User Story 4 - Ingest stays fast; adapters stay obvious (Priority: P2)

As an operator under error bursts, Envelope (and OTLP adapters that map into the same pipeline) still **authenticate and ACK quickly**, with heavy work async. Contributors can tell **Envelope wire** apart from **OTLP mapping** without reading one undifferentiated Ingest folder. Envelope processing remains an orchestrator with explicit domain calls — not an unbounded god-handler.

**Why this priority**: OTLP LOC already rivals Envelope core; one shared async transport also mixes ingest drain with notifications/HTTP-log.

**Independent Test**: Ingest functional tests (Envelope + OTLP) green; under load notes or queue config, ingest messages are not starved by notification/http-log consumers; architecture doc describes OTLP as adapters into Envelope processing.

**Acceptance Scenarios**:

1. **Given** a valid Envelope POST, **When** auth succeeds, **Then** the client receives a success ACK before grouping/persistence completes.
2. **Given** OTLP logs/traces/metrics posts for a project, **When** accepted, **Then** they still produce Issues (or documented drops) via the same async processing path.
3. **Given** bursty ingest plus outbound notification/http-log jobs, **When** the worker topology is configured per this feature, **Then** ingest drain has an isolated queue or consumer so notification backlog does not block Envelope processing indefinitely.

---

### User Story 5 - Read-model confidence (Priority: P3)

As a maintainer, Analytics period charts/filters and Performance N+1 listing have automated coverage beyond smoke, so schema or filter changes fail CI before operators notice.

**Why this priority**: Audit showed Analytics=1 and Performance=2 PHPUnit classes vs much denser Shared/Identity coverage.

**Independent Test**: New/extended PHPUnit scenarios for Analytics filters/aggregates and Performance N+1 filter; CI green.

**Acceptance Scenarios**:

1. **Given** seeded daily stats and events, **When** Analytics period/filter scenarios run in CI, **Then** they assert series/table outcomes for at least one preset and one filter dimension.
2. **Given** a transaction with repeated db-like spans, **When** Performance N+1 scenarios run, **Then** the N+1 filter/listing expectations hold.

---

### Edge Cases

- Moving admin project routes MUST preserve bookmarks via redirects or keep stable paths.
- Shared may still *compose* domain repositories for instance ops overview / retention purge; it MUST NOT become the owner of new domain write rules.
- Renaming a duplicate `079-*` spec directory MUST update ROADMAP, cross-links in other specs, and `.specify/feature.json` if it pointed at the old path.
- Worker Compose changes MUST remain optional-compatible with single-consumer dev setups (document defaults).
- No behaviour change to Envelope auth deprecation headers or governance re-check after ACK (`051`).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Constitution Principle II and `docs/ARCHITECTURE.md` module map MUST list `Api` and `Setup` with responsibilities aligned to as-built code.
- **FR-002**: Architecture docs MUST state Shared growth rules: presentation / instance settings / cross-cutting glue only; domain enums, domain demo seeders, and ingest orchestration belong in owning modules.
- **FR-003**: Spec Kit feature directories MUST use unique numeric prefixes; resolve the `079-dashboard-assignments` vs `084-ops-env-to-db` clash by renumbering one and updating references.
- **FR-004**: Project tenancy administration for `ROLE_ADMIN` MUST be owned under the Project boundary (or Project-owned Admin facade); Identity MUST NOT permanently own that HTTP surface.
- **FR-005**: Documented module dependency direction MUST match code for the happy path: Ingest writes Issues/Performance/Analytics/Notifications; UI modules depend on Project; Project depends on Identity; Shared does not own domain write paths.
- **FR-006**: Issues list/detail maintainability MUST improve by splitting query vs HTTP vs mutation responsibilities out of the current hotspot entry points without changing member-visible behaviour.
- **FR-007**: OTLP adapters MUST be an explicit Ingest sub-area (package/namespace or documented folder) separate from Envelope HTTP/parse/auth.
- **FR-008**: Async processing for Envelope MUST be isolatable from notification delivery and HTTP-log persistence (separate transport and/or consumer) so ingest drain is not indefinitely blocked by those workloads.
- **FR-009**: Analytics and Performance MUST gain automated tests covering primary read-model scenarios called out in User Story 5.
- **FR-010**: Operator-facing ingest, issues, admin projects, analytics, and performance flows MUST keep existing capabilities (no intentional product regression); URL moves require redirects or documented breakage only inside this feature’s tasks.

### Key Entities

- **Module (logical)**: Top-level capability package under `src/` with documented responsibility and allowed dependency directions.
- **Spec catalog entry**: `specs/NNN-name/` with unique `NNN` across the repository.
- **Async workload class**: Ingest processing vs outbound notification/http-log — separable for drain isolation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new contributor can map every `src/*` top-level directory to a row in the architecture module map in under 5 minutes with no omissions.
- **SC-002**: Import/ownership review shows Project (not Identity) owns admin project tenancy UI after the move; operator admin project tasks still complete without workaround docs.
- **SC-003**: Primary Issues list filter + detail status/assignee scenarios pass automated tests after the maintainability split.
- **SC-004**: Envelope ACK-before-heavy-work property remains covered by automated ingest tests; OTLP acceptance paths remain green.
- **SC-005**: Under a constructed backlog of non-ingest async jobs, ingest messages still process within an operator-documented bound (or separate consumer is the default in Compose guidance).
- **SC-006**: Spec feature numbers are unique (zero duplicate `NNN-` prefixes).
- **SC-007**: CI includes Analytics and Performance tests beyond the pre-feature baseline counts (at least one new scenario class or substantially extended coverage each).

## Assumptions

- Full DDD/hexagonal rewrite, separate SPA, and Nginx+FPM remain **out of scope** (constitution).
- AuthKit / UserKit / other nowo-tech kits stay the auth and chrome solution; this feature does not reintroduce a custom `SecurityController`.
- `079-dashboard-assignments` keeps number `079` (already on ROADMAP as Done); `084-ops-env-to-db` is the candidate to renumber unless planning finds a better unique id.
- Demo seeders may move modules without changing `make seed` / `make seed-sample` operator outcomes.
- Single-queue Messenger remains acceptable only as an explicit transitional default documented until FR-008 lands. **Superseded as-built**: dual transports shipped; assumption retained only for historical context.
- `IssueController` HTTP/mutation extraction is **Done** (`IssueDetailController`); FR-006 met for query + HTTP surfaces.

## Out of scope

- New operator product features (SSO, WebAuthn, OTLP gRPC, etc.).
- Notifications channel formatters / Issues AI export controller (delivered in `085`).
- Enforcing module boundaries via custom PHPStan rules (optional Later; not required for Done).
- Performance profiling / production load testing beyond documented Compose topology.

## Dependencies

- Architecture rationale: `docs/ARCHITECTURE.md`
- Constitution: `.specify/memory/constitution.md` (Principle II module list; amend as part of FR-001)
- Prior features: `002-identity-project`, `003-ingest`, `004-issues`, `025-analytics-charts`, `035-ops-overview`, `051` (worker re-check), `056-setup-wizard`, `067`/`070`/`074` (OTLP), `084-ops-env-to-db`, `079-dashboard-assignments`
- Successor: `085-architecture-convergence`
- Audit artifact: Cursor canvas architecture audit 2026-08-05
