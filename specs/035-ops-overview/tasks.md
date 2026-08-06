# Tasks: Ops Overview Dashboard (`035`)

**Input**: `specs/035-ops-overview/spec.md`  
**Status**: Implemented

## Phase 1: Service & queries

- [x] T001 Add cross-project helpers on notification destinations / delivery attempts (failed last delivery + recent failures; no secrets)
- [x] T002 `App\Ops\Service\OpsOverviewService` (queue depth, open issues, suspended count, spikes, failed deliveries; optional project UUID filter; caps; `085`)
- [x] T003 Deterministic spike rule + unit/fixture coverage for the calculation

## Phase 2: UI & nav

- [x] T004 `App\Ops\Controller\AdminOpsOverviewController` + `/admin/ops` (`ROLE_ADMIN`; `085`)
- [x] T005 Twig overview (healthy/empty states, filter, drill-down links)
- [x] T006 Admin hub card + platform menu/breadcrumb seed keys for Ops overview

## Phase 3: i18n & quality

- [x] T007 `messages.*` keys `admin.ops.*` (EN + parity for enabled locales)
- [x] T008 PHPUnit: admin 200 + scoped filter; non-admin 403
- [x] T009 CHANGELOG / ROADMAP / UPGRADING notes
