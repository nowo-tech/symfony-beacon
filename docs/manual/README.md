# Symfony Beacon — product UI manual

Operator guide for the self-hosted Beacon web UI. Screenshots are English, day-theme captures at a fixed viewport (development overlays removed).

## Conventions

| Item | Value |
|------|--------|
| Viewport | **1440×900** fixed. Tall screens use same-size companions (`*-2.png`), never a stretched full-page image |
| Theme | **Day** for the inventory. Day/night difference is shown once on public + private routes — see [Theme and language](07-appearance.md) |
| Language | **English**. How to switch languages is shown once on public + private routes — see [Theme and language](07-appearance.md) |

## Contents

| Chapter | Topic |
|---------|--------|
| [00 — First-time setup](00-setup.md) | Cold install wizard |
| [01 — Getting started](01-getting-started.md) | Sign-in and recovery |
| [02 — Dashboard](02-dashboard.md) | Projects home and panels |
| [03 — Account](03-account.md) | Profile, security, display, privacy |
| [04 — Projects and issues](04-projects-and-issues.md) | Issues, analytics, settings |
| [05 — Administration](05-admin-and-ops.md) | Users, ops, kits, backup |
| [06 — Legal and privacy](06-legal-and-privacy.md) | Legal pages and cookies |
| [07 — Theme and language](07-appearance.md) | How to switch day/night and locale |

## Regenerating screenshots

```bash
make up-e2e && make ready-e2e
make docs-manual-screenshots

make wipe-e2e-cold && make up-e2e-cold
make docs-manual-screenshots-setup
```

## Local demo credentials

- Email: `admin@symfony-beacon.local`
- Password: `admin123`

Do not reuse on a public instance.
