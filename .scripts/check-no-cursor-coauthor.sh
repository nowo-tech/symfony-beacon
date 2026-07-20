#!/usr/bin/env sh
# Fail if git history contains Cursor agent Co-authored-by trailers.
set -eu

REF="${1:-HEAD}"

if [ ! -d .git ]; then
  echo "ERROR: .git not found — run from the repository root." >&2
  exit 1
fi

if ! git rev-parse --verify "${REF}" >/dev/null 2>&1; then
  echo "ERROR: git ref not found: ${REF}" >&2
  exit 1
fi

PATTERN='(^Co-authored-by:[[:space:]]*.*[Cc]ursor|^Co-authored-by:[[:space:]]*.*@cursor\.(com|so)|^Made-with:[[:space:]]*[Cc]ursor)'

MATCHES="$(
  git --no-replace-objects log "${REF}" --format=%B \
    | grep -E "${PATTERN}" \
    || true
)"

if [ -n "${MATCHES}" ]; then
  echo "ERROR: Cursor co-author trailers found in git history (ref: ${REF})" >&2
  echo "Offending commits:" >&2
  git --no-replace-objects log "${REF}" --format='%H %s' | while read -r hash subject; do
    if git --no-replace-objects log -1 --format=%B "${hash}" | grep -qE "${PATTERN}"; then
      echo "  ${hash} ${subject}" >&2
    fi
  done
  echo "Run: make strip-cursor-coauthor-from-history" >&2
  echo "${MATCHES}" | head -5 >&2
  exit 1
fi

echo "OK: no Cursor co-author trailers in git history (ref: ${REF})"
