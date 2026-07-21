# Feature Specification: API Docs in Panel

**Feature Branch**: `013-api-docs-panel`  
**Created**: 2026-07-21  
**Status**: Completed (as-built)  

**Input**: Add NelmioApiDoc / OpenAPI docs with access from the Dashboard (Panel) section, integrated into the Beacon app shell (not a standalone Nelmio page).

## Summary

Authenticated operators browse Envelope ingest (and related `/api/*`) documentation via Swagger UI at `/api/doc`, linked from the **Panel** sidebar. The UI extends the Beacon layout (header, section sidebar, breadcrumbs). OpenAPI JSON remains at `/api/doc.json`. Docs require a logged-in session (`ROLE_USER`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Open API docs from Panel (Priority: P1)

As an authenticated user in the Dashboard/Panel section, I open **API docs** from the sidebar and see Swagger UI inside the Beacon chrome.

**Acceptance Scenarios**:

1. **Given** I am logged in and menus are seeded, **When** I open `/dashboard`, **Then** the Panel sidebar includes a link to `/api/doc`.
2. **Given** I open `/api/doc`, **When** the page loads, **Then** I see `[data-app-shell]`, the Panel sidebar, Beacon page title/intro, and `#swagger-ui` — not the standalone Nelmio logo header.
3. **Given** an anonymous user, **When** they request `/api/doc` or `/api/doc.json`, **Then** they are redirected to login.

### User Story 2 - Explore Envelope ingest (Priority: P1)

As an operator integrating an SDK, I read the documented `POST /api/{projectId}/envelope/` operation and Authorize with `X-Beacon-Auth`.

**Acceptance Scenarios**:

1. **Given** `/api/doc.json`, **When** parsed, **Then** `info.title` is Symfony Beacon API and paths include the Envelope ingest route.
2. **Given** Swagger UI, **When** I use Authorize, **Then** I can supply the `X-Beacon-Auth` header scheme documented in OpenAPI components.
3. **Given** the docs page, **When** I click OpenAPI JSON, **Then** `/api/doc.json` opens successfully for authenticated users.

## Requirements *(mandatory)*

- **FR-001**: System MUST expose OpenAPI JSON at `/api/doc.json` and Swagger UI at `/api/doc` (NelmioApiDocBundle).
- **FR-002**: Documented path patterns MUST cover `/api/*` except `/api/doc*`, and SHOULD include `/health/live` and `/health/ready`.
- **FR-008**: Envelope ingest OpenAPI MUST document auth (`X-Beacon-Auth` and query `beacon_key`/`beacon_secret`), request Content-Types, and responses `200`/`400`/`401`/`403`/`404`/`429` (including `Retry-After` on 429).
- **FR-003**: Swagger UI MUST render inside the Beacon `base` / app-shell layout (Twig override of Nelmio SwaggerUi template).
- **FR-004**: Panel sidebar MUST link to `app.swagger_ui` (Dashboard menu seed); breadcrumbs MAY include the route.
- **FR-005**: Both docs endpoints MUST require authentication (same `ROLE_USER` gate as the rest of the app).
- **FR-006**: UI strings for the embedded page MUST be English in docs and i18n in Twig (`en` / `es`).
- **FR-007**: Automated tests MUST cover anonymous redirect, authenticated shell markers, and OpenAPI JSON title/paths.

## Success Criteria

- **SC-001**: An authenticated operator reaches usable Swagger UI from Panel in under 30 seconds without leaving the app shell.
- **SC-002**: PHPUnit `ApiDocAccessTest` stays green in CI.

## Assumptions

- Envelope auth for “Try it out” uses project DSN secrets (`beacon_key` / `beacon_secret`); session login only gates access to the docs UI.
- CDN assets for Swagger UI are acceptable (`html_config.assets_mode: cdn`).

## Out of Scope

- Public anonymous API docs.
- Documenting non-`/api` HTML dashboard routes in OpenAPI.
- Replacing BeaconBundle client docs (see `docs/DSN.md`).
