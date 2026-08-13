# Feature Specification: Audit residual hardening (2026-08-13)

**Feature Branch**: `095-audit-residual-hardening`  
**Created**: 2026-08-13  
**Status**: Implemented (v1.11.0)  
**Roadmap**: Phase 6.45  

**Input**: Full-tree security + architecture + Symfony practices audit (2026-08-13 canvas). Close remaining High/Medium residuals after `087` / `093` / `083`–`086` / `090`–`092`: phone QR auto-verify, maintenance `/api/` blast radius, Mercure hub SSRF, N+1 member-alert prefs, admin list scale, `#[IsGranted]` convergence, Identity→Project cycle, Metrics ownership, InstanceSettings size, and SqlLike/EXTRA_LAZY hygiene.

## Summary

Operators get safer defaults (no unverified phone QR, tighter maintenance exclusions, Mercure URL guard). Maintainers get clearer module boundaries (`ProjectMembershipAdminPort`, Metrics under Ops, project HTTP `#[IsGranted]`), cheaper alert fan-out, paginated admin directories, and leaner Settings/Doctrine collections — without a hexagonal rewrite.

## Scope (delivered)

| ID | Area | Deliverable |
|----|------|-------------|
| R1 | Identity / AuthKit QR | Stop auto-setting `phoneVerifiedAt` on profile save; clear verification when phone changes; UI status copy |
| R2 | Maintenance (`092`) | Exclude only Envelope + OTLP ingest paths from 503; Read API stays under lockdown |
| R3 | Mercure | `MercureHubUrlGuard` on hub URL save/use (block private/metadata targets) |
| R4 | Notifications | Batch-prefetch member alert prefs / events / mentions in realtime notifier + preference manager |
| R5 | Admin Identity/Project | Server-side pagination; `countByRoleIds` without users×permissions cartesian product |
| R6 | Project HTTP | `ProjectPermissionVoter` + migrate project-scoped controllers to `#[IsGranted(ProjectPermission::…, 'project')]` |
| R7 | Issues / Dashboard | `IssueShowPageBuilder`; GET filter resolvers + DTOs for Assignments / Mentions / New-in-release / Alerts / Activity |
| R8 | Module boundaries | `ProjectMembershipAdminPort` for Identity admin unlink; new-project form owned by Project fragment; Metrics scrape → `App\Ops\Metrics`; CI boundary checks |
| R9 | Doctrine | `EXTRA_LAZY` on large collections; consistent `SqlLikeEscaper` + SQLite-safe `ESCAPE '\'` |
| R10 | Settings / Forms | Split `InstanceSettings` into Mailer/Mercure/Ops traits; `AbstractJsonImportType`; EqualTo/File constraints on danger/import forms; Maintenance panel FormViews |

## Non-goals

- Shipping SMS OTP (QR left `enabled` in this cut; **v1.12.0 / `096`** disables `qr_login` until OTP exists)
- Api Platform / Serializer groups / Read API rate limit / hash-at-rest ingest secrets (shipped in **`096`** / v1.12.0)
- Splitting `SiteAppearance` or full Identity admin controller rewrite
- Binding Slack user IDs via OAuth (password + uniqueness hygiene in **`096`**; OAuth still Later)

## User Scenarios & Testing

### User Story 1 - Phone is not trusted without verification (P1)

**Acceptance**: Saving a new phone clears `phoneVerifiedAt`; unchanged verified phone keeps timestamp; QR login cannot treat a freshly typed number as verified.

### User Story 2 - Maintenance does not open Read API (P1)

**Acceptance**: With maintenance enabled, Envelope/OTLP still reach ingest; `/api/projects/...` Read API returns 503; operators can still sign in and use `/admin/maintenance`.

### User Story 3 - Member alerts do not N+1 prefs (P2)

**Acceptance**: One alert fan-out loads prefs/events/mentions in batch queries indexed by user/project, not per-member `shouldNotify` round-trips.

### User Story 4 - Project HTTP uses declarative grants (P2)

**Acceptance**: Keys/members/shares/export/notifications/thresholds/danger routes use `#[IsGranted(ProjectPermission::…, 'project')]`; unauthorized actor gets 403.

## Dependencies

- Builds on `083` / `085` / `086` (boundaries), `090` (forms CSRF), `091` (member alerts), `092` (maintenance), `093` (security residual).

## Implementation notes

- No Doctrine migrations in this release.
- Host Maintenance Twig override uses kit FormViews + `_fields`.
- Dashboard embeds `ProjectController::newFormFragment` so Identity does not own `ProjectCreationFormFactory`.
