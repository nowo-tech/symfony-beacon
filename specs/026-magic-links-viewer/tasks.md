# Tasks: Magic Links and Viewer Role

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

## Phase 0–1

- [x] T000 Spec + checklist
- [x] T001 Plan / research
- [x] T003 LEGAL: operational email only; no new tracking cookies

## Phase 2: Viewer (P1)

- [x] T010 `ProjectRole::Viewer` + `canTriageIssues()`; update ROLE_RANK maps
- [x] T011 `assignableRoles` / group roles include Viewer; i18n
- [x] T012 Issue POST + saved views require Member; Twig hide triage for viewers
- [x] T013 Functional test: viewer read OK, status POST 403

## Phase 3: Magic login (P1)

- [x] T020 `security.yaml` login_link + rate_limiter
- [x] T021 `MagicLoginController` request + check; email via Mailer
- [x] T022 AuthKit login page link; access_control public paths
- [x] T023 Functional test: create link, consume, expired/disabled

## Phase 4: Share links (P2)

- [x] T030 Entity + migration `project_share_link`
- [x] T031 Create/revoke UI (Settings admin); open `/share/{token}`
- [x] T032 Session grant in `ProjectAccessService`; audit actions
- [x] T033 Functional tests expiry / revoke / read-only

## Phase 5: Docs

- [x] T040 CHANGELOG / UPGRADING / ROADMAP / SECURITY / README / LEGAL
- [x] T041 `make cs` + PHPUnit for new tests
