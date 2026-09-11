# Quickstart: Product UI manual screenshots (`111`)

English day-theme inventory at **1440×900**. Theme/language demos once public + once private. Chapters are an operator **screen catalog**: purpose → what it contributes → screenshot (including `*-2` scroll companions).

## Product inventory (warm isolated)

```bash
make up-e2e && make ready-e2e
make docs-manual-screenshots
# PNGs → docs/manual/images/
```

After regenerating PNGs, update the matching section in `docs/manual/*.md` if the UI meaning changed (do not leave orphan images or bare image stacks).

## Setup wizard (cold only)

```bash
make wipe-e2e-cold && make up-e2e-cold
make docs-manual-screenshots-setup
```

| | Warm inventory | Setup shots |
|--|----------------|-------------|
| Stack | `104` `:9460` / `app_e2e` | `110` `:9461` / `app_e2e_cold` |
| Env | `PLAYWRIGHT_MANUAL=1` `PLAYWRIGHT_ISOLATED=1` | `PLAYWRIGHT_MANUAL=1` `PLAYWRIGHT_COLD=1` |
| Spec | `e2e/manual/capture-screens.spec.ts` | `e2e/manual/capture-setup.spec.ts` |
| Seed | `ready-e2e` | **none** (wizard) |

Manual index: [`docs/manual/README.md`](../../docs/manual/README.md).  
Wiki index only: [`docs/wiki/Home.md`](../../docs/wiki/Home.md) (`make wiki-push-home`).  
Do **not** run setup capture against dogfood or warm `app_e2e`.
