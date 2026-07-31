## Summary

<!-- CI, Docker, deps, refactor, git hygiene — no intentional product behaviour change. -->

-

## Type

- [ ] CI / GitHub Actions
- [ ] Docker / Compose / FrankenPHP image
- [ ] Dependencies (Composer / pnpm)
- [ ] Refactor / DX / tooling
- [ ] Other chore

## Test plan

- [ ] Relevant CI jobs green (or local equivalent: `make qa` / `make secrets-scan` / `make check-no-cursor-coauthor`)
- [ ] `make build-prod` when touching Dockerfile prod target

## Checklist

- [ ] `docs/CHANGELOG.md` `[Unreleased]` only if operators/devs must know
- [ ] No Cursor `Co-authored-by` / `Made-with` trailers
- [ ] No secrets in the diff
