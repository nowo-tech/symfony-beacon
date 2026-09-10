# Symfony Beacon — product UI manual

Operator guide for the self-hosted Beacon web UI.

**Source of truth:** [`docs/manual/`](https://github.com/nowo-tech/symfony-beacon/tree/main/docs/manual) in this repository (versioned with `main`, regenerated via Playwright). This wiki **Home** is an index only — chapter content and screenshots live in the tree.

## Chapters

| Chapter | Topic |
|---------|--------|
| [00 — First-time setup](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/00-setup.md) | Cold install wizard |
| [01 — Getting started](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/01-getting-started.md) | Sign-in and recovery |
| [02 — Dashboard](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/02-dashboard.md) | Projects home and panels |
| [03 — Account](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/03-account.md) | Profile, security, display, privacy |
| [04 — Projects and issues](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/04-projects-and-issues.md) | Issues, analytics, settings |
| [05 — Administration](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/05-admin-and-ops.md) | Users, ops, kits, backup |
| [06 — Legal and privacy](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/06-legal-and-privacy.md) | Legal pages and cookies |
| [07 — Theme and language](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/07-appearance.md) | Day/night and locale |

Full index and conventions: [docs/manual/README.md](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/manual/README.md).

## Conventions (short)

| Item | Value |
|------|--------|
| Viewport | **1440×900** fixed (tall screens use `*-2.png` companions) |
| Theme / language | **Day** + **English** for the inventory; preference demos once public + private — see chapter 07 |

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

## Sync this wiki Home

From a clone of `symfony-beacon` (after the wiki has been created once on GitHub):

```bash
make wiki-push-home
```

Canonical file: [`docs/wiki/Home.md`](https://github.com/nowo-tech/symfony-beacon/blob/main/docs/wiki/Home.md).
