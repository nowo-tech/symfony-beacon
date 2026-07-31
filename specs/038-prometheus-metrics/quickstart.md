# Quickstart: Prometheus metrics

1. Set a long random `BEACON_METRICS_TOKEN` in `.env` (required in prod — empty token returns 503).
2. Scrape: `curl -H "Authorization: Bearer $BEACON_METRICS_TOKEN" https://beacon.example/metrics`
3. Or open `/metrics` while logged in as `ROLE_ADMIN` (after a token is configured in prod).
4. Do **not** expose `/metrics` on the public internet without token or network ACL (see PRODUCTION.md). Optional Caddy `remote_ip` snippet is commented in the FrankenPHP Caddyfile.
