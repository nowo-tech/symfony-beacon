# Contracts: Identity / error docs (114)

Documentation and harness feature — no new public HTTP API beyond existing `063` `/_error/{code}` (dev) and `/_maintenance_preview`.

## Doc contracts

| Consumer | MUST |
|----------|------|
| Docs index / wiki / README | Link `docs/identity/README.md` |
| Product UI manual | Include `08-errors.md` and list every committed error PNG |
| CHANGELOG Unreleased | Mention identity book + transparent art (+ chapter 08 when shipped) |

## Runtime art contract

| Path pattern | Format |
|--------------|--------|
| `public/brand/mascot.png` | PNG, color type 6 (RGBA), transparent canvas |
| `public/illustrations/error-*.png` | Same |

## Make / E2E contracts

| Target / suite | Behavior |
|----------------|----------|
| `make docs-manual-screenshots` (or project alias) | Refreshes `docs/manual/images/` including `error-*`; applies dashboard filter + MM schedule clear |
| `make docs-manual-screenshots-setup` | Cold stack only; forces English setup chrome before PNGs |
| `make test-e2e-smoke` | Includes html-twig standardization; no Mailpit requirement |
| `make test-e2e-mailpit` | Profile `mail` + `PLAYWRIGHT_MAILPIT=1` + serial workers |

## Capture hygiene contract

| Shot family | MUST |
|-------------|------|
| `dashboard` / `prefs-private-theme*` | Demo project filter (`Symfony Beacon`); no XSS payload titles in main; legal footer labels match `html[lang]` (EN inventory) |
| `admin-groups` | Human demo row (`Beacon operators` via `?q=Beacon`); no `E2E` row names in main |
| `error-maintenance` | No absurd multi-year countdown (clear leftover schedule first) |
| `setup-*` (cold) | `html[lang^=en]` and English copy (`FIRST RUN` / `Continue`) |

## CSP contract

HTML responses that emit a CSP nonce MUST stamp the same nonce onto bare inline `<style>` elements (and existing `<script>` behavior), so `style-src-elem` / `script-src` nonce policies do not blank maintenance or error chrome.
