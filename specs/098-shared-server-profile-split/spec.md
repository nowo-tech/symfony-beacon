# Feature Specification: Shared MySQL mode & account profile split (2026-08-15)

**Feature Branch**: `098-shared-server-profile-split`  
**Created**: 2026-08-15  
**Status**: Implemented (v1.14.0)  
**Roadmap**: Phase 6.48  

**Input**: Operators need Beacon on shared `developer.local.server` / little-vps MySQL (no embedded `database` service) with the same `DATABASE_URL` template; Account → Profile must separate non-sensitive edits from email/Slack changes that require the current password; Doctrine identity table should be named `user` (quoted reserved word) instead of `app_user`.

## Summary

Beacon stays standalone by default (`make up`). Shared mode (`make up-shared` + `compose.shared.yaml`) joins an external Docker network and MySQL primary/replica hostnames. Account profile is two named forms (display/phone vs email/Slack + password). Migration `Version20260814230000` renames `app_user` → `user`.

## Scope (delivered)

| ID | Area | Deliverable |
|----|------|-------------|
| C1 | Shared Compose | `compose.shared.yaml` (profile-out local `database`, external `${SHARED_DOCKER_NETWORK}`); `.compose-mode` marker; Makefile `up-shared` / `down-shared` / `bootstrap-shared-db` / `$(DC)` |
| C2 | Env contract | `.env.dist`: `MYSQL_HOST` / `MYSQL_HOST_RO` / ports; `DATABASE_URL` / `DATABASE_URL_RO` templates; commented shared-mode block |
| C3 | Bootstrap | `.scripts/bootstrap-shared-db.sh` — `CREATE DATABASE IF NOT EXISTS`; optional non-root app user |
| C4 | Docs | `docs/ops/SHARED-SERVER.md`; README / docs index / DATABASE ER diagram `user` |
| C5 | Profile UX | `AccountProfileType` (display + phone) + `AccountProfileSensitiveType` (email + Slack + required current password); named forms `user_profile` / `user_profile_sensitive` |
| C6 | Schema | Entity table `user`; `Version20260814230000`; AuditFields FK defaults → `user` |
| C7 | Tests / E2E | PHPUnit profile/sensitive coverage; Playwright selectors for split forms + Slack hook helpers |

## Amendments

### 2026-08-15 — Phone field via phone-input kit (`100`)

`AccountProfileType` phone is no longer a free-text `Length(max: 32)` field. It uses `nowo-tech/phone-input-bundle` `PhoneType` (country ISO + national number → concatenated E.164 on `User.phone`). HTML names are `user_profile[phone][country_iso]` / `[national_number]`; FormKit catalogue prefix remains `user_preferences`. See `specs/100-phone-input-profile/`.

## Non-goals

- Doctrine read/write routing to `DATABASE_URL_RO` (documented placeholder only)
- Replacing AuthKit login/register flows
- Automating little-vps Ansible / dedicated prod app-user provisioning beyond env docs

## User Scenarios & Testing

### User Story 1 - Shared MySQL without local database container (P1)

**Why this priority**: Multi-app local stacks and little-vps already run MySQL on `server_network` / `server_internal`; embedding a second MySQL wastes resources and diverges from prod.

**Independent Test**: With shared MySQL up and `.env` pointing at `mysql-9.7-primary`, `make up-shared` starts php/messengers without a Beacon `database` container; `make ready` migrates.

**Acceptance Scenarios**:

1. **Given** `MYSQL_HOST=database`, **When** `make up-shared`, **Then** the target refuses with a clear env hint.
2. **Given** valid shared env + running primary, **When** `make up-shared`, **Then** schema is ensured and app joins `SHARED_DOCKER_NETWORK`.
3. **Given** standalone mode, **When** `make up`, **Then** behaviour remains the embedded `database` path (`.compose-mode` cleared).

### User Story 2 - Sensitive profile fields require password (P1)

**Why this priority**: Email is the login identifier; Slack user ID drives Assign/Resolve. Changing either without re-auth is unsafe; mixing them with display-name saves caused confusing empty-password failures.

**Independent Test**: Save display name without password succeeds; email change without current password fails and does not persist.

**Acceptance Scenarios**:

1. **Given** Account → Profile, **When** only display name/phone is submitted, **Then** save succeeds without current password.
2. **Given** the sensitive panel, **When** email or Slack ID is submitted without a valid current password, **Then** values revert and an error is shown.
3. **Given** a valid current password, **When** email/Slack is unique, **Then** sensitive save persists and flashes success.

### User Story 3 - Identity table named `user` (P2)

**Why this priority**: Align mapping with a conventional table name; MySQL reserved word is handled via quoted identifiers in the rename migration.

**Independent Test**: After `make migrate`, Doctrine schema uses table `user`; previous `app_user` is gone when the rename ran.

**Acceptance Scenarios**:

1. **Given** a DB still on `app_user`, **When** `Version20260814230000` runs, **Then** table is renamed to `user`.
2. **Given** a DB already on `user`, **When** the migration runs again, **Then** it is a no-op (idempotent guards).

## Dependencies

- External shared MySQL (`developer.local.server/server` or little-vps)
- FormKit host catalogues (`translations/form.*.yaml` keys under `user_preferences`)
- Prior Slack user-id password gate (`096`)

## Assumptions

- Local shared mode may use MySQL `root` (same password as `server/.env`); VPS should prefer a dedicated app user.
- Form HTML names differ from FormKit block prefix (`user_preferences`) so both panels share catalogue keys.
- Replica hostname is configured for future read routing only.

## Success Criteria

- `make up` and `make up-shared` documented and operational for their env contracts.
- Account profile PHPUnit + UC-ACC-17/18 (and Slack hook helpers) pass against the split forms.
- Migration renames `app_user` → `user` safely on upgrade.

## Implementation notes

- Ops: `docs/ops/SHARED-SERVER.md`, `compose.shared.yaml`, `.scripts/bootstrap-shared-db.sh`.
- Forms: `src/Identity/Form/AccountProfileType.php`, `AccountProfileSensitiveType.php`.
- Migration: `migrations/Version20260814230000.php`.
