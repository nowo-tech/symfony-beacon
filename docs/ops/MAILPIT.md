# Mailpit (local SMTP catcher)

Beacon sends transactional mail (magic login, password reset, notification email, Admin **Send sample**) through Symfony Mailer. For local development you can catch that mail with **[Mailpit](https://github.com/axllent/mailpit)** instead of a real SMTP provider.

Mailpit is **dev/test only**. It is **not** part of [`compose.prod.yaml`](../../compose.prod.yaml) and must **not** be used in production.

| Piece | Role |
|-------|------|
| Compose service `mailer` | Mailpit container (SMTP `:1025`, UI `:8025`) |
| Profile `mail` | Opt-in — same idea as Vite HMR (`hmr`); not started by `make up` |
| `make mailpit` | Starts the service and prints the UI URL |
| Administration → Mailer | Save encrypted DSN `smtp://mailer:1025` so the app delivers into Mailpit |

Image: [`axllent/mailpit`](https://hub.docker.com/r/axllent/mailpit). Defined in [`compose.override.yaml`](../../compose.override.yaml) (merged automatically for the default local stack).

---

## Quick start

1. Start the app stack (if not already running):

   ```bash
   make up
   make ready   # optional: migrate + demo admin
   ```

2. Start Mailpit:

   ```bash
   make mailpit
   ```

   Open the UI URL printed by Make (default **http://localhost:18026**).

3. As `ROLE_ADMIN`, open **Administration → Mailer** (`/settings/mailer`) and save:

   | Field | Value |
   |-------|--------|
   | Mailer DSN | `smtp://mailer:1025` |
   | From (optional) | e.g. `beacon@localhost` |

   Hostname `mailer` is the Compose service name (reachable from the `php` / `messenger` containers). Do **not** use `localhost:1025` from inside PHP — that points at the container itself, not Mailpit.

4. On the same page, use **Send sample email** (or trigger magic login / a project email notification test). Messages appear in the Mailpit UI.

Env `MAILER_DSN` remains a bootstrap fallback only (`null://null` in `.env.dist`). Magic login and forgot-password stay gated on a **deliverable encrypted** database DSN — see [`034-encrypted-mailer-dsn`](../../specs/034-encrypted-mailer-dsn/spec.md) and [NOTIFICATIONS.md](../product/NOTIFICATIONS.md#email).

---

## Make / Compose

| Command | Effect |
|---------|--------|
| `make mailpit` | `docker compose --profile mail up -d mailer` + print UI / SMTP hints |
| `make mailpit-logs` | Follow Mailpit container logs |
| `make down` | Stops profile `mail` (and `hmr`) along with the default stack |
| `make print-urls` | Includes Mailpit UI when the service is running |

Equivalent without Make:

```bash
docker compose --profile mail up -d mailer
```

---

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `MAILPIT_UI_PORT` | `18026` | Host port → Mailpit web UI (container `8025`) |
| `MAILPIT_SMTP_PORT` | `1026` | Host port → Mailpit SMTP (container `1025`; for host-side tools only) |
| `MAILER_DSN` | `null://null` | Symfony fallback when no DB DSN is stored — prefer Admin → Mailer |

---

## Production

| Stack | Mailpit |
|-------|---------|
| Default local (`compose.yaml` + `compose.override.yaml`) | Available via profile `mail` |
| Production (`compose.prod.yaml` / `frankenphp_prod`) | **Absent** — configure a real SMTP/API DSN under Administration → Mailer |

Do not set the encrypted instance Mailer DSN to a Mailpit hostname in production. Use your provider’s SMTP or a supported Symfony Mailer scheme (see Admin form help / `MailerDsnValidator` allowlist).

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Connection refused from PHP | Is Mailpit up? `make mailpit` / `docker compose --profile mail ps` |
| Messages never appear | DSN must be `smtp://mailer:1025` in **Admin → Mailer** (DB), not only env `null://null` |
| Wrong host port | Override `MAILPIT_UI_PORT` / `MAILPIT_SMTP_PORT` in `.env`; recreate with `make mailpit` |
| Magic login links missing | Encrypted DB DSN must be deliverable (not `null://…`); Mailpit DSN counts as deliverable |

Messenger workers use the same Compose network, so async notification emails also reach Mailpit when the DB DSN points at `mailer:1025`.
