# Feature Specification: Product Tour

**Feature Branch**: `057-product-tour`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: After install/setup (`055`/`056`), guide users with driver.js tours that vary by **page**, **instance role**, and **project permissions**; mark each page seen independently (or suppress all from Account → Display).

## User Scenarios

### US1 — Contextual first visit (P1)

As a signed-in user on an instance whose setup is complete, the first time I open a supported page (`/dashboard`, project Issues, `/admin`) I see a tour tailored to that page and my permissions.

### US2 — Role / permission filtering (P1)

As a non-admin I do not see Administration steps. As a project viewer I do not see triage/saved-view or admin-settings steps that only apply to higher project roles.

### US3 — Prefer not to see tours (P1)

As a user, I can mark all tours as seen from Account → Display. Completing or closing a page tour marks that page only.

### US4 — Replay (P2)

As a user, I can clear seen flags and replay from Display preferences (`/dashboard?tour=1`).

## Requirements

- **FR-001**: Persist `product_tour_seen_at` (suppress all) and `product_tour_seen_pages` (JSON list of page ids).
- **FR-002**: Auto-start per page when setup is complete and that page is not yet seen.
- **FR-003**: Steps filtered by `ROLE_ADMIN` and `ProjectRole` capabilities.
- **FR-004**: English UI strings; other locales at least EN copy.

## Out of Scope

- Tours for every secondary screen (performance/analytics detail).
- Auto-start while the setup wizard is still pending.
