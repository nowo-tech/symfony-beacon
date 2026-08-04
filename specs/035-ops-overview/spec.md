# Feature Specification: Ops Overview Dashboard

**Feature Branch**: `035-ops-overview`  
**Created**: 2026-07-31  
**Status**: Implemented  

**Input**: Instance-admin **Ops overview** that surfaces cross-project error spikes, open issues, and failed notification deliveries (with optional project filter), so operators do not need to open every project. Complements per-project Health (`021` / `030`) and Admin → Projects (`019`). Prefer aggregating existing repositories/services over new telemetry stores.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Instance ops at a glance (Priority: P1)

As an instance admin, I open Ops overview from the Administration hub and see fleet-level health: Messenger queue depth, open issues totals, suspended projects, top error spikes, and destinations with recent delivery failures.

**Independent Test**: Seed two projects with open issues + one failed delivery + known queue depth stub; `ROLE_ADMIN` GET overview → sections populated; `ROLE_USER` → 403.

**Acceptance Scenarios**:

1. **Given** I am `ROLE_ADMIN`, **When** I open Ops overview, **Then** I see instance Messenger async pending depth (labeled instance-wide) and aggregate open-issue counts.
2. **Given** at least one project with elevated recent errors vs a short baseline, **When** I view the spikes section, **Then** that project appears in a capped top-N list with a clear metric.
3. **Given** a destination with `lastDeliverySuccess = false` (or a recent failed attempt), **When** I view failed deliveries, **Then** I see project, destination label/type, and time — **without** raw webhook secrets/URLs.
4. **Given** I am not an instance admin, **When** I request the overview URL, **Then** access is denied.

### User Story 2 - Optional project filter (Priority: P1)

As an instance admin, I filter the overview to a single project and see that project’s open issues / spikes / delivery failures while queue depth remains clearly instance-wide.

**Acceptance Scenarios**:

1. **Given** multiple projects with data, **When** I select project A, **Then** spike/open-issue/delivery rows are limited to A.
2. **Given** a filter is applied, **When** I clear it, **Then** fleet-wide lists return.

### User Story 3 - Drill into project health (Priority: P2)

As an instance admin, I jump from a failed-delivery or spike row to the project’s existing Health / Admin project surface for detail (`021` / `030` / Admin project show).

**Acceptance Scenarios**:

1. **Given** a failed-delivery row, **When** I follow its link, **Then** I land on an authorized project health or settings URL for that project.
2. **Given** a spike row, **When** I follow its link, **Then** I land on Admin project show or project Issues for that project.

## Edge Cases

- Healthy fleet: empty/healthy states (no spikes, no failed deliveries) must not look like an error.
- High cardinality: lists capped (document N, e.g. 25); no full table scans unbounded in the request.
- Suspended ingest projects appear in a dedicated count/list when any exist.
- Shared Messenger worker: queue metric is instance-wide and must not be mislabeled as project-scoped.
- SQLite test DB and MySQL prod: aggregations MUST work on both (same constraint as other admin stats).

## Requirements *(mandatory)*

- **FR-001**: Provide an Ops overview page restricted to `ROLE_ADMIN`, linked from Administration hub (and admin navigation/seeder when menus are product-seeded).
- **FR-002**: Show instance Messenger async pending depth via existing `MessengerQueueHealth` (or equivalent); label as instance-wide.
- **FR-003**: Show aggregate open-issue counts and per-project open issues (reuse `ProjectOpsStatsService` / `IssueRepository` batch helpers where possible).
- **FR-004**: Show error-spike candidates using existing daily/event aggregates (`DailyProjectStat` and/or `EventRepository`); define spike rule in plan (e.g. last 24h vs prior 7d average) and keep it deterministic for tests.
- **FR-005**: Show failed deliveries from destination last-delivery fields and/or recent `NotificationDeliveryAttempt` failures; never render webhook secrets or full private URLs.
- **FR-006**: Optional project filter (UUID); empty = all projects.
- **FR-007**: English UI catalogues under `messages.*` with key parity for enabled locales (EN source of truth; other locales may copy EN until translated).
- **FR-008**: Functional tests: admin 200 + data assertions; non-admin 403; filter scopes rows.

## Success Criteria

- **SC-001**: An admin diagnoses a noisy project and a failed webhook from one page without opening every project.
- **SC-002**: Non-admins cannot access Ops overview.
- **SC-003**: Overview request stays within a documented row/project cap suitable for self-hosted fleets (hundreds of projects, not SaaS multi-tenant scale).

## Assumptions

- Per-project Health (`021`) and delivery history (`030`) remain the drill-down detail surfaces.
- No new persistent metrics store for MVP; compute on read with caps.
- Prometheus scrape (`038`) is out of scope and may later reuse the same underlying counters.

## Out of Scope

- Prometheus `/metrics` endpoint (`038`).
- Notification circuit breaker auto-pause (`039`).
- Multi-org / tenant control plane.
- Real-time websocket push of overview widgets (Mercure optional later).
- Replacing Admin → Projects list.
- Member-scoped failed-delivery inbox (not admin Ops): **`080-dashboard-aside-panels`** Alerts panel.
