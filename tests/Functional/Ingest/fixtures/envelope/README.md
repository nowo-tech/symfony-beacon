# Golden Envelope fixtures (Phase 3.6)

Canonical NDJSON bodies for the Bundle ↔ Beacon ingest contract.

- Canonical copy lives in BeaconBundle: `tests/Contract/fixtures/envelope/`.
- This directory must stay byte-identical to the Bundle fixtures (`.ndjson` only).
- Deterministic `event_id` / timestamps (not live client output).

Run `make check-envelope-goldens` from either repo when the sibling checkout is available.
