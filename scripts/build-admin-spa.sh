#!/usr/bin/env bash
set -euo pipefail

# Build Waaseyaa Nuxt admin SPA and copy into Minoo public/admin for PHP to serve.
# Usage (from Minoo repo root): ./scripts/build-admin-spa.sh
# Env:
#   ADMIN_PKG   Path to packages/admin (default: ../waaseyaa/packages/admin)
#   NUXT_BACKEND_URL  PHP dev API (default: http://127.0.0.1:8081)
#   NUXT_PUBLIC_APP_NAME  (default: Minoo)

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ADMIN_PKG="${ADMIN_PKG:-$ROOT/../waaseyaa/packages/admin}"

if [[ ! -f "$ADMIN_PKG/package.json" ]]; then
  echo "Admin package not found at $ADMIN_PKG — set ADMIN_PKG" >&2
  exit 1
fi

export NUXT_PUBLIC_APP_NAME="${NUXT_PUBLIC_APP_NAME:-Minoo}"
export NUXT_PUBLIC_BASE_URL="${NUXT_PUBLIC_BASE_URL:-}"
export NUXT_BACKEND_URL="${NUXT_BACKEND_URL:-http://127.0.0.1:8081}"

(cd "$ADMIN_PKG" && npm ci && npm run generate)

rm -rf "$ROOT/public/admin"
mkdir -p "$ROOT/public/admin"
cp -a "$ADMIN_PKG/.output/public/"* "$ROOT/public/admin/"

echo "Admin SPA copied to $ROOT/public/admin"
