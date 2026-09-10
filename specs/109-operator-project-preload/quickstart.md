# Quickstart: Operator project preload (`109`)

```bash
# Validate only
php bin/console app:preload-projects \
  --bundle=/path/to/projects.json \
  --api-keys=/path/to/api-keys.json \
  --dry-run

# Apply (single DB transaction)
php bin/console app:preload-projects \
  --bundle=/path/to/projects.json \
  --api-keys=/path/to/api-keys.json
```

Bundle format: `beacon-project-bundle` (same as Administration export — `089`).  
API keys file: devops-owned; do **not** commit secrets into this repository.

See `docs/INSTALL.md` and `docs/PRODUCTION.md`.
