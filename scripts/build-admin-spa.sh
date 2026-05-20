#!/usr/bin/env bash
set -euo pipefail

# Build Waaseyaa Nuxt admin SPA and copy into Minoo public/admin for PHP to serve.
# Built files are gitignored (see .gitignore); production builds also run in deploy.yml.
# Usage (from Minoo repo root): ./scripts/build-admin-spa.sh
#
# Path resolution (first match wins):
#   WAASEYAA_ADMIN_PATH — env (absolute or relative to Minoo root); overrides composer.
#   composer.json > extra.waaseyaa.admin_path — relative to Minoo root or absolute.
#
# Other env:
#   NUXT_BACKEND_URL  PHP dev API (default: http://127.0.0.1:8081)
#   NUXT_PUBLIC_APP_NAME  (default: Minoo)

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export MINOO_ROOT="$ROOT"

if [[ -n "${WAASEYAA_ADMIN_PATH:-}" ]]; then
  if [[ "${WAASEYAA_ADMIN_PATH}" != /* ]]; then
    ADMIN_PKG="${ROOT}/${WAASEYAA_ADMIN_PATH}"
  else
    ADMIN_PKG="${WAASEYAA_ADMIN_PATH}"
  fi
else
  ADMIN_PKG="$(php -r '
$root = getenv("MINOO_ROOT");
$f = $root . "/composer.json";
if (!is_file($f)) {
    fwrite(STDERR, "build-admin-spa: composer.json not found at project root.\n");
    exit(1);
}
try {
    $j = json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, "build-admin-spa: invalid composer.json — " . $e->getMessage() . "\n");
    exit(1);
}
$p = $j["extra"]["waaseyaa"]["admin_path"] ?? null;
if (!is_string($p) || $p === "") {
    fwrite(STDERR, "build-admin-spa: set WAASEYAA_ADMIN_PATH or composer.json extra.waaseyaa.admin_path.\n");
    exit(1);
}
$abs = str_starts_with($p, "/") ? $p : $root . "/" . $p;
echo $abs;
  ')" || exit 1
fi

if [[ ! -f "$ADMIN_PKG/package.json" ]]; then
  echo "build-admin-spa: admin package missing package.json: $ADMIN_PKG" >&2
  exit 1
fi

export NUXT_PUBLIC_APP_NAME="${NUXT_PUBLIC_APP_NAME:-Minoo}"
export NUXT_PUBLIC_BASE_URL="${NUXT_PUBLIC_BASE_URL:-}"
export NUXT_BACKEND_URL="${NUXT_BACKEND_URL:-http://127.0.0.1:8081}"

# Use `npm install --package-lock=false` instead of `npm ci`: the framework's
# admin-surface package may ship a lockfile out of sync with its package.json
# (e.g. missing commander@13.1.0 in the alpha.181/.182 train). `--package-lock=false`
# regenerates node_modules without mutating the vendored lockfile, so the build
# keeps moving even when the framework's lockfile is stale.
(cd "$ADMIN_PKG" && npm install --no-audit --no-fund --package-lock=false && npm run generate)

rm -rf "$ROOT/public/admin"
mkdir -p "$ROOT/public/admin"
cp -a "$ADMIN_PKG/.output/public/"* "$ROOT/public/admin/"

echo "Admin SPA copied to $ROOT/public/admin"
