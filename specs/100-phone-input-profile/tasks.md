# Tasks: Phone input kit on Account → Profile

**Feature**: `100-phone-input-profile`  
**Status**: Done (v1.16.0)

## Phase 1: Kit + form

- [x] T001 Add `nowo-tech/phone-input-bundle` **1.2.1** (`composer.json` / lock / `bundles.php`)
- [x] T002 Add `config/packages/nowo_phone_input.yaml` + Form theme in `twig.yaml`
- [x] T003 Wire `PhoneType` in `AccountProfileType` (CONCATENATED, ES default, preferred countries)
- [x] T004 Update `form.*.yaml` chrome keys if needed for phone field

## Phase 2: CSP host surface

- [x] T005 Override `phone_input_widget.html.twig` with Stimulus `phone-prefix-picker`
- [x] T006 Register controller in `assets/stimulus_bootstrap.ts`
- [x] T007 Add host `_phone_input.scss`; load flag-icons only on Profile (skip vendor Bootstrap CSS)

## Phase 3: QR env + tests + ops

- [x] T008 Default `qr_login.mode: disabled`; enable under `when@dev` / `when@test`
- [x] T009 Update `AccountPreferencesTest` for compound phone fields + verification hygiene
- [x] T010 `make restart` force-recreates php/messengers so `BEACON_DSN` reloads from `.env.local`

## Phase 4: Specs / docs / env

- [x] T011 Spec + ROADMAP 6.51 + CHANGELOG + `feature.json`
- [x] T012 Cross-amend `072` / `096` / `098` / `058`; kits rule; `docs/API.md` QR note
- [x] T013 Operator working file `.env.local` (REQ-ENV-003); CI `COMPOSE_ENV_FILES`; `ensure-env-local.sh`
