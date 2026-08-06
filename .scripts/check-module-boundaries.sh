#!/usr/bin/env sh
# Fail on module-boundary regressions (083 / 085 architecture guardrails).
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

bad=0

# --- AdminProject ownership (Identity must not own project admin HTTP) ---
if [ -e src/Identity/Controller/AdminProjectController.php ]; then
  echo "error: src/Identity/Controller/AdminProjectController.php must not exist (belongs in App\\Project\\Controller)." >&2
  bad=1
fi

if rg -l --glob '*.php' 'class AdminProjectController\b' src/Identity >/dev/null 2>&1; then
  echo "error: AdminProjectController must not live under App\\Identity." >&2
  rg -n --glob '*.php' 'class AdminProjectController\b' src/Identity >&2 || true
  bad=1
fi

if [ ! -f src/Project/Controller/AdminProjectController.php ]; then
  echo "error: missing src/Project/Controller/AdminProjectController.php" >&2
  bad=1
fi

# --- OTLP controllers must live under Ingest/Otlp ---
if rg -l --glob '*.php' 'class Otlp' src --glob '!src/Ingest/Otlp/**' >/dev/null 2>&1; then
  # Narrower: Controllers named *Otlp* outside Ingest/Otlp
  hits=$(rg -l --glob '*.php' 'Otlp.*Controller|Controller.*Otlp' src 2>/dev/null | grep -v '^src/Ingest/Otlp/' || true)
  if [ -n "$hits" ]; then
    echo "error: OTLP controllers must live under src/Ingest/Otlp/:" >&2
    echo "$hits" >&2
    bad=1
  fi
fi

if [ ! -d src/Ingest/Otlp ]; then
  echo "error: missing src/Ingest/Otlp (OTLP adapters belong here)." >&2
  bad=1
fi

# --- Shared must not own domain write-path services (Issues/Events/Perf persist) ---
# Allowlist: no Shared Service/Retention that imports IssueRepository, EventRepository,
# IssueMergeService, or Performance entities for write ownership. Ops module owns retention.
if [ -d src/Shared ]; then
  write_hits=$(rg -l --glob '*.php' \
    'use App\\Issues\\(Repository\\(Issue|Event)Repository|Service\\IssueMergeService)|use App\\Performance\\Entity\\' \
    src/Shared 2>/dev/null || true)
  if [ -n "$write_hits" ]; then
    echo "error: Shared must not import Issues/Performance write-path types (use App\\Ops for retention/ops):" >&2
    echo "$write_hits" >&2
    bad=1
  fi
fi

# --- Ops module expected after 085 ---
if [ ! -f src/Ops/Service/OpsOverviewService.php ]; then
  echo "error: missing src/Ops/Service/OpsOverviewService.php" >&2
  bad=1
fi
if [ ! -f src/Ops/Retention/RetentionPurger.php ]; then
  echo "error: missing src/Ops/Retention/RetentionPurger.php" >&2
  bad=1
fi

# --- Envelope writers: ProcessEnvelopeHandler should not re-inline Issue/Event persist ---
if [ -f src/Ingest/MessageHandler/ProcessEnvelopeHandler.php ]; then
  if ! rg -q 'IssueEnvelopeWriter' src/Ingest/MessageHandler/ProcessEnvelopeHandler.php; then
    echo "error: ProcessEnvelopeHandler must use IssueEnvelopeWriter (domain writer extraction)." >&2
    bad=1
  fi
  if ! rg -q 'PerformanceEnvelopeWriter' src/Ingest/MessageHandler/ProcessEnvelopeHandler.php; then
    echo "error: ProcessEnvelopeHandler must use PerformanceEnvelopeWriter." >&2
    bad=1
  fi
fi

if [ "$bad" -ne 0 ]; then
  exit 1
fi

echo "OK: module boundaries (AdminProject, OTLP, Shared write-path, Ops, Envelope writers)."
