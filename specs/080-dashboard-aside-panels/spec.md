# Feature Specification: Dashboard aside panels

**Feature Branch**: `080-dashboard-aside-panels`  
**Created**: 2026-08-03  
**Status**: Done  
**Input**: Extend the Dashboard sidebar (beyond Projects + Assignments) with member-focused panels: day summary, recent activity, @mentions inbox, failed notification deliveries in accessible projects, and cross-project “new in release”. Watching/favorites deferred (needs a new model).

## User Scenarios & Testing

### User Story 1 - Day summary (Priority: P1)

As a signed-in user, I open **Summary** and see quick counts for my open assignments, unassigned open issues, unread mentions, failed deliveries, errors today, and accessible project count — each card linking to the matching panel when useful.

**Acceptance**: Route `/dashboard/summary`; counts limited to `findAccessibleByUser`; empty zeros allowed; no list pager (cards only).

### User Story 2 - Recent activity (Priority: P1)

As a member, I open **Activity** and see my recent product actions (open/assign/comment/status/…) across accessible projects, optionally filtered by project, with standard list pagination.

**Acceptance**: Route `/dashboard/activity`; actor-scoped allowlist (`DashboardProductActivity`); SQL filter on `context.project_uuid` ∈ accessible (or selected) projects; `PagePagination` + `_table_pagination`; rows link to issue/project when context UUIDs exist.

### User Story 3 - Mentions inbox (Priority: P1)

As a member, I open **Mentions** and see comments where I was `@mentioned` in accessible projects; I can filter unread-only, mark one or all read, and page results.

**Acceptance**: Route `/dashboard/mentions`; persisting `issue_mention` on comment create (migration `Version20260803140000`); CSRF-protected mark-read / mark-all-read via `MentionsMarkReadType` / `MentionsMarkAllReadType` (`090`); only project-accessible mentions; `PagePagination`.

### User Story 4 - Alerts / failed deliveries (Priority: P1)

As a member, I open **Alerts** and see notification destinations in my projects whose last delivery failed (not instance-admin Ops-only), with standard list pagination.

**Acceptance**: Route `/dashboard/alerts`; scoped to accessible projects; links to project settings / destination edit; no raw secrets/URLs beyond existing masked settings patterns; `PagePagination`.

### User Story 5 - New in release (Priority: P2)

As a member, I open **New in release** and browse issues with `firstRelease` set across my projects, filterable by project and release.

**Acceptance**: Route `/dashboard/new-in-release`; paginated table (`PagePagination`); complements project Release health (`028`).

### User Story 6 - Nav + breadcrumbs (Priority: P1)

As a member, sidebar Dashboard items and breadcrumbs exist for every new panel route after `app:seed-platform`.

**Acceptance**: Menu positions ~30–70; crumbs parented under `dashboard_home` so trails render with `hide_when_single_root`; Assignments also nested under Projects for the same reason.

## Requirements

- **FR-001**: Seeded Dashboard menu items: Summary, Activity, Mentions, Alerts, New in release (plus existing Projects / Assignments).
- **FR-002**: Seeded breadcrumbs for `dashboard_summary`, `dashboard_activity`, `dashboard_mentions`, `dashboard_alerts`, `dashboard_new_in_release`, and `dashboard_assignments`, each with parent `dashboard_home`.
- **FR-003**: All panels restrict data to projects from `findAccessibleByUser`.
- **FR-004**: Mentions persist via `issue_mention` (`comment_id`, `mentioned_user_id`, `created_at`, `read_at`) created in `IssueCommentCreator` from `IssueMentionParser` resolutions; table created by `DoctrineMigrations\Version20260803140000`.
- **FR-005**: Activity uses allowlisted `UserActionType` values for product work (not auth/admin identity noise).
- **FR-006**: Alerts use destinations with `lastDeliverySuccess = false` and non-null `lastDeliveryAt` within accessible projects.
- **FR-007**: New-in-release lists issues where `firstRelease IS NOT NULL`, optional release/project filters, pagination.
- **FR-008**: Summary aggregates mine-open, unassigned-open, unread mentions, failed deliveries, errors today (`DailyProjectStat` last day), project count.
- **FR-009**: English UI + seeder/locale translations for nav and panel copy.
- **FR-010**: Functional coverage for panel routes, mention persistence/read, and breadcrumb wrap presence.
- **FR-011**: List panels (Assignments, Activity, Mentions, Alerts, New in release) use `PagePagination` + `shared/_table_pagination.html.twig` (→ `kit/_pagination` / UiKit `_pagination`; see `081`) with `page` / `per_page` (10|25|50|100). Summary is cards-only (no list pager).

## Key Entities

- **IssueMention**: Inbox row linking an `IssueComment` to a mentioned `User`, with optional `readAt`.
- **UserAction**: Existing audit/product timeline; Activity panel filters by actor + allowlist + `context.project_uuid`.
- **NotificationDestination**: Existing delivery health fields power Alerts (member-scoped) vs Ops overview (admin).

## Success Criteria

- **SC-001**: After migrate + `app:seed-platform`, each panel URL returns 200 for a project member and shows sidebar labels.
- **SC-002**: Creating a comment with `@localpart` inserts `issue_mention` and appears in Mentions for that user.
- **SC-003**: Breadcrumb wrap `.beacon-breadcrumb` appears on Assignments and all new panel routes (not a lone hidden root).
- **SC-004**: Failed destination fixtures appear on Alerts; `firstRelease` fixtures appear on New in release.
- **SC-005**: Activity, Mentions, Alerts, Assignments, and New in release expose `per_page` and use `shared/_table_pagination.html.twig` (UiKit pager chrome) when `total > 0`.

## Related

- `079-dashboard-assignments` — Assignments panel (sibling; breadcrumbs + pagination aligned here).
- `081-formkit-uikit-kit-sync` — shared pagination → UiKit 1.7+.
- `040-issue-mentions-notify` — Email on mention/assign; this feature adds the in-app inbox.
- `028-release-health` — Project-scoped new-in-release; this feature adds cross-project panel.
- `035-ops-overview` — Admin fleet failed deliveries; Alerts is the member-scoped counterpart.

## Amendment (Symfony Forms + title weight, 2026-08-11)

- Mentions mark-read / mark-all-read POSTs use `MentionsMarkReadType` / `MentionsMarkAllReadType` (`090`).
- Dashboard product page titles use lighter chrome: `h1` `text-2xl font-semibold` + quieter intro (`text-xs` / lower opacity); kit admin page-header title/intro aligned in `_kit_admin_styles`.

## Amendment (FormKit GET filters, 2026-08-13)

Aside list filters (Mentions / New-in-release / Alerts / Activity as applicable) extend `AbstractGetFilterType` (profile `filter`). Shared `addDashboardPerPage` keeps **`per_page` required**; other fields optional. Twig: `form_row` + `_fields` / loop (`077`). Canonical contract + CSRF/authz non-regression: `081` FR-003a / `090` FR-007.

## Amendment (Mentions unread via `form_row`, 2026-08-13)

Mentions filter checkbox `unread` uses `form_row` with a Twig/`messages` caption (`mentions.filter.unread_only`). The Type keeps FormKit `label: false` (filter profile). No hand-rolled `form_widget` + sibling label. See `077` / `081` Twig consolidation amendments.

## Out of scope (v1)

- Watching / favorites (projects or issues) — needs a new follow model.
- Real-time push for mentions (Mercure/Web Push beyond existing new-issue push).
- Assign/reassign or Resolve actions from these panels.
- Instance-wide Ops metrics for non-admins.
- `plan.md` / data-model / contracts artifacts (as-built documented in this spec + tasks only).
