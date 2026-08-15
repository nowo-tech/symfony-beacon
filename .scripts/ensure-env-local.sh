#!/usr/bin/env bash
# REQ-ENV-003 — ensure operator working file is `.env.local` (never primary `.env`).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -f .env.local ]]; then
  exit 0
fi

if [[ -f .env ]]; then
  cp .env .env.local
  echo "Migrated .env → .env.local (REQ-ENV-003). Prefer deleting leftover .env so Compose does not prefer a stale copy."
  exit 0
fi

if [[ ! -f .env.dist ]]; then
  echo "ensure-env-local: .env.dist missing" >&2
  exit 1
fi

cp .env.dist .env.local
echo "Created .env.local from .env.dist"
