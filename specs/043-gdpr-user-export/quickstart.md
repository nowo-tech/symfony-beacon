# Quickstart: 043-gdpr-user-export

1. Migrate: `make console ARGS='doctrine:migrations:migrate -n'`
2. Sign in → **Account → Privacy** (`/account/privacy`).
3. **Download my data** → JSON with `schema: beacon-account-export/v1`.
4. Create a second admin + transfer/add project owners before anonymizing a sole owner / last admin.
5. Confirm anonymize → redirected to login; old password fails; email scrubbed.
6. Admin: Users table → Export / Anonymize for another account.
7. PHPUnit: `vendor/bin/phpunit --filter GdprAccountExportTest`
