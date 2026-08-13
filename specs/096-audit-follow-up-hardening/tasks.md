# Tasks: Audit follow-up hardening (`096`)

**Status**: Implemented (v1.12.0)

## Phase A — Security

- [x] A1 Read API IP rate limit (`BEACON_READ_API_RATE_LIMIT` + subscriber/limiter)
- [x] A2 API key `secret_hash` + dual-read legacy upgrade + migration
- [x] A3 `InstancePermissionVoter` abstain on `Project` subject + functional bypass test
- [x] A4 Disable AuthKit `qr_login` until phone OTP
- [x] A5 Slack user ID: password gate + uniqueness on profile save
- [x] A6 Interaction action token TTL 24h

## Phase B — Architecture / Forms / Filters

- [x] B1 `ProjectMembershipPolicy` + `ProjectGroupAccessManager`; FormType role POSTs
- [x] B2 Issue / Analytics / Performance / dashboard filter DTOs + resolvers
- [x] B3 Read API `ProjectIssuesListQuery` + `MapQueryString`
- [x] B4 `AccessibleProjectsProvider` request cache
- [x] B5 `OtlpResourceIterator`; `IssueStatusTransition`; `IssueJsonView`

## Phase C — Doctrine / deps

- [x] C1 Indexes migration for new-in-release + event user_identifier
- [x] C2 Pin `nowo-tech/beacon-bundle` 1.7.0

## Phase D — Docs / release

- [x] D1 Spec `096`; amend `042` / `087` / `095` notes; CHANGELOG / UPGRADING / ROADMAP / PRODUCTION / DSN / API / ARCHITECTURE / SECURITY / ROLES
- [x] D2 Cut v1.12.0
