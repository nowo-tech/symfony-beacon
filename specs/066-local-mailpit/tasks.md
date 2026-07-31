# Tasks: Local Mailpit

**Spec**: [spec.md](./spec.md) · **Quickstart**: [quickstart.md](./quickstart.md)

## Phase 1: Compose + Make

- [x] T001 Put Mailpit behind Compose profile `mail` in `compose.override.yaml` with fixed published ports
- [x] T002 Add `make mailpit` / `mailpit-logs`; include profile in `make down`; hint from `make up` / `print-urls`
- [x] T003 Document `MAILPIT_*` + Mailpit DSN comments in `.env.dist`

## Phase 2: Docs + spec

- [x] T004 Add `docs/MAILPIT.md`; link from INSTALL, NOTIFICATIONS, PRODUCTION, README, CHANGELOG
- [x] T005 Speckit artifacts `066-local-mailpit` + ROADMAP pointer
- [x] T006 Confirm `compose.prod.yaml` has no Mailpit service
