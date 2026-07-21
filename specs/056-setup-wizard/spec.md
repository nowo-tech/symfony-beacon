# Feature Specification: Setup Wizard UI

**Feature Branch**: `056-setup-wizard`  
**Created**: 2026-07-21  
**Status**: Implemented  

**Input**: After CLI seed layers (`055`), add a light admin UI that runs platform / demo / sample seed steps and can be dismissed. Complements AuthKit first-user register; does not replace Docker/Compose install.

## User Scenarios

### US1 — Admin completes first-run setup (P1)

As an instance admin on a fresh install, I open **Setup** and can: install navigation (platform seed), optionally create the demo project, optionally load sample telemetry, then mark setup complete.

### US2 — Existing instances skip the wizard (P1)

As an operator upgrading from a prior release, I do not see the setup wizard by default (migration marks setup complete).

### US3 — Discoverability (P2)

As an admin who has not finished setup, I see a banner on the dashboard and a card on the admin hub linking to `/setup`.

## Requirements

- **FR-001**: `instance_settings.setup_completed_at` nullable; null means wizard pending.
- **FR-002**: Upgrade migration sets `setup_completed_at` on existing singleton row so prod upgrades are not interrupted.
- **FR-003**: `/setup` is `ROLE_ADMIN` only; POST actions CSRF-protected; invoke same seeders as CLI (`055`).
- **FR-004**: Actions: platform seed, demo identity, sample `dev` (and optional load), complete/dismiss.
- **FR-005**: English UI strings; docs pointer in INSTALL.md.

## Out of Scope

- Replacing AuthKit register / Docker bootstrap.
- Running `huge` sample from the UI (CLI only with `--force`).
