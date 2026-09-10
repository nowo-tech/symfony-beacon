#!/usr/bin/env sh
# Push docs/wiki/Home.md to the GitHub wiki Home page.
# Prereq: wiki enabled and initialized once
#   (https://github.com/nowo-tech/symfony-beacon/wiki — Create the first page if 404).
# Usage: from repo root — make wiki-push-home  OR  sh .scripts/wiki-push-home.sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/docs/wiki/Home.md"
OWNER_REPO="${WIKI_GITHUB_REPO:-nowo-tech/symfony-beacon}"
BRANCH="${WIKI_BRANCH:-master}"

if [ ! -f "$SRC" ]; then
  echo "ERROR: missing ${SRC}" >&2
  exit 1
fi

if [ -n "${WIKI_GIT_URL:-}" ]; then
  WIKI_URL="$WIKI_GIT_URL"
elif command -v gh >/dev/null 2>&1 && TOKEN="$(gh auth token 2>/dev/null)" && [ -n "$TOKEN" ]; then
  WIKI_URL="https://x-access-token:${TOKEN}@github.com/${OWNER_REPO}.wiki.git"
else
  WIKI_URL="https://github.com/${OWNER_REPO}.wiki.git"
fi

TMP="$(mktemp -d "${TMPDIR:-/tmp}/beacon-wiki.XXXXXX")"
cleanup() { rm -rf "$TMP"; }
trap cleanup EXIT

echo "Cloning wiki ${OWNER_REPO}.wiki…"
if ! git clone --depth 1 "$WIKI_URL" "$TMP/wiki" 2>"$TMP/clone.err"; then
  echo "ERROR: could not clone wiki." >&2
  sed 's#x-access-token:[^@]*@#x-access-token:***@#g' "$TMP/clone.err" >&2 || true
  echo >&2
  echo "Initialize the wiki once in the browser, then re-run:" >&2
  echo "  https://github.com/${OWNER_REPO}/wiki" >&2
  echo "  (Create the first page — title Home — paste from docs/wiki/Home.md, Save," >&2
  echo "   then: make wiki-push-home)" >&2
  exit 1
fi

cp "$SRC" "$TMP/wiki/Home.md"
cd "$TMP/wiki"
git add Home.md
if git diff --cached --quiet; then
  echo "Wiki Home already up to date."
  exit 0
fi

AUTHOR_NAME="${GIT_AUTHOR_NAME:-$(git -C "$ROOT" config user.name 2>/dev/null || true)}"
AUTHOR_EMAIL="${GIT_AUTHOR_EMAIL:-$(git -C "$ROOT" config user.email 2>/dev/null || true)}"
AUTHOR_NAME="${AUTHOR_NAME:-symfony-beacon wiki}"
AUTHOR_EMAIL="${AUTHOR_EMAIL:-wiki-bot@users.noreply.github.com}"

git -c user.email="$AUTHOR_EMAIL" -c user.name="$AUTHOR_NAME" \
  commit -m "docs(wiki): sync Home index for product UI manual."
git push origin "HEAD:${BRANCH}"
echo "Pushed wiki Home → https://github.com/${OWNER_REPO}/wiki"
