# Implementation Plan: Admin Projects Ops

**Branch**: `019-admin-projects-ops`  
**Spec**: [spec.md](./spec.md)  
**Status**: Implemented  

## Technical Context

| Area | Decision |
|------|----------|
| Stats | `ProjectOpsStatsService`: open unresolved issues, events last 7d (`Event`), last ingest `MAX(received_at)` |
| Suspend | Toggle `Project.ingestEnabled` on admin project show; Envelope returns `403 ingest disabled` |
| Audit | `UserActionType::ProjectSuspended` / `ProjectResumed` (+ view-as start/end) |
| View-as | Session `_beacon_view_as_member`; `ProjectAccessService` forces Member for ROLE_ADMIN; banner in `base.html.twig` |
| Routes | `admin_projects_ingest_toggle`, `admin_view_as_member_enable` / `_disable` |

## Constitution Check

- Spec-first: yes (`019`)
- Prefer existing `UserActionRecorder` over inventing a second audit store
- English docs/PHPDoc/UI; PHPUnit coverage

## Legal note

Operators offering hosted Beacon should disclose support impersonation (view-as) in privacy/terms when applicable. Cookie consent remains via `nowo-tech/cookie-consent-bundle` for non-essential cookies.

## Out of scope

- Fleet-wide aggregate dashboard charts
- Audit log filtering UI (actions still appear on user activity timeline)
- Impersonating a specific named member (v1 = member role for admin)
