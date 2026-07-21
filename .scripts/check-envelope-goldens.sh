#!/usr/bin/env sh
# Diff golden Envelope fixtures between BeaconBundle and symfony-beacon when both checkouts exist.
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
BEACON_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
BUNDLE_FIXTURES="${BUNDLE_FIXTURES:-$BEACON_ROOT/../../bundles/BeaconBundle/tests/Contract/fixtures/envelope}"
BEACON_FIXTURES="$BEACON_ROOT/tests/Ingest/fixtures/envelope"

if [ ! -d "$BUNDLE_FIXTURES" ]; then
  echo "SKIP: sibling BeaconBundle fixtures not found at $BUNDLE_FIXTURES"
  exit 0
fi

failed=0
for name in event_happy.ndjson event_exception.ndjson transaction_with_spans.ndjson; do
  if ! cmp -s "$BUNDLE_FIXTURES/$name" "$BEACON_FIXTURES/$name"; then
    echo "MISMATCH: $name differs between Bundle and Beacon golden fixtures"
    diff -u "$BUNDLE_FIXTURES/$name" "$BEACON_FIXTURES/$name" || true
    failed=1
  fi
done

if [ "$failed" -ne 0 ]; then
  echo "Golden Envelope fixtures are out of sync. Update both copies (Phase 3.6)."
  exit 1
fi

echo "OK: golden Envelope fixtures match ($BEACON_FIXTURES ↔ $BUNDLE_FIXTURES)"
