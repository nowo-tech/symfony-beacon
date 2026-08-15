# Golden Envelope fixtures (Phase 3.6)

Canonical NDJSON bodies for the Bundle ↔ Beacon ingest contract.

- Canonical copy lives in BeaconBundle: `tests/Contract/fixtures/envelope/`.
- This directory must stay byte-identical to the Bundle fixtures (`.ndjson` only).
- Deterministic `event_id` / timestamps (not live client output).
- DSN host port is the placeholder `__HTTPS_PORT__` (expanded at test runtime from
  `HTTPS_PORT`, default `9447` — same as `.env.dist`). Do not hard-code `9444`/`9447`
  in these files; keep both checkouts identical.

Run `make check-envelope-goldens` from either repo when the sibling checkout is available.
