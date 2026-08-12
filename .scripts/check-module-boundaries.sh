#!/usr/bin/env sh
# Fail on module-boundary regressions (083 / 085 architecture guardrails).
# Uses ripgrep when available; falls back to POSIX/GNU grep (CI / minimal PATH).
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

bad=0

if command -v rg >/dev/null 2>&1; then
  HAS_RG=1
else
  HAS_RG=0
fi

# Print matching *.php paths under dir (one per line). Args: ERE pattern, directory
php_files_matching() {
  pattern=$1
  dir=$2
  if [ "$HAS_RG" -eq 1 ]; then
    rg -l --glob '*.php' "$pattern" "$dir" 2>/dev/null || true
  else
    find "$dir" -name '*.php' -type f -print 2>/dev/null \
      | while IFS= read -r f; do
          grep -E -q -- "$pattern" "$f" 2>/dev/null && printf '%s\n' "$f"
        done || true
  fi
}

file_contains() {
  pattern=$1
  file=$2
  if [ "$HAS_RG" -eq 1 ]; then
    rg -q -- "$pattern" "$file"
  else
    grep -E -q -- "$pattern" "$file"
  fi
}

# --- AdminProject ownership (Identity must not own project admin HTTP) ---
if [ -e src/Identity/Controller/AdminProjectController.php ]; then
  echo "error: src/Identity/Controller/AdminProjectController.php must not exist (belongs in App\\Project\\Controller)." >&2
  bad=1
fi

admin_hits=$(php_files_matching 'class AdminProjectController([^[:alnum:]_]|$)' src/Identity)
if [ -n "$admin_hits" ]; then
  echo "error: AdminProjectController must not live under App\\Identity." >&2
  printf '%s\n' "$admin_hits" >&2
  bad=1
fi

if [ ! -f src/Project/Controller/AdminProjectController.php ]; then
  echo "error: missing src/Project/Controller/AdminProjectController.php" >&2
  bad=1
fi

# --- OTLP controllers must live under Ingest/Otlp ---
otlp_hits=$(php_files_matching 'Otlp.*Controller|Controller.*Otlp' src)
otlp_outside=$(printf '%s\n' "$otlp_hits" | grep -v '^src/Ingest/Otlp/' | grep -v '^$' || true)
if [ -n "$otlp_outside" ]; then
  echo "error: OTLP controllers must live under src/Ingest/Otlp/:" >&2
  echo "$otlp_outside" >&2
  bad=1
fi

if [ ! -d src/Ingest/Otlp ]; then
  echo "error: missing src/Ingest/Otlp (OTLP adapters belong here)." >&2
  bad=1
fi

# --- Shared must not own domain write-path services (Issues/Events/Perf persist) ---
# Allowlist: no Shared Service/Retention that imports IssueRepository, EventRepository,
# IssueMergeService, or Performance entities for write ownership. Ops module owns retention.
if [ -d src/Shared ]; then
  write_hits=$(php_files_matching \
    'use App\\Issues\\(Repository\\(Issue|Event)Repository|Service\\IssueMergeService)|use App\\Performance\\Entity\\' \
    src/Shared)
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
  if ! file_contains 'IssueEnvelopeWriter' src/Ingest/MessageHandler/ProcessEnvelopeHandler.php; then
    echo "error: ProcessEnvelopeHandler must use IssueEnvelopeWriter (domain writer extraction)." >&2
    bad=1
  fi
  if ! file_contains 'PerformanceEnvelopeWriter' src/Ingest/MessageHandler/ProcessEnvelopeHandler.php; then
    echo "error: ProcessEnvelopeHandler must use PerformanceEnvelopeWriter." >&2
    bad=1
  fi
fi

if [ "$bad" -ne 0 ]; then
  exit 1
fi

echo "OK: module boundaries (AdminProject, OTLP, Shared write-path, Ops, Envelope writers)."
