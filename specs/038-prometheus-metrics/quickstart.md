# Quickstart: Prometheus metrics

1. Set a long random `BEACON_METRICS_TOKEN` in `.env` (prod).
2. Scrape: `curl -H "Authorization: Bearer $BEACON_METRICS_TOKEN" https://beacon.example/metrics`
3. Or open `/metrics` while logged in as `ROLE_ADMIN`.
4. Do **not** expose `/metrics` on the public internet without token or network ACL (see PRODUCTION.md).
