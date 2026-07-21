## Summary

<!-- What does this PR change and why? Link related issues: Fixes #NN -->

-

## Spec / docs

- [ ] Spec under `specs/` updated (or N/A for docs-only / chore)
- [ ] `docs/CHANGELOG.md` `[Unreleased]` updated when user-facing
- [ ] `docs/UPGRADING.md` updated when operators must migrate or reconfigure

## Test plan

- [ ] `make test` (or targeted PHPUnit) passes
- [ ] `make qa` when touching PHP style / Twig / Rector / PHPStan
- [ ] Manual check (describe briefly):

```text

```

## Checklist

- [ ] English docs / PHPDoc / UI default copy (`lang="en"`)
- [ ] No Cursor `Co-authored-by` / `Made-with` trailers
- [ ] No secrets (`.env`, Halite keys, real DSNs) in the diff
- [ ] Prefer `nowo-tech/*` kits over reinventing auth/user/legal UX when applicable
- [ ] New Doctrine migrations use Migrations Kit MDK (`migrations/FieldDictionary/`)
