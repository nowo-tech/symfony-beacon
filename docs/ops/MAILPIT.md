# Mailpit (local SMTP catcher)

Beacon sends transactional mail (magic login, password reset, notification email, Admin **Send sample**) through Symfony Mailer. For local development you can catch that mail with **[Mailpit](https://github.com/axllent/mailpit)** instead of a real SMTP provider.

Mailpit is **dev/test only**. It is **not** part of [`compose.prod.yaml`](../../compose.prod.yaml) and must **not** be used in production.

| Piece | Role |
|-------|------|
| Shared `mailpit` (preferred) | Container in `developer.local.server/server` on `server_network` |
| Compose service `mailer` (fallback) | App-local Mailpit via profile `mail` when `server/` is absent |
| `make mailpit` | Starts shared Mailpit when available; else profile `mail` |
| Administration → Mailer | Save encrypted DSN so the app delivers into Mailpit |

Image: [`axllent/mailpit`](https://hub.docker.com/r/axllent/mailpit).

---

## Quick start (shared server — recommended)

1. Start the app stack (if not already running):

   ```bash
   make up
   make ready   # optional: migrate + demo admin
   ```

2. Start shared Mailpit:

   ```bash
   make mailpit
   # equivalent: make -C ../../../server up-mailpit
   ```

   Open the UI URL printed by Make (default **http://localhost:18026**).

3. As `ROLE_ADMIN`, open **Administration → Mailer** (`/admin/mailer`) and save:

   | Field | Value |
   |-------|--------|
   | Mailer DSN | `smtp://mailpit:1025` |
   | From (optional) | e.g. `beacon@localhost` |

   Hostname `mailpit` is the shared Compose `container_name` (reachable from `php` / `messenger` on `server_network`). Do **not** use `localhost:1025` from inside PHP — that points at the container itself, not Mailpit.

4. On the same page, use **Send sample email** (or trigger magic login / a project email notification test). Messages appear in the Mailpit UI.

Env `MAILER_DSN` remains a bootstrap fallback only (`null://null` in `.env.dist`). Magic login and forgot-password stay gated on a **deliverable encrypted** database DSN — see [`034-encrypted-mailer-dsn`](../../specs/034-encrypted-mailer-dsn/spec.md) and [NOTIFICATIONS.md](../product/NOTIFICATIONS.md#email).

---

## App-local fallback

If `developer.local.server/server` is not present, `make mailpit` starts the Compose profile `mail` service `mailer` from [`compose.override.yaml`](../../compose.override.yaml). Use DSN `smtp://mailer:1025` instead.

Do **not** run both the shared `mailpit` and the app-local `mailer` at once — `make mailpit` stops the local service when it starts the shared one.

---

## Make / Compose

| Command | Effect |
|---------|--------|
| `make mailpit` | Prefer `server` Mailpit; else `docker compose --profile mail up -d mailer` |
| `make -C ../../../server up-mailpit` | Start only shared Mailpit |
| `make mailpit-logs` | Follow shared `mailpit` or local `mailer` logs |
| `make down` | Stops profile `mail` (and `hmr`) along with the default stack; does **not** stop shared `mailpit` |
| `make print-urls` | Includes Mailpit UI when a catcher is running |

---

## Environment variables

| Variable | Default | Meaning |
|----------|---------|---------|
| `MAILPIT_UI_PORT` | `18026` | Host port → Mailpit web UI (container `8025`); set in Beacon `.env` and/or `server/.env` |
| `MAILPIT_SMTP_PORT` | `1027` | Host port → Mailpit SMTP (container `1025`; for host-side tools only) |
| `MAILER_DSN` | `null://null` | Symfony fallback when no DB DSN is stored — prefer Admin → Mailer |

---

## Production

| Stack | Mailpit |
|-------|---------|
| Shared `server/` or default local (`compose.override.yaml` profile `mail`) | Dev/test catcher only |
| Production (`compose.prod.yaml` / `frankenphp_prod`) | **Absent** — configure a real SMTP/API DSN under Administration → Mailer |

Do not set the encrypted instance Mailer DSN to a Mailpit hostname in production. Use your provider’s SMTP or a supported Symfony Mailer scheme (see Admin form help / `MailerDsnValidator` allowlist).

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| Connection refused from PHP | Is Mailpit up? `make mailpit` / `docker inspect mailpit` |
| Messages never appear | DSN must be `smtp://mailpit:1025` (shared) or `smtp://mailer:1025` (local) in **Admin → Mailer** (DB), not only env `null://null` |
| Wrong host port | Override `MAILPIT_UI_PORT` / `MAILPIT_SMTP_PORT` in `server/.env` (or Beacon `.env` for local); recreate with `make mailpit` |
| Magic login links missing | Encrypted DB DSN must be deliverable (not `null://…`); Mailpit DSN counts as deliverable |

Messenger workers use the same Docker network, so async notification emails also reach Mailpit when the DB DSN points at the catcher hostname.
