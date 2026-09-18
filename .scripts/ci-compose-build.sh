#!/usr/bin/env bash
# docker compose build uses Bake/Buildx. On GitHub-hosted runners the builder
# sometimes is not accepting connections yet:
#   waiting for BuildKit: DeadlineExceeded
# Retry only that race. A real image build failure is not retried.
set -euo pipefail

docker buildx inspect --bootstrap >/dev/null 2>&1 || true

max=4
attempt=1
while true; do
  log="$(mktemp)"
  set +e
  docker compose build "$@" 2>&1 | tee "$log"
  status="${PIPESTATUS[0]}"
  set -e
  if [ "$status" -eq 0 ]; then
    rm -f "$log"
    exit 0
  fi
  if ! grep -Eq 'DeadlineExceeded|waiting for BuildKit' "$log"; then
    rm -f "$log"
    exit "$status"
  fi
  rm -f "$log"
  if [ "$attempt" -ge "$max" ]; then
    echo "BuildKit stayed unavailable after ${max} attempts." >&2
    exit "$status"
  fi
  echo "BuildKit was not ready (attempt ${attempt}/${max}); bootstrapping and retrying." >&2
  docker buildx inspect --bootstrap >/dev/null 2>&1 || true
  attempt=$((attempt + 1))
  sleep $((attempt * 5))
done
