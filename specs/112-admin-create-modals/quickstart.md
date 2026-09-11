# Quickstart: Admin / alerts create modals (`112`)

## Try it

1. **Group**: Administration → Groups → **New group**, or `/admin/groups?new=1`.
2. **Admin project**: Administration → Projects → **New project**, or `/admin/projects?new=1`.
3. **Threshold**: Project Settings → Alerts → add rule, or `…/settings/alerts?new_threshold=1`.

Legacy create URLs (`…/new`) redirect to the query opens above.

## Verify

```bash
make test ARGS='tests/Functional/Identity/AdminGroupsTest.php tests/Functional/Identity/AdminProjectsTest.php'
# Manual shots (isolated E2E):
# make docs-manual-screenshots   # or Playwright -g 'admin identity'
```

Edit flows stay full-page (`/admin/groups/{uuid}/edit`, project edit, threshold edit).
