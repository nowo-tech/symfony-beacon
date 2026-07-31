## Summary

<!-- Feature / product change. Link Spec: specs/NNN-… and Fixes #NN -->

-

## Spec / docs

- [ ] Spec under `specs/` updated (required for non-trivial product work)
- [ ] `docs/CHANGELOG.md` `[Unreleased]` updated
- [ ] `docs/UPGRADING.md` / ROADMAP updated when operators must migrate

## Kit preference

- [ ] Prefer `nowo-tech/*` kits over reinventing auth/user/legal/forms UX (or N/A)

## Test plan

- [ ] `make test` passes
- [ ] `make qa` when touching PHP / Twig / Rector / PHPStan
- [ ] Manual check:

```text

```

## Checklist

- [ ] English docs / PHPDoc / UI default copy (`lang="en"`)
- [ ] No Cursor `Co-authored-by` / `Made-with` trailers
- [ ] No secrets in the diff
- [ ] New Doctrine migrations use Migrations Kit MDK (`migrations/FieldDictionary/`) when applicable
- [ ] Legal / cookie consent considered if public UI or non-essential cookies changed
