# Tasks: 058-self-beacon-client

- [x] T001 Require `nowo-tech/beacon-bundle` ^1.6.7; register bundle + `nowo_beacon.yaml` + `.env.dist`
- [x] T002 `DropSelfIngestBeforeSend` + wire as `before_send`
- [x] T003 Stable `DEMO_SECRET_KEY`; optional secret on `ProjectApiKey::generate`; seed writes server `BEACON_DSN`
- [x] T004 `make ready` + docs (INSTALL, DSN, README, ARCHITECTURE, constitution)
- [x] T005 Tests: before_send + seed DSN; CHANGELOG/UPGRADING
- [x] T006 Resolve `nowo-tech/beacon-bundle` from Packagist; remove GitHub VCS `repositories` entry
- [x] T007 `make dogfood` → `app:seed-demo --skip-demo-user` (+ help text / restart note)
- [x] T008 `ensure-halite-secrets` Make target; `dogfood` / `seed-platform` / `seed-sample` / `bootstrap` depend on it (`048`)
- [x] T009 Pin `nowo-tech/beacon-bundle` **1.7.3**; `make beacon-test` → `app:beacon:test` (wraps `nowo:beacon:test` + dogfood diagnostics)
- [x] T010 Seed/dogfood write loopback `BEACON_DSN` preferring `.env.local`; `make reclaim-demo-client-env` after writing `.demo-client.env` (CI/host readable)
- [x] T011 Docs: DSN.md / CHANGELOG / UPGRADING; unit tests for dogfood diagnostics
