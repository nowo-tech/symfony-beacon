# Tasks: 058-self-beacon-client

- [x] T001 Require `nowo-tech/beacon-bundle` ^1.6.7; register bundle + `nowo_beacon.yaml` + `.env.dist`
- [x] T002 `DropSelfIngestBeforeSend` + wire as `before_send`
- [x] T003 Stable `DEMO_SECRET_KEY`; optional secret on `ProjectApiKey::generate`; seed writes server `BEACON_DSN`
- [x] T004 `make ready` + docs (INSTALL, DSN, README, ARCHITECTURE, constitution)
- [x] T005 Tests: before_send + seed DSN; CHANGELOG/UPGRADING
- [x] T006 Resolve `nowo-tech/beacon-bundle` from Packagist; remove GitHub VCS `repositories` entry
- [x] T007 `make dogfood` → `app:seed-demo --skip-demo-user` (+ help text / restart note)
- [x] T008 `ensure-halite-secrets` Make target; `dogfood` / `seed-platform` / `seed-sample` / `bootstrap` depend on it (`048`)
