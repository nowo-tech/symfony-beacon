#!/usr/bin/env sh
# Fail when Clover statement coverage is below COVERAGE_MIN (percent).
# If COVERAGE_MIN is unset or empty, print coverage and exit 0 (informational).
set -eu

CLOVER="${1:-var/coverage/clover.xml}"

if [ ! -f "$CLOVER" ]; then
  echo "ERROR: Clover report not found at $CLOVER" >&2
  echo "Generate with: make test-coverage (or PHPUnit --coverage-clover)." >&2
  exit 1
fi

parse_clover() {
  if command -v php >/dev/null 2>&1; then
    php -r '
$clover = $argv[1];
$xml = @simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "ERROR: Unable to parse Clover XML: {$clover}\n");
    exit(2);
}
$metrics = $xml->project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "ERROR: Missing project/metrics in Clover XML\n");
    exit(2);
}
$covered = (int) $metrics["coveredstatements"];
$total = (int) $metrics["statements"];
$pct = $total > 0 ? (100.0 * $covered / $total) : 0.0;
printf("%.2f\t%d\t%d\n", $pct, $covered, $total);
' "$CLOVER"
    return
  fi
  if command -v python3 >/dev/null 2>&1; then
    python3 - "$CLOVER" <<'PY'
import sys
import xml.etree.ElementTree as ET

path = sys.argv[1]
try:
    root = ET.parse(path).getroot()
except ET.ParseError as exc:
    print(f"ERROR: Unable to parse Clover XML: {path}: {exc}", file=sys.stderr)
    sys.exit(2)
metrics = root.find("./project/metrics")
if metrics is None:
    print("ERROR: Missing project/metrics in Clover XML", file=sys.stderr)
    sys.exit(2)
covered = int(metrics.get("coveredstatements", "0"))
total = int(metrics.get("statements", "0"))
pct = (100.0 * covered / total) if total > 0 else 0.0
print(f"{pct:.2f}\t{covered}\t{total}")
PY
    return
  fi
  echo "ERROR: Need php or python3 to parse Clover XML" >&2
  exit 2
}

REPORT=$(parse_clover)
PCT=$(printf '%s' "$REPORT" | cut -f1)
COVERED=$(printf '%s' "$REPORT" | cut -f2)
TOTAL=$(printf '%s' "$REPORT" | cut -f3)

echo "Statement coverage: ${PCT}% (${COVERED}/${TOTAL}) from ${CLOVER}"

MIN="${COVERAGE_MIN:-}"
if [ -z "$MIN" ]; then
  echo "Soft threshold: disabled (COVERAGE_MIN unset). Informational only."
  exit 0
fi

# Numeric compare (awk is portable on CI + Compose)
awk -v pct="$PCT" -v min="$MIN" 'BEGIN {
  if (min < 0 || min > 100) {
    printf "ERROR: COVERAGE_MIN must be between 0 and 100 (got %s)\n", min > "/dev/stderr"
    exit 2
  }
  if (pct + 1e-9 < min) {
    printf "ERROR: Coverage %.2f%% is below soft threshold %.2f%%\n", pct, min > "/dev/stderr"
    exit 1
  }
  printf "Soft threshold: OK (%.2f%% >= %.2f%%)\n", pct, min
  exit 0
}'
