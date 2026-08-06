# Quickstart: Local Mailpit

```bash
cp .env.dist .env   # if needed
make up
make mailpit
```

1. Open the Mailpit UI (default http://localhost:18026).
2. Administration → Mailer → save DSN `smtp://mailer:1025` (+ optional From).
3. **Send sample email** (or magic login / project email **Send test**).
4. Confirm the message in Mailpit.

Full manual: [docs/ops/MAILPIT.md](../../docs/ops/MAILPIT.md).

Production: do not start Mailpit; use a real Mailer DSN ([docs/PRODUCTION.md](../../docs/PRODUCTION.md)).
