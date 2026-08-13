# Tasks: Audit residual hardening (`095`)

**Status**: Implemented (v1.11.0)

## Phase A — Security

- [x] A1 Remove auto `phoneVerifiedAt`; preserve on unchanged phone; profile UI status
- [x] A2 Narrow maintenance exclusions to Envelope + OTLP patterns
- [x] A3 Mercure hub URL guard + form validation + unit tests

## Phase B — Performance / Doctrine

- [x] B1 Batch eligible users in `MemberIssueRealtimeNotifier` / preference evaluator
- [x] B2 Batch project preference UI load/save
- [x] B3 Admin pagination + `countByRoleIds`
- [x] B4 `EXTRA_LAZY` + `SqlLikeEscaper` (+ SQLite `ESCAPE '\'`)

## Phase C — Architecture / Symfony

- [x] C1 `ProjectPermissionVoter` + migrate Project HTTP controllers
- [x] C2 `IssueShowPageBuilder` + dashboard filter resolvers/DTOs
- [x] C3 `ProjectMembershipAdminPort` + new-project Project fragment
- [x] C4 Metrics → `App\Ops\Metrics`; boundary script guards
- [x] C5 `InstanceSettings` traits; `AbstractJsonImportType`; danger/import constraints
- [x] C6 Maintenance panel FormViews / `_fields`

## Phase D — Docs / release

- [x] D1 Spec `095`, CHANGELOG / UPGRADING / ROADMAP / SECURITY / ARCHITECTURE / ROLES
- [x] D2 Cut v1.11.0
